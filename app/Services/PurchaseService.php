<?php

namespace App\Services;

use App\Models\Existencia;
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
    public function __construct(private StockService $stock, private InventoryMathService $math) {}

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
        return DB::transaction(function () use ($compra, $userId) {

            // H-F09 · Releer con bloqueo: dos clics no dan doble entrada.
            $compra = $this->bloquear($compra);

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

            foreach ($compra->items()->with('product')->get() as $item) {
                if (! $item->product) {
                    continue;
                }

                // Saldo de TODA la empresa antes de la entrada (el costo es por
                // producto, no por local). Se suma desde `existencias`, que es la
                // fuente de verdad: `products.stock_total` es un resumen que los
                // seeders no mantienen y puede estar en 0. Sin el filtro de tienda
                // para sumar todos los locales; el producto ya es de esta empresa.
                $stockAntes = (float) Existencia::withoutGlobalScopes()
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->sum('stock');
                $costoAntes = (float) Product::whereKey($item->product_id)->value('cost');

                $this->stock->ingresar(
                    $item->product,
                    (float) $item->quantity,
                    'compra',
                    $compra,
                    $userId,
                    'Compra ' . $compra->number . ' · ' . ($compra->supplier->name ?? ''),
                    (float) $item->unit_cost,
                    tiendaId: $compra->tienda_id, // H-F12: al local de la compra
                );

                // H-F02 · Configuración › Inventario › «Método de costeo»:
                // 'promedio' = promedio ponderado · 'ultimo' = costo de esta compra.
                if ($item->update_cost) {
                    $nuevoCosto = \App\Models\Setting::obtener('inventario.costo_metodo', 'promedio') === 'ultimo'
                        ? (float) $item->unit_cost
                        : $this->math->weightedAverageCost(
                            $stockAntes, $costoAntes, (float) $item->quantity, (float) $item->unit_cost,
                        );

                    $item->product->forceFill(['cost' => $nuevoCosto])->save();
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
        return DB::transaction(function () use ($compra, $userId, $motivo) {

            $compra = $this->bloquear($compra); // H-F09

            if ($compra->status === Purchase::ANULADA) {
                throw ValidationException::withMessages([
                    'compra' => 'Esta compra ya estaba anulada.',
                ]);
            }

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
                            tiendaId: $compra->tienda_id, // H-F12
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

    /** Relee la compra con bloqueo de fila; solo dentro de una transacción. */
    private function bloquear(Purchase $compra): Purchase
    {
        return Purchase::whereKey($compra->getKey())->lockForUpdate()->firstOrFail();
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
