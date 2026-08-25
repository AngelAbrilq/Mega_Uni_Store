<?php

namespace App\Services;

use App\Models\Existencia;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Contexto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Único punto por el que se mueve el inventario.
 *
 * Nadie más debería escribir una existencia a mano: si se toca el saldo
 * sin registrar el movimiento, el kardex deja de cuadrar y ya no hay
 * forma de saber qué pasó.
 *
 * ── Lo que cambió con el multi-tienda ──
 *
 * El saldo ya no está en el producto sino en la fila de `existencias` del
 * local. Eso cambia dos cosas importantes:
 *
 *   1. La fila que se bloquea es la de la existencia, no la del producto.
 *      Bloquear el producto haría que una venta en el centro tuviera que
 *      esperar a una venta del mismo artículo en el norte, cuando son dos
 *      saldos que no se tocan.
 *
 *   2. Si el local todavía no tenía fila para ese producto, se crea en el
 *      momento. Pasa cuando llega mercancía nueva a un local que no la
 *      manejaba: la compra la da de alta ahí sola.
 *
 * `products.stock_total` se mantiene al día como la suma de todos los
 * locales. No es la verdad —la verdad son las existencias— pero le ahorra
 * al informe consolidado sumar la tabla entera cada vez.
 */
class StockService
{
    /**
     * Aplica un movimiento y devuelve el registro del kardex.
     *
     * @param  float     $cantidad  positiva entra, negativa sale
     * @param  int|null  $tiendaId  null = el local en el que se trabaja
     */
    public function mover(
        Product $producto,
        float $cantidad,
        string $motivo,
        ?Model $origen = null,
        ?int $userId = null,
        ?string $nota = null,
        ?float $costoUnitario = null,
        ?int $tiendaId = null,
    ): StockMovement {
        $tiendaId ??= Contexto::tiendaId();

        if (! $tiendaId) {
            // Preferible a mover el inventario del local equivocado: eso no
            // se nota el mismo día y después no se sabe desde cuándo.
            throw new \RuntimeException(
                'No se puede mover inventario sin saber en qué tienda. '
                . 'Revisa que el usuario tenga un local asignado.'
            );
        }

        return DB::transaction(function () use ($producto, $cantidad, $motivo, $origen, $userId, $nota, $costoUnitario, $tiendaId) {

            $existencia = $this->filaBloqueada($producto, $tiendaId);

            $saldo = (float) $existencia->stock + $cantidad;

            $existencia->forceFill(['stock' => $saldo])->save();

            $movimiento = new StockMovement([
                'tienda_id'     => $tiendaId,
                'product_id'    => $producto->id,
                'type'          => $this->tipoDe($cantidad, $motivo),
                'reason'        => $motivo,
                'quantity'      => $cantidad,
                'balance_after' => $saldo,
                'unit_cost'     => $costoUnitario ?? (float) $producto->cost,
                'user_id'       => $userId,
                'notes'         => $nota,
            ]);

            if ($origen) {
                $movimiento->source_type = $origen::class;
                $movimiento->source_id   = $origen->getKey();
            }

            $movimiento->save();

            $this->actualizarTotal($producto->id);

            // Se refresca el modelo que recibió quien llamó, para que no se
            // quede con el saldo viejo en memoria.
            $producto->setRelation('existencia', $existencia);

            // La campana de avisos debe reflejar el saldo nuevo.
            AlertService::olvidar();

            return $movimiento;
        });
    }

    /**
     * La fila del producto en este local, bloqueada para escribir.
     *
     * `lockForUpdate` es lo que impide que dos cajeros vendiendo el mismo
     * artículo a la vez se pisen el saldo: el segundo espera unos
     * milisegundos y lee el número que dejó el primero.
     *
     * Si no existe, se crea. Eso pasa cuando llega mercancía a un local
     * que no manejaba ese producto — y es correcto que la compra lo dé de
     * alta ahí sin que nadie tenga que acordarse.
     */
    private function filaBloqueada(Product $producto, int $tiendaId): Existencia
    {
        $existencia = Existencia::where('tienda_id', $tiendaId)
            ->where('product_id', $producto->id)
            ->lockForUpdate()
            ->first();

        if ($existencia) {
            return $existencia;
        }

        Existencia::create([
            'tienda_id'  => $tiendaId,
            'product_id' => $producto->id,
            'stock'      => 0,
            'min_stock'  => 0,
            'activo'     => true,
        ]);

        // Se vuelve a leer con el bloqueo puesto: la fila recién creada
        // tiene que entrar en la misma disciplina que las demás.
        return Existencia::where('tienda_id', $tiendaId)
            ->where('product_id', $producto->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Deja `products.stock_total` igual a la suma de todos los locales.
     *
     * Se recalcula en vez de sumar y restar sobre el valor anterior: si
     * alguna vez quedó descuadrado por lo que sea, el siguiente movimiento
     * lo endereza solo. Ir sumando arrastraría el error para siempre.
     */
    private function actualizarTotal(int $productId): void
    {
        $totales = Existencia::where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(stock), 0) as s, COALESCE(SUM(min_stock), 0) as m')
            ->first();

        Product::whereKey($productId)->update([
            'stock_total'     => (float) ($totales->s ?? 0),
            'min_stock_total' => (float) ($totales->m ?? 0),
        ]);
    }

    /** Salida de bodega (venta, merma). */
    public function descontar(Product $p, float $cantidad, string $motivo, ?Model $origen = null, ?int $userId = null, ?string $nota = null, ?int $tiendaId = null): StockMovement
    {
        return $this->mover($p, -abs($cantidad), $motivo, $origen, $userId, $nota, null, $tiendaId);
    }

    /** Entrada a bodega (compra, devolución). */
    public function ingresar(Product $p, float $cantidad, string $motivo, ?Model $origen = null, ?int $userId = null, ?string $nota = null, ?float $costo = null, ?int $tiendaId = null): StockMovement
    {
        return $this->mover($p, abs($cantidad), $motivo, $origen, $userId, $nota, $costo, $tiendaId);
    }

    /**
     * Deja el saldo en un valor exacto (conteo físico).
     * Registra solo la diferencia, que es lo que interesa auditar.
     */
    public function ajustarA(Product $p, float $saldoReal, ?int $userId = null, ?string $nota = null, ?int $tiendaId = null): ?StockMovement
    {
        $tiendaId ??= Contexto::tiendaId();

        $actual = (float) (Existencia::where('tienda_id', $tiendaId)
            ->where('product_id', $p->id)
            ->value('stock') ?? 0);

        $diferencia = $saldoReal - $actual;

        if (abs($diferencia) < 0.0001) {
            return null;   // no hubo diferencia, no se ensucia el kardex
        }

        return $this->mover($p, $diferencia, 'conteo', null, $userId, $nota, null, $tiendaId);
    }

    /**
     * Traslado entre locales del mismo negocio.
     *
     * Son dos movimientos, no uno: sale de allá y entra acá, cada uno con
     * su renglón en el kardex del local que le toca. Van en la misma
     * transacción para que sea imposible que la mercancía salga de un sitio
     * y no llegue al otro — que es exactamente el error que produce la foto
     * de WhatsApp con la que se hacen los traslados sin sistema.
     *
     * La pantalla que lo maneja llega en la fase 6; el servicio se escribe
     * aquí porque es donde vive la disciplina del inventario.
     */
    public function trasladar(Product $p, float $cantidad, int $desde, int $hacia, ?int $userId = null, ?string $nota = null): \App\Models\Traslado
    {
        if ($desde === $hacia) {
            throw new \InvalidArgumentException('El origen y el destino son el mismo local.');
        }

        $cantidad = abs($cantidad);

        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('No se puede trasladar una cantidad de cero.');
        }

        return DB::transaction(function () use ($p, $cantidad, $desde, $hacia, $userId, $nota) {
            /**
             * Primero el documento, después los movimientos.
             *
             * Así los dos movimientos pueden apuntar al traslado desde que
             * nacen, y en el kardex se ve de dónde vino cada uno. Al revés
             * quedarían huérfanos el rato que dura la transacción — y si
             * algo falla en la mitad, huérfanos para siempre.
             */
            $traslado = \App\Models\Traslado::create([
                'numero'         => ConsecutivoService::siguiente('traslados', $desde),
                'product_id'     => $p->id,
                'desde_tienda_id' => $desde,
                'hacia_tienda_id' => $hacia,
                'cantidad'       => $cantidad,
                'costo_unitario' => (float) $p->cost,
                'user_id'        => $userId,
                'notas'          => $nota,
            ]);

            $this->descontar($p, $cantidad, 'traslado_salida', $traslado, $userId, $nota, $desde);
            $this->ingresar($p, $cantidad, 'traslado_entrada', $traslado, $userId, $nota, (float) $p->cost, $hacia);

            return $traslado;
        });
    }

    private function tipoDe(float $cantidad, string $motivo): string
    {
        if ($motivo === 'conteo' || $motivo === 'ajuste_manual') {
            return StockMovement::AJUSTE;
        }

        return $cantidad >= 0 ? StockMovement::ENTRADA : StockMovement::SALIDA;
    }
}
