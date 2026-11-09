<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Devoluciones parciales de una venta.
 *
 * La venta original no se toca: se crea un documento de devolución que
 * la referencia. Así el histórico de ventas sigue cuadrando y se puede
 * saber exactamente qué se devolvió, cuándo y por qué.
 *
 * ── Aprobación (RN-30) ──
 *
 *   registrar(aprobar: true)   quien tiene `ventas.aprobar_devolucion`: se aplica ya.
 *   registrar(aprobar: false)  queda PENDIENTE: reserva la cantidad en
 *                              `sale_items.returned_quantity` (nadie puede
 *                              devolverla dos veces) pero NO mueve inventario.
 *   aprobar()                  aplica el reingreso al inventario.
 *   rechazar()                 libera la cantidad reservada; nada se movió.
 */
class ReturnService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array<int, array{sale_item_id:int, quantity:float}>  $lineas
     */
    public function registrar(
        Sale $venta,
        array $lineas,
        int $userId,
        string $motivo,
        bool $reingresar = true,
        bool $aprobar = true,
    ): SaleReturn
    {
        if ($venta->status === Sale::ANULADA) {
            throw ValidationException::withMessages([
                'venta' => 'No se puede devolver sobre una venta anulada.',
            ]);
        }

        // Se descartan los renglones sin cantidad antes de validar.
        $lineas = array_values(array_filter(
            $lineas,
            fn ($l) => (float) ($l['quantity'] ?? 0) > 0
        ));

        if (empty($lineas)) {
            throw ValidationException::withMessages([
                'items' => 'Indica al menos un producto y una cantidad a devolver.',
            ]);
        }

        return DB::transaction(function () use ($venta, $lineas, $userId, $motivo, $reingresar, $aprobar) {

            // H-F09 · Se serializa contra una anulación simultánea de la misma venta.
            $venta = Sale::whereKey($venta->getKey())->lockForUpdate()->firstOrFail();

            // RN-43: con una anulación por aprobar, devolver pisaría esa decisión.
            if (\App\Models\SaleVoidRequest::where('sale_id', $venta->id)->where('status', \App\Models\SaleVoidRequest::PENDIENTE)->exists()) {
                throw ValidationException::withMessages([
                    'items' => 'Esta venta tiene una solicitud de anulación esperando aprobación. Resuélvela antes de registrar una devolución.',
                ]);
            }

            if ($venta->status === Sale::ANULADA) {
                throw ValidationException::withMessages([
                    'venta' => 'No se puede devolver sobre una venta anulada.',
                ]);
            }

            $devolucion = new SaleReturn([
                'number'      => SaleReturn::siguienteNumero(),
                'sale_id'     => $venta->id,
                'user_id'     => $userId,
                'reason'      => $motivo,
                'restock'     => $reingresar,
                'returned_at' => now(),
                'status'      => SaleReturn::PENDIENTE,
            ]);
            $devolucion->save();

            $subtotal = 0.0;
            $impuesto = 0.0;

            foreach ($lineas as $linea) {
                /** @var SaleItem $item */
                $item = SaleItem::with('product')
                    ->where('sale_id', $venta->id)
                    ->lockForUpdate()
                    ->findOrFail($linea['sale_item_id']);

                $cantidad  = (float) $linea['quantity'];
                $pendiente = (float) $item->quantity - (float) $item->returned_quantity;

                if ($cantidad > $pendiente + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => "De «{$item->name}» solo quedan "
                                 . rtrim(rtrim(number_format($pendiente, 3, ',', '.'), '0'), ',')
                                 . ' unidades por devolver.',
                    ]);
                }

                // Se devuelve la parte proporcional de lo que se cobró.
                $proporcion = (float) $item->quantity > 0 ? $cantidad / (float) $item->quantity : 0;
                $lineaSub   = round((float) $item->subtotal * $proporcion, 2);
                $lineaImp   = round((float) $item->tax_amount * $proporcion, 2);

                SaleReturnItem::create([
                    'sale_return_id' => $devolucion->id,
                    'sale_item_id'   => $item->id,
                    'product_id'     => $item->product_id,
                    'name'           => $item->name,
                    'quantity'       => $cantidad,
                    'unit_price'     => (float) $item->unit_price,
                    'unit_cost'      => (float) $item->unit_cost,
                    'tax_rate'       => (float) $item->tax_rate,
                    'tax_amount'     => $lineaImp,
                    'subtotal'       => $lineaSub,
                    'total'          => round($lineaSub + $lineaImp, 2),
                ]);

                // Reserva la cantidad aunque quede pendiente: así no se
                // puede pedir dos veces la devolución de la misma unidad.
                $item->forceFill([
                    'returned_quantity' => (float) $item->returned_quantity + $cantidad,
                ])->save();

                $subtotal += $lineaSub;
                $impuesto += $lineaImp;
            }

            $devolucion->forceFill([
                'subtotal'  => round($subtotal, 2),
                'tax_total' => round($impuesto, 2),
                'total'     => round($subtotal + $impuesto, 2),
            ])->save();

            if ($aprobar) {
                $this->aplicar($devolucion, $venta, $userId);
            }

            return $devolucion->fresh(['items', 'sale', 'user']);
        });
    }

    /**
     * Aprueba una devolución pendiente: la mercancía vuelve al inventario.
     *
     * @throws ValidationException si ya no está pendiente (doble clic, otra pestaña).
     */
    public function aprobar(SaleReturn $devolucion, int $userId): SaleReturn
    {
        return DB::transaction(function () use ($devolucion, $userId) {
            $devolucion = $this->pendienteBloqueada($devolucion);
            $venta = Sale::whereKey($devolucion->sale_id)->lockForUpdate()->firstOrFail();

            $this->aplicar($devolucion, $venta, $userId);

            return $devolucion->fresh(['items', 'sale', 'user', 'reviewer']);
        });
    }

    /**
     * Rechaza una devolución pendiente: libera la cantidad reservada.
     * No hay inventario que revertir porque nunca se movió.
     */
    public function rechazar(SaleReturn $devolucion, int $userId, string $motivo): SaleReturn
    {
        return DB::transaction(function () use ($devolucion, $userId, $motivo) {
            $devolucion = $this->pendienteBloqueada($devolucion);

            foreach ($devolucion->items as $linea) {
                $item = SaleItem::whereKey($linea->sale_item_id)->lockForUpdate()->first();

                if ($item) {
                    $item->forceFill([
                        'returned_quantity' => max(0, (float) $item->returned_quantity - (float) $linea->quantity),
                    ])->save();
                }
            }

            $devolucion->forceFill([
                'status'           => SaleReturn::RECHAZADA,
                'reviewed_by'      => $userId,
                'reviewed_at'      => now(),
                'rejection_reason' => $motivo,
            ])->save();

            return $devolucion->fresh(['items', 'sale', 'user', 'reviewer']);
        });
    }

    /* ───────────────────────── Apoyo ───────────────────────── */

    /** Reingresa la mercancía (si aplica) y marca la devolución como aprobada. */
    private function aplicar(SaleReturn $devolucion, Sale $venta, int $userId): void
    {
        if ($devolucion->restock) {
            foreach ($devolucion->items()->with('product')->get() as $linea) {
                if (! $linea->product) {
                    continue;
                }

                $this->stock->ingresar(
                    $linea->product,
                    (float) $linea->quantity,
                    'devolucion',
                    $devolucion,
                    $userId,
                    'Devolución ' . $devolucion->number . ' de la venta ' . $venta->number,
                    tiendaId: $venta->tienda_id, // H-F12
                );
            }
        }

        $devolucion->forceFill([
            'status'      => SaleReturn::APROBADA,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ])->save();

        // RN-38: la parte proporcional de los puntos de la venta.
        app(FidelizacionService::class)->revertirDevolucion($devolucion, $venta, $userId);
    }

    /**
     * Relee la devolución con bloqueo de fila y exige que siga pendiente (H-F09).
     */
    private function pendienteBloqueada(SaleReturn $devolucion): SaleReturn
    {
        $devolucion = SaleReturn::whereKey($devolucion->getKey())->lockForUpdate()->firstOrFail();

        if ($devolucion->status !== SaleReturn::PENDIENTE) {
            throw ValidationException::withMessages([
                'devolucion' => 'Esta devolución ya fue ' . $devolucion->status . '.',
            ]);
        }

        return $devolucion->load('items');
    }
}
