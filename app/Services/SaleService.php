<?php

namespace App\Services;


use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleCoupon;
use App\Models\SalePayment;
use App\Models\Tax;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registrar una venta toca cinco tablas y el inventario. O sale todo o
 * no sale nada: por eso vive en una transacción y no repartido por el
 * controlador.
 */
class SaleService
{
    public function __construct(
        private StockService $stock,
        private LimiteService $limites,
        private CouponService $cupones,
    ) {}

    /**
     * @param  array<int, array{product_id:int, quantity:float, unit_price?:float, discount?:float}>  $lineas
     * @param  array<int, array{payment_method_id:int, amount:float, reference?:string}>  $pagos
     * @param  bool  $permitirCambioDePrecio  true solo si quien llama ya verificó el permiso
     *                                         `ventas.cambiar_precio` o el precio lo fija el
     *                                         propio sistema (p. ej. el valor pactado de una cita).
     *
     * @throws ValidationException  precio alterado sin permiso, descuento sobre el tope,
     *                              stock insuficiente o pago incompleto.
     */
    public function registrar(
        array $lineas,
        array $pagos,
        int $userId,
        ?int $customerId = null,
        ?string $notas = null,
        bool $descontarStock = true,
        bool $permitirCambioDePrecio = false,
        ?string $codigoCupon = null,
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

        /* Reglas del negocio leídas UNA vez por venta (Configuración › Ventas). */
        $permitirNegativo = Setting::activo('venta.stock_negativo');
        $descuentoMaximo  = (float) Setting::obtener('venta.descuento_max', '100');

        return DB::transaction(function () use ($lineas, $pagos, $userId, $customerId, $notas, $descontarStock, $permitirCambioDePrecio, $permitirNegativo, $descuentoMaximo, $codigoCupon) {

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
            $renglones = [];

            /* ─────────── 1.ª pasada: precio, descuento manual y validaciones ─────────── */
            foreach ($lineas as $linea) {
                $producto = Product::with('tax')->findOrFail($linea['product_id']);
                $cantidad = (float) $linea['quantity'];

                if ($cantidad <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "La cantidad de «{$producto->name}» debe ser mayor que cero.",
                    ]);
                }

                if ($descontarStock && ! $permitirNegativo && $cantidad > (float) $producto->stock) {
                    throw ValidationException::withMessages([
                        'items' => "No hay existencias suficientes de «{$producto->name}»: "
                                 . "quedan {$producto->stock} y estás vendiendo {$cantidad}.",
                    ]);
                }

                /*
                 * H-F01 · El precio lo decide el servidor.
                 * La referencia es el precio del LOCAL (existencias.precio), el mismo
                 * que muestra el POS. Un unit_price distinto solo pasa si quien llama
                 * tiene permiso; si no, la venta se rechaza en vez de cobrar mal.
                 */
                $precioLista = (float) $producto->precio_tienda;
                $precio      = isset($linea['unit_price']) ? round((float) $linea['unit_price'], 2) : $precioLista;
                $precioCambiado = abs($precio - $precioLista) > 0.009;

                if ($precioCambiado && ! $permitirCambioDePrecio) {
                    throw ValidationException::withMessages([
                        'items' => "No tienes permiso para cambiar el precio de «{$producto->name}».",
                    ]);
                }

                $desc     = (float) ($linea['discount'] ?? 0);
                $bruto    = $precio * $cantidad;
                $lineaSub = round($bruto - $desc, 2);

                /* H-F02 · Tope de descuento por renglón (Configuración › Ventas). */
                if ($desc > 0 && $bruto > 0 && ($desc / $bruto * 100) > $descuentoMaximo + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => "El descuento de «{$producto->name}» supera el máximo permitido ({$descuentoMaximo} %).",
                    ]);
                }

                if ($precioCambiado) {
                    $this->auditarCambioDePrecio($venta, $producto, $precioLista, $precio, $userId);
                }

                if ($lineaSub < 0) {
                    throw ValidationException::withMessages([
                        'items' => "El descuento de «{$producto->name}» supera el valor del renglón.",
                    ]);
                }

                $renglones[] = compact('producto', 'cantidad', 'precio', 'desc', 'lineaSub');
            }

            /*
             * ─────────── Cupón (VD-CUP-001 · RN-35) ───────────
             * Se valida y se gasta el uso con el cupón bloqueado. El descuento
             * se reparte entre los renglones ANTES del impuesto: el IVA se
             * cobra sobre lo que el cliente de verdad paga.
             */
            $cupon   = null;
            $repartos = [];

            if ($codigoCupon !== null && trim($codigoCupon) !== '') {
                $cupon    = $this->cupones->reservar($codigoCupon, array_sum(array_column($renglones, 'lineaSub')));
                $repartos = $this->cupones->repartir($cupon['descuento'], array_column($renglones, 'lineaSub'));
            }

            /* ─────────── 2.ª pasada: impuestos, renglones y kardex ─────────── */
            foreach ($renglones as $i => $r) {
                ['producto' => $producto, 'cantidad' => $cantidad, 'precio' => $precio] = $r;
                $parteCupon = (float) ($repartos[$i] ?? 0);
                $desc       = round($r['desc'] + $parteCupon, 2);
                $lineaSub   = round($r['lineaSub'] - $parteCupon, 2);

                // El impuesto se calcula sobre el renglón ya descontado.
                // AT-PRI-001: el principal más los adicionales del producto.
                $impuestos = Tax::sobreRenglon($producto->impuestos(), $lineaSub, $cantidad);
                $tasa      = $impuestos['tasa'];
                $imp       = $impuestos['monto'];

                SaleItem::create([
                    'sale_id'    => $venta->id,
                    'product_id' => $producto->id,
                    'name'       => $producto->name,
                    'sku'        => $producto->sku,
                    'quantity'   => $cantidad,
                    'unit_price' => $precio,
                    'unit_cost'  => (float) $producto->cost,
                    'discount'   => $desc,
                    'tax_name'   => $impuestos['nombre'],
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

            // TBL-VCU-001: qué cupón se usó y cuánto descontó, con el código congelado.
            if ($cupon) {
                SaleCoupon::create([
                    'sale_id'         => $venta->id,
                    'coupon_id'       => $cupon['cupon']->id,
                    'code'            => $cupon['cupon']->code,
                    'discount_amount' => $cupon['descuento'],
                ]);
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
                    // TBL-PAG-001: transferencias y similares esperan verificación.
                    'status'            => $medio->requires_verification
                        ? SalePayment::PENDIENTE
                        : SalePayment::APROBADO,
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

            // CL-PUN-001 · RN-38: puntos y total gastado del cliente en este local.
            app(FidelizacionService::class)->acumular($venta, $userId);

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
        return DB::transaction(function () use ($venta, $userId, $motivo) {

            /*
             * H-F09 · El estado se relee CON bloqueo dentro de la transacción:
             * dos clics seguidos en «Anular» no pueden reingresar dos veces.
             */
            $venta = Sale::whereKey($venta->getKey())->lockForUpdate()->firstOrFail();

            if ($venta->status === Sale::ANULADA) {
                throw ValidationException::withMessages([
                    'venta' => 'Esta venta ya estaba anulada.',
                ]);
            }

            /*
             * H-F08 · Una venta con devoluciones no se anula: lo devuelto ya
             * volvió a bodega y a la caja; anular reingresaría la cantidad
             * completa y duplicaría ese inventario y ese dinero.
             */
            // Las pendientes también bloquean: ya reservaron cantidad. Las rechazadas no.
            if ($venta->returns()->vigentes()->exists()) {
                throw ValidationException::withMessages([
                    'venta' => 'Esta venta ya tiene devoluciones. Anularla duplicaría lo que ya se devolvió: '
                             . 'registra una devolución por lo que falta.',
                ]);
            }

            foreach ($venta->items()->with('product')->get() as $item) {
                if ($item->product) {
                    $this->stock->ingresar(
                        $item->product,
                        (float) $item->quantity,
                        'anulacion',
                        $venta,
                        $userId,
                        'Anulación de la venta ' . $venta->number,
                        tiendaId: $venta->tienda_id, // H-F12
                    );
                }
            }

            // RN-35: el cupón de una venta anulada recupera su uso.
            $this->cupones->liberar($venta);

            // RN-38: los puntos que ganó la venta (lo que quede sin devolver) se restan.
            app(FidelizacionService::class)->revertirVenta($venta, $userId);

            $venta->forceFill([
                'status'      => Sale::ANULADA,
                'voided_by'   => $userId,
                'voided_at'   => now(),
                'void_reason' => $motivo,
            ])->save();

            // RN-43: la solicitud pendiente (si la había) queda aprobada por quien anuló.
            \App\Models\SaleVoidRequest::query()
                ->where('sale_id', $venta->id)->where('status', \App\Models\SaleVoidRequest::PENDIENTE)
                ->update(['status' => \App\Models\SaleVoidRequest::APROBADA, 'reviewed_by' => $userId, 'reviewed_at' => now(), 'updated_at' => now()]);

            return $venta->fresh();
        });
    }

    /**
     * Deja rastro de una venta cobrada a un precio distinto al del local.
     * Auditar nunca debe tumbar la venta: los errores se ignoran.
     */
    private function auditarCambioDePrecio(Sale $venta, Product $producto, float $antes, float $despues, int $userId): void
    {
        try {
            AuditLog::create([
                'user_id'         => $userId,
                'user_name'       => auth()->user()?->name,
                'event'           => 'precio_modificado',
                'auditable_type'  => Sale::class,
                'auditable_id'    => $venta->id,
                'auditable_label' => mb_substr("Venta {$venta->number} · {$producto->name}", 0, 200),
                'old_values'      => ['product_id' => $producto->id, 'unit_price' => $antes],
                'new_values'      => ['product_id' => $producto->id, 'unit_price' => $despues],
                'ip'              => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
