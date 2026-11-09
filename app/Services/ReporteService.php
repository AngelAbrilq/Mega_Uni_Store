<?php

namespace App\Services;

use App\Support\Contexto;
use App\Models\Existencia;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de los informes del negocio.
 *
 * Salieron de ReportController para que la pantalla de reportes y la
 * exportación a PDF/Excel (RP-EXP-001) usen exactamente las mismas cifras:
 * si un informe se corrige, se corrige en los dos lados a la vez.
 * Todas se hacen en SQL agregado y respetan el negocio y el local activos.
 */
class ReporteService
{
    /**
     * Cada local, uno al lado del otro.
     *
     * ── Por qué esta consulta NO usa el filtro de la tienda activa ──
     *
     * Porque la pregunta que responde es la del dueño, no la del cajero:
     * «¿cómo va el negocio completo, y cuál sede está jalando?». El filtro
     * global ya acota a los locales de ESTA empresa —que es el aislamiento
     * que importa—; dentro de eso, se ven todos.
     *
     * ── Por qué el inventario se cuenta aparte ──
     *
     * Porque una sede puede no haber vendido nada en el rango y aun así
     * tener medio millón parado en bodega. Si el inventario saliera del
     * mismo JOIN que las ventas, esa sede desaparecería del informe justo
     * cuando es la que hay que mirar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function porLocal(Carbon $desde, Carbon $hasta): array
    {
        $tiendas = \App\Models\Tienda::query()
            ->whereIn('id', Contexto::tiendaIds() ?? [])
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        if ($tiendas->count() < 2) {
            return [];
        }

        /* ── Ventas del rango, agrupadas ── */
        $ventas = Sale::query()
            ->pagadas()
            ->entre($desde, $hasta)
            ->selectRaw('tienda_id,
                         COUNT(*) as documentos,
                         COALESCE(SUM(total), 0) as ingresos,
                         COALESCE(SUM(cost_total), 0) as costo,
                         COALESCE(SUM(profit_total), 0) as utilidad')
            ->groupBy('tienda_id')
            ->get()
            ->keyBy('tienda_id');

        /* ── Lo que hay parado en cada bodega ── */
        $inventario = Existencia::query()
            ->join('products', 'products.id', '=', 'existencias.product_id')
            ->selectRaw('existencias.tienda_id,
                         COALESCE(SUM(products.cost * existencias.stock), 0) as valor,
                         COALESCE(SUM(existencias.stock), 0) as unidades')
            ->groupBy('existencias.tienda_id')
            ->get()
            ->keyBy('tienda_id');

        $filas  = [];
        $totalIngresos = 0.0;

        foreach ($tiendas as $tienda) {
            $v = $ventas->get($tienda->id);

            $totalIngresos += (float) ($v->ingresos ?? 0);
        }

        foreach ($tiendas as $tienda) {
            $v = $ventas->get($tienda->id);
            $i = $inventario->get($tienda->id);

            $ingresos   = (float) ($v->ingresos ?? 0);
            $documentos = (int) ($v->documentos ?? 0);
            $utilidad   = (float) ($v->utilidad ?? 0);

            $filas[] = [
                'id'         => $tienda->id,
                'nombre'     => $tienda->nombre,
                'codigo'     => $tienda->codigo,
                'documentos' => $documentos,
                'ingresos'   => $ingresos,
                'costo'      => (float) ($v->costo ?? 0),
                'utilidad'   => $utilidad,
                // El ticket promedio se calcula aquí y no en SQL con AVG:
                // con cero ventas, AVG devuelve null y la vista tendría que
                // acordarse de cubrirlo. Aquí sale cero, que es la verdad.
                'ticket'     => $documentos > 0 ? round($ingresos / $documentos, 2) : 0.0,
                'margen'     => $ingresos > 0 ? round($utilidad / $ingresos * 100, 1) : 0.0,
                'inventario' => (float) ($i->valor ?? 0),
                'unidades'   => (float) ($i->unidades ?? 0),
                // Cuánto aporta al total. Es la columna que de verdad se
                // mira: dice cuál sede sostiene el negocio.
                'peso'       => $totalIngresos > 0 ? round($ingresos / $totalIngresos * 100, 1) : 0.0,
            ];
        }

        return $filas;
    }

    /** Cuánta plata hay parada en este local, al costo o al precio. */
    public function valorInventario(string $columna): float
    {
        return (float) Existencia::deTienda(Contexto::tiendaId())
            ->join('products', 'products.id', '=', 'existencias.product_id')
            ->selectRaw("COALESCE(SUM(products.{$columna} * existencias.stock), 0) as t")
            ->value('t');
    }

    /**
     * Ventas día por día. Se rellenan los días sin ventas con cero para
     * que la gráfica no mienta saltándose fechas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function porDia(Carbon $desde, Carbon $hasta): array
    {
        $filas = Sale::query()
            ->pagadas()
            ->entre($desde, $hasta)
            ->selectRaw('DATE(sold_at) as dia, COUNT(*) as n, SUM(total) as total, SUM(profit_total) as utilidad')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $conteos   = Sale::query()->pagadas()->entre($desde, $hasta)
            ->selectRaw('DATE(sold_at) as dia, COUNT(*) as n')->groupBy('dia')->pluck('n', 'dia');
        $utilidades = Sale::query()->pagadas()->entre($desde, $hasta)
            ->selectRaw('DATE(sold_at) as dia, SUM(profit_total) as u')->groupBy('dia')->pluck('u', 'dia');

        $salida = [];
        $cursor = $desde->copy()->startOfDay();
        $limite = $hasta->copy()->startOfDay();

        // Más de 92 días no cabe legible en una gráfica de barras diaria.
        if ($cursor->diffInDays($limite) > 92) {
            $cursor = $limite->copy()->subDays(92);
        }

        while ($cursor->lessThanOrEqualTo($limite)) {
            $k = $cursor->format('Y-m-d');
            $salida[] = [
                'dia'      => $k,
                'etiqueta' => $cursor->locale('es')->isoFormat('D MMM'),
                'corta'    => $cursor->format('d'),
                'total'    => (float) ($filas[$k] ?? 0),
                'ventas'   => (int) ($conteos[$k] ?? 0),
                'utilidad' => (float) ($utilidades[$k] ?? 0),
            ];
            $cursor->addDay();
        }

        return $salida;
    }

    /** @return array<int, array<string, mixed>> */
    public function topProductos(Carbon $desde, Carbon $hasta, int $limite = 12): array
    {
        return SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->pagadas()->entre($desde, $hasta))
            ->selectRaw('name, sku, SUM(quantity) as unidades, SUM(subtotal) as ingresos,
                         SUM(subtotal - (unit_cost * quantity)) as utilidad, COUNT(*) as veces')
            ->groupBy('name', 'sku')
            ->orderByDesc('ingresos')
            ->take($limite)
            ->get()
            ->map(fn ($r) => [
                'nombre'   => $r->name,
                'sku'      => $r->sku,
                'unidades' => (float) $r->unidades,
                'ingresos' => (float) $r->ingresos,
                'utilidad' => (float) $r->utilidad,
                'veces'    => (int) $r->veces,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function porCategoria(Carbon $desde, Carbon $hasta, int $limite = 8): array
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.status', Sale::PAGADA)
            ->whereBetween('sales.sold_at', [$desde, $hasta])
            ->selectRaw('COALESCE(categories.name, ?) as categoria,
                         SUM(sale_items.subtotal) as ingresos,
                         SUM(sale_items.quantity) as unidades', ['Sin categoría'])
            ->groupBy('categoria')
            ->orderByDesc('ingresos')
            ->take($limite)
            ->get()
            ->map(fn ($r) => [
                'nombre'   => $r->categoria,
                'ingresos' => (float) $r->ingresos,
                'unidades' => (float) $r->unidades,
            ])
            ->all();
    }

    /**
     * Cuánto entró por cada medio de pago.
     *
     * Al efectivo se le resta el cambio, por la misma razón que en el
     * arqueo: `sale_payments.amount` guarda lo que el cliente entregó, y
     * parte de eso volvió a su mano. Sin restarlo, este informe decía que
     * por caja entraron treinta mil pesos de una venta de dieciocho mil
     * quinientos — y encima cuadraba mal contra el total facturado, que sí
     * es el correcto. Dos cifras del mismo informe contándose distinto es
     * lo que hace que alguien deje de creerle al sistema entero.
     *
     * El cambio se descuenta del efectivo y no se reparte: cuando alguien
     * paga con tarjeta no hay vuelto que dar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function porMedio(Carbon $desde, Carbon $hasta): array
    {
        $cambio = (float) Sale::query()
            ->pagadas()
            ->entre($desde, $hasta)
            ->sum('change_amount');

        return SalePayment::query()
            ->vigentes() // RN-32: un pago rechazado no es plata que entró
            ->whereHas('sale', fn ($q) => $q->pagadas()->entre($desde, $hasta))
            ->selectRaw('method_name, SUM(amount) as total, COUNT(*) as veces')
            ->groupBy('method_name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($r) use (&$cambio) {
                // Se descuenta del primer medio que se llame «efectivo».
                // Si un negocio tiene dos («Efectivo» y «Efectivo caja 2»),
                // el vuelto se le carga al de más movimiento, que es el
                // orden en que vienen: no es exacto por caja, pero el total
                // del informe sí queda bien.
                if ($cambio > 0 && str_contains(mb_strtolower((string) $r->method_name), 'efectivo')) {
                    $r->total = max(0, (float) $r->total - $cambio);
                    $cambio   = 0;
                }

                return $r;
            })
            ->map(fn ($r) => [
                'nombre' => $r->method_name,
                'total'  => (float) $r->total,
                'veces'  => (int) $r->veces,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function porVendedor(Carbon $desde, Carbon $hasta): array
    {
        return Sale::query()
            ->pagadas()
            ->entre($desde, $hasta)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('users.name as vendedor, COUNT(*) as ventas,
                         SUM(sales.total) as total, SUM(sales.profit_total) as utilidad')
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'nombre'   => $r->vendedor,
                'ventas'   => (int) $r->ventas,
                'total'    => (float) $r->total,
                'utilidad' => (float) $r->utilidad,
            ])
            ->all();
    }

    /**
     * Productos con existencias que no se vendieron en el periodo:
     * plata quieta en la bodega.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sinRotacion(Carbon $desde, Carbon $hasta, int $limite = 10): array
    {
        $vendidos = SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->pagadas()->entre($desde, $hasta))
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        // Lo que está parado EN ESTE LOCAL. Un producto que no rota aquí
        // puede estar rotando muy bien en el otro, y mezclarlos escondería
        // justamente lo que este informe busca mostrar.
        /**
         * Nada de lista de columnas en el `get()`.
         *
         * `stock` dejó de ser columna de `products` cuando la existencia
         * pasó a ser por local, e `inmovil` nunca lo fue: se calcula. Pedir
         * las dos a la base daba «Unknown column». Y como `ordenPorExistencia`
         * se une con `existencias`, cualquier columna sin calificar —`id`,
         * la primera— sale ambigua: por eso van con `products.` delante.
         *
         * El `addSelect` trae la existencia del local en la misma consulta.
         * Sin él, el accessor `stock` preguntaría la suya a la base por cada
         * uno de los diez productos.
         */
        return Product::query()
            ->whereHas('existencia', fn ($e) => $e->where('stock', '>', 0))
            ->whereNotIn('products.id', $vendidos)
            ->ordenPorExistencia('desc')
            ->addSelect('existencias.stock as stock')
            ->take($limite)
            ->get()
            ->map(fn (Product $p) => [
                'id'       => $p->id,
                'nombre'   => $p->name,
                'sku'      => $p->sku,
                'stock'    => (float) $p->stock,
                'inmovil'  => $p->stock_value,
            ])
            ->all();
    }
}
