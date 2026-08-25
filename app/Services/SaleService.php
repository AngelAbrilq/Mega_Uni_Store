<?php

namespace App\Services;


use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registrar una venta toca cinco tablas y el inventario. O sale todo o
 * no sale nada: por eso vive en una transacción y no repartido por el
 * controlador.
 */
class SaleService
{
    public function __construct(private StockService $stock, private LimiteService $limites) {}

    /**
     * @param  array<int, array{product_id:int, quantity:float, unit_price?:float, discount?:float}>  $lineas
     * @param  array<int, array{payment_method_id:int, amount:float, reference?:string}>  $pagos
     */
    public function registrar(
        array $lineas,
        array $pagos,
        int $userId,
        ?int $customerId = null,
        ?string $notas = null,
        bool $descontarStock = true,
    ): Sale {
        if (empty($lineas)) {
            throw ValidationException::withMessages([
                'items' => 'La venta no tiene ningún producto.',
            ]);
        }

        /**
         * El tope de ventas del mes.
         *
         * Se comprueba ANTES de abrir la transacción, no adentro: si la
         * excepción saliera con la venta a medio escribir, el consecutivo
         * ya se habría gastado y quedaría un hueco en la numeración que
         * después nadie sabe explicar.
         */
        $this->limites->exigir('ventas_mes');

        return DB::transaction(function () use ($lineas, $pagos, $userId, $customerId, $notas, $descontarStock) {

            $turno = CashSession::abiertaDe($userId);

            $venta = new Sale([
                'number'          => Sale::siguienteNumero(),
                'customer_id'     => $customerId,
                'user_id'         => $userId,
                'cash_session_id' => $turno?->id,
                'status'          => Sale::PAGADA,
                'notes'           => $notas,
                'sold_at'         => now(),
            ]);
            $venta->save();

            $subtotal = 0.0;
            $impuesto = 0.0;
            $descuento = 0.0;
            $costo = 0.0;

            foreach ($lineas as $linea) {
                $producto = Product::with('tax')->findOrFail($linea['product_id']);
                $cantidad = (float) $linea['quantity'];

                if ($cantidad <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "La cantidad de «{$producto->name}» debe ser mayor que cero.",
                    ]);
                }

                if ($descontarStock && $cantidad > (float) $producto->stock) {
                    throw ValidationException::withMessages([
                        'items' => "No hay existencias suficientes de «{$producto->name}»: "
                                 . "quedan {$producto->stock} y estás vendiendo {$cantidad}.",
                    ]);
                }

                $precio  = isset($linea['unit_price']) ? (float) $linea['unit_price'] : (float) $producto->price;
                $desc    = (float) ($linea['discount'] ?? 0);
                $lineaSub = round($precio * $cantidad - $desc, 2);

                if ($lineaSub < 0) {
                    throw ValidationException::withMessages([
                        'items' => "El descuento de «{$producto->name}» supera el valor del renglón.",
                    ]);
                }

                // El impuesto se calcula sobre el renglón ya descontado.
                $tasa = 0.0;
                $imp  = 0.0;

                if ($producto->tax) {
                    if ($producto->tax->type === 'fixed') {
                        $imp = round((float) $producto->tax->rate * $cantidad, 2);
                    } else {
                        $tasa = (float) $producto->tax->rate;
                        $imp  = round($lineaSub * $tasa / 100, 2);
                    }
                }

                SaleItem::create([
                    'sale_id'    => $venta->id,
                    'product_id' => $producto->id,
                    'name'       => $producto->name,
                    'sku'        => $producto->sku,
                    'quantity'   => $cantidad,
                    'unit_price' => $precio,
                    'unit_cost'  => (float) $producto->cost,
                    'discount'   => $desc,
                    'tax_name'   => $producto->tax?->name,
                    'tax_rate'   => $tasa,
                    'tax_amount' => $imp,
                    'subtotal'   => $lineaSub,
                    'total'      => round($lineaSub + $imp, 2),
                ]);

                $subtotal  += $lineaSub;
                $impuesto  += $imp;
                $descuento += $desc;
                $costo     += (float) $producto->cost * $cantidad;

                if ($descontarStock) {
                    $this->stock->descontar($producto, $cantidad, 'venta', $venta, $userId,
                        'Venta ' . $venta->number);
                }
            }

            $total = round($subtotal + $impuesto, 2);

            /* ─────────── Pagos ─────────── */
            $pagado = 0.0;

            foreach ($pagos as $pago) {
                $monto = round((float) $pago['amount'], 2);

                if ($monto <= 0) {
                    continue;
                }

                $medio = PaymentMethod::findOrFail($pago['payment_method_id']);

                SalePayment::create([
                    'sale_id'           => $venta->id,
                    'payment_method_id' => $medio->id,
                    'method_name'       => $medio->name,
                    'amount'            => $monto,
                    'reference'         => $pago['reference'] ?? null,
                ]);

                $pagado += $monto;
            }

            if ($pagado + 0.009 < $total) {
                throw ValidationException::withMessages([
                    'pagos' => 'Lo recibido ($' . number_format($pagado, 0, ',', '.')
                             . ') no alcanza para cubrir el total ($' . number_format($total, 0, ',', '.') . ').',
                ]);
            }

            $venta->forceFill([
                'subtotal'       => round($subtotal, 2),
                'discount_total' => round($descuento, 2),
                'tax_total'      => round($impuesto, 2),
                'total'          => $total,
                'paid_total'     => round($pagado, 2),
                'change_amount'  => round(max(0, $pagado - $total), 2),
                'cost_total'     => round($costo, 2),
                'profit_total'   => round($subtotal - $costo, 2),
            ])->save();

            return $venta->fresh(['items', 'payments', 'customer', 'user']);
        });
    }

    /**
     * Anular una venta no la borra: la marca y devuelve la mercancía al
     * inventario con su propio movimiento de kardex. Así queda el rastro
     * de que existió y de por qué se deshizo.
     */
    public function anular(Sale $venta, int $userId, string $motivo): Sale
    {
        if ($venta->status === Sale::ANULADA) {
            throw ValidationException::withMessages([
                'venta' => 'Esta venta ya estaba anulada.',
            ]);
        }

        return DB::transaction(function () use ($venta, $userId, $motivo) {

            foreach ($venta->items()->with('product')->get() as $item) {
                if ($item->product) {
                    $this->stock->ingresar(
                        $item->product,
                        (float) $item->quantity,
                        'anulacion',
                        $venta,
                        $userId,
                        'Anulación de la venta ' . $venta->number,
                    );
                }
            }

            $venta->forceFill([
                'status'      => Sale::ANULADA,
                'voided_by'   => $userId,
                'voided_at'   => now(),
                'void_reason' => $motivo,
            ])->save();

            return $venta->fresh();
        });
    }
}
