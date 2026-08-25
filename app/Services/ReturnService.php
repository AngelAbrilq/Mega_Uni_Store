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
 */
class ReturnService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array<int, array{sale_item_id:int, quantity:float}>  $lineas
     */
    public function registrar(Sale $venta, array $lineas, int $userId, string $motivo, bool $reingresar = true): SaleReturn
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

        return DB::transaction(function () use ($venta, $lineas, $userId, $motivo, $reingresar) {

            $devolucion = new SaleReturn([
                'number'      => SaleReturn::siguienteNumero(),
                'sale_id'     => $venta->id,
                'user_id'     => $userId,
                'reason'      => $motivo,
                'restock'     => $reingresar,
                'returned_at' => now(),
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

                $item->forceFill([
                    'returned_quantity' => (float) $item->returned_quantity + $cantidad,
                ])->save();

                // La mercancía vuelve a bodega salvo que venga dañada.
                if ($reingresar && $item->product) {
                    $this->stock->ingresar(
                        $item->product,
                        $cantidad,
                        'devolucion',
                        $devolucion,
                        $userId,
                        'Devolución ' . $devolucion->number . ' de la venta ' . $venta->number,
                    );
                }

                $subtotal += $lineaSub;
                $impuesto += $lineaImp;
            }

            $devolucion->forceFill([
                'subtotal'  => round($subtotal, 2),
                'tax_total' => round($impuesto, 2),
                'total'     => round($subtotal + $impuesto, 2),
            ])->save();

            return $devolucion->fresh(['items', 'sale', 'user']);
        });
    }
}
