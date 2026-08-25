<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Compras a proveedores.
 *
 * Registrar la compra NO mueve inventario: eso solo pasa al recibirla,
 * que es cuando la mercancía entra de verdad a la bodega. Separarlo
 * permite dejar la orden hecha y darle entrada cuando llegue.
 */
class PurchaseService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array<int, array{product_id:int, quantity:float, unit_cost:float, tax_rate?:float, update_cost?:bool}>  $lineas
     */
    public function registrar(
        int $supplierId,
        array $lineas,
        int $userId,
        ?string $factura = null,
        ?string $fecha = null,
        ?string $notas = null,
    ): Purchase {
        if (empty($lineas)) {
            throw ValidationException::withMessages([
                'items' => 'La compra no tiene ningún producto.',
            ]);
        }

        return DB::transaction(function () use ($supplierId, $lineas, $userId, $factura, $fecha, $notas) {

            $compra = new Purchase([
                'number'         => Purchase::siguienteNumero(),
                'invoice_number' => $factura,
                'supplier_id'    => $supplierId,
                'user_id'        => $userId,
                'status'         => Purchase::BORRADOR,
                'notes'          => $notas,
                'ordered_at'     => $fecha ?: now()->toDateString(),
            ]);
            $compra->save();

            $this->reemplazarLineas($compra, $lineas);

            AlertService::olvidar();

            return $compra->fresh(['items', 'supplier']);
        });
    }

    /** Cambia los renglones de una compra que todavía está en borrador. */
    public function actualizar(Purchase $compra, int $supplierId, array $lineas, ?string $factura, ?string $fecha, ?string $notas): Purchase
    {
        if (! $compra->editable) {
            throw ValidationException::withMessages([
                'compra' => 'Una compra ya recibida no se puede modificar.',
            ]);
        }

        return DB::transaction(function () use ($compra, $supplierId, $lineas, $factura, $fecha, $notas) {
            $compra->forceFill([
                'supplier_id'    => $supplierId,
                'invoice_number' => $factura,
                'ordered_at'     => $fecha ?: $compra->ordered_at,
                'notes'          => $notas,
            ])->save();

            $compra->items()->delete();
            $this->reemplazarLineas($compra, $lineas);

            return $compra->fresh(['items', 'supplier']);
        });
    }

    /**
     * Da entrada a la mercancía: suma el stock de cada producto, escribe
     * su movimiento de kardex y, si se pidió, actualiza el costo.
     */
    public function recibir(Purchase $compra, int $userId): Purchase
    {
        if ($compra->status === Purchase::RECIBIDA) {
            throw ValidationException::withMessages([
                'compra' => 'Esta compra ya había sido recibida.',
            ]);
        }

        if ($compra->status === Purchase::ANULADA) {
            throw ValidationException::withMessages([
                'compra' => 'No se puede recibir una compra anulada.',
            ]);
        }

        return DB::transaction(function () use ($compra, $userId) {

            foreach ($compra->items()->with('product')->get() as $item) {
                if (! $item->product) {
                    continue;
                }

                $this->stock->ingresar(
                    $item->product,
                    (float) $item->quantity,
                    'compra',
                    $compra,
                    $userId,
                    'Compra ' . $compra->number . ' · ' . ($compra->supplier->name ?? ''),
                    (float) $item->unit_cost,
                );

                // El costo del producto se actualiza al de la última compra.
                if ($item->update_cost) {
                    $item->product->forceFill(['cost' => (float) $item->unit_cost])->save();
                }
            }

            $compra->forceFill([
                'status'      => Purchase::RECIBIDA,
                'received_by' => $userId,
                'received_at' => now(),
            ])->save();

            AlertService::olvidar();

            return $compra->fresh();
        });
    }

    /** Anula una compra. Si ya se había recibido, saca la mercancía. */
    public function anular(Purchase $compra, int $userId, string $motivo): Purchase
    {
        if ($compra->status === Purchase::ANULADA) {
            throw ValidationException::withMessages([
                'compra' => 'Esta compra ya estaba anulada.',
            ]);
        }

        return DB::transaction(function () use ($compra, $userId, $motivo) {

            if ($compra->status === Purchase::RECIBIDA) {
                foreach ($compra->items()->with('product')->get() as $item) {
                    if ($item->product) {
                        $this->stock->descontar(
                            $item->product,
                            (float) $item->quantity,
                            'anulacion',
                            $compra,
                            $userId,
                            'Anulación de la compra ' . $compra->number,
                        );
                    }
                }
            }

            $compra->forceFill([
                'status' => Purchase::ANULADA,
                'notes'  => trim(($compra->notes ? $compra->notes . "\n" : '') . 'Anulada: ' . $motivo),
            ])->save();

            return $compra->fresh();
        });
    }

    /**
     * Crea los renglones y recalcula los totales de la cabecera.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function reemplazarLineas(Purchase $compra, array $lineas): void
    {
        $subtotal = 0.0;
        $impuesto = 0.0;

        foreach ($lineas as $linea) {
            $producto = Product::findOrFail($linea['product_id']);
            $cantidad = (float) $linea['quantity'];
            $costo    = (float) $linea['unit_cost'];

            if ($cantidad <= 0) {
                throw ValidationException::withMessages([
                    'items' => "La cantidad de «{$producto->name}» debe ser mayor que cero.",
                ]);
            }

            $lineaSub = round($costo * $cantidad, 2);
            $tasa     = (float) ($linea['tax_rate'] ?? 0);
            $imp      = round($lineaSub * $tasa / 100, 2);

            PurchaseItem::create([
                'purchase_id' => $compra->id,
                'product_id'  => $producto->id,
                'name'        => $producto->name,
                'quantity'    => $cantidad,
                'unit_cost'   => $costo,
                'tax_rate'    => $tasa,
                'tax_amount'  => $imp,
                'subtotal'    => $lineaSub,
                'total'       => round($lineaSub + $imp, 2),
                'update_cost' => (bool) ($linea['update_cost'] ?? true),
            ]);

            $subtotal += $lineaSub;
            $impuesto += $imp;
        }

        $compra->forceFill([
            'subtotal'  => round($subtotal, 2),
            'tax_total' => round($impuesto, 2),
            'total'     => round($subtotal + $impuesto, 2),
        ])->save();
    }
}
