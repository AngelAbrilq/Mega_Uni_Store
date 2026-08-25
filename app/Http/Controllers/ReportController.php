<?php

namespace App\Http\Controllers;

use App\Support\Contexto;
use App\Models\Existencia;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Purchase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Informes del negocio. Todas las consultas se hacen en SQL agregado,
 * no trayendo filas a PHP: con miles de ventas la diferencia se nota.
 */
class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:reportes.ver'),
        ];
    }

    public function index(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        $ventas = Sale::query()->pagadas()->entre($desde, $hasta);

        return view('reports.index', [
            'rango'   => ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')],

            // Rótulo del eje horizontal: «agosto de 2026» o «jun 2026 — ago 2026».
            // Se arma aquí y no en la vista, que no tiene por qué parsear fechas.
            'mesRango' => $desde->locale('es')->isoFormat('MMMM YYYY') === $hasta->locale('es')->isoFormat('MMMM YYYY')
                ? $desde->locale('es')->isoFormat('MMMM [de] YYYY')
                : $desde->locale('es')->isoFormat('MMM YYYY') . ' — ' . $hasta->locale('es')->isoFormat('MMM YYYY'),
            'resumen' => [
                'ventas'    => (clone $ventas)->count(),
                'ingresos'  => (float) (clone $ventas)->sum('total'),
                'costo'     => (float) (clone $ventas)->sum('cost_total'),
                'utilidad'  => (float) (clone $ventas)->sum('profit_total'),
                'ticket'    => (float) ((clone $ventas)->avg('total') ?? 0),
                'unidades'  => (float) SaleItem::whereHas('sale', fn ($q) => $q->pagadas()->entre($desde, $hasta))->sum('quantity'),
                'anuladas'  => Sale::where('status', Sale::ANULADA)->entre($desde, $hasta)->count(),
                'compras'   => (float) Purchase::where('status', Purchase::RECIBIDA)
                                    ->whereBetween('received_at', [$desde, $hasta])->sum('total'),
            ],
            'porDia'      => $this->porDia($desde, $hasta),
            'topProductos'=> $this->topProductos($desde, $hasta),
            'porCategoria'=> $this->porCategoria($desde, $hasta),
            'porMedio'    => $this->porMedio($desde, $hasta),
            'porVendedor' => $this->porVendedor($desde, $hasta),
            'inventario'  => [
                'valor'    => $this->valorInventario('cost'),
                'venta'    => $this->valorInventario('price'),
                'unidades' => (float) Existencia::deTienda(Contexto::tiendaId())->sum('stock'),
                'bajos'    => Product::lowStock()->count(),
            ],
            'sinRotacion' => $this->sinRotacion($desde, $hasta),

            /**
             * El comparativo entre locales.
             *
             * Sale vacío cuando el negocio tiene un solo local — y ahí la
             * vista no dibuja nada. Comparar un local contra sí mismo no
             * responde ninguna pregunta, y una tabla de una fila solo
             * ocupa pantalla.
             */
            'porLocal'    => $this->porLocal($desde, $hasta),
        ]);
    }

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
    private function porLocal(Carbon $desde, Carbon $hasta): array
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
    private function valorInventario(string $columna): float
    {
        return (float) Existencia::deTienda(Contexto::tiendaId())
            ->join('products', 'products.id', '=', 'existencias.product_id')
            ->selectRaw("COALESCE(SUM(products.{$columna} * existencias.stock), 0) as t")
            ->value('t');
    }

    /* ───────────────────────── Consultas ───────────────────────── */

    /** @return array{0:Carbon,1:Carbon} */
    private function rango(Request $request): array
    {
        $desde = $request->date('desde')?->startOfDay() ?? now()->startOfMonth();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        return [$desde, $hasta];
    }

    /**
     * Ventas día por día. Se rellenan los días sin ventas con cero para
     * que la gráfica no mienta saltándose fechas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function porDia(Carbon $desde, Carbon $hasta): array
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
    private function topProductos(Carbon $desde, Carbon $hasta): array
    {
        return SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->pagadas()->entre($desde, $hasta))
            ->selectRaw('name, sku, SUM(quantity) as unidades, SUM(subtotal) as ingresos,
                         SUM(subtotal - (unit_cost * quantity)) as utilidad, COUNT(*) as veces')
            ->groupBy('name', 'sku')
            ->orderByDesc('ingresos')
            ->take(12)
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
    private function porCategoria(Carbon $desde, Carbon $hasta): array
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
            ->take(8)
            ->get()
            ->map(fn ($r) => [
                'nombre'   => $r->categoria,
                'ingresos' => (float) $r->ingresos,
                'unidades' => (float) $r->unidades,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function porMedio(Carbon $desde, Carbon $hasta): array
    {
        return SalePayment::query()
            ->whereHas('sale', fn ($q) => $q->pagadas()->entre($desde, $hasta))
            ->selectRaw('method_name, SUM(amount) as total, COUNT(*) as veces')
            ->groupBy('method_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'nombre' => $r->method_name,
                'total'  => (float) $r->total,
                'veces'  => (int) $r->veces,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function porVendedor(Carbon $desde, Carbon $hasta): array
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
    private function sinRotacion(Carbon $desde, Carbon $hasta): array
    {
        $vendidos = SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->pagadas()->entre($desde, $hasta))
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        // Lo que está parado EN ESTE LOCAL. Un producto que no rota aquí
        // puede estar rotando muy bien en el otro, y mezclarlos escondería
        // justamente lo que este informe busca mostrar.
        return Product::query()
            ->whereHas('existencia', fn ($e) => $e->where('stock', '>', 0))
            ->whereNotIn('id', $vendidos)
            ->ordenPorExistencia('desc')
            ->take(10)
            ->get(['id', 'name', 'sku', 'cost'])
            ->map(fn (Product $p) => [
                'id'       => $p->id,
                'nombre'   => $p->name,
                'sku'      => $p->sku,
                'stock'    => (float) $p->stock,
                'inmovil'  => (float) $p->cost * (float) $p->stock,
            ])
            ->all();
    }
}
