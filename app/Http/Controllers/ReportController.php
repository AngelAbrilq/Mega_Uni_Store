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
use App\Services\ReporteService;

/**
 * Informes del negocio. Todas las consultas se hacen en SQL agregado,
 * no trayendo filas a PHP: con miles de ventas la diferencia se nota.
 */
class ReportController extends Controller implements HasMiddleware
{
    public function __construct(private ReporteService $reportes) {}

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
            'porDia'      => $this->reportes->porDia($desde, $hasta),
            'topProductos'=> $this->reportes->topProductos($desde, $hasta),
            'porCategoria'=> $this->reportes->porCategoria($desde, $hasta),
            'porMedio'    => $this->reportes->porMedio($desde, $hasta),
            'porVendedor' => $this->reportes->porVendedor($desde, $hasta),
            'inventario'  => [
                'valor'    => $this->reportes->valorInventario('cost'),
                'venta'    => $this->reportes->valorInventario('price'),
                'unidades' => (float) Existencia::deTienda(Contexto::tiendaId())->sum('stock'),
                'bajos'    => Product::lowStock()->count(),
            ],
            // H-F02 · ventana de Configuración › Inventario › «Días para calcular la rotación».
            'diasRotacion' => $diasRotacion = max(7, (int) \App\Models\Setting::obtener('inventario.dias_rotacion', '30')),
            'sinRotacion' => $this->reportes->sinRotacion($hasta->copy()->subDays($diasRotacion)->startOfDay(), $hasta),

            /**
             * El comparativo entre locales.
             *
             * Sale vacío cuando el negocio tiene un solo local — y ahí la
             * vista no dibuja nada. Comparar un local contra sí mismo no
             * responde ninguna pregunta, y una tabla de una fila solo
             * ocupa pantalla.
             */
            'porLocal'    => $this->reportes->porLocal($desde, $hasta),
        ]);
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

}
