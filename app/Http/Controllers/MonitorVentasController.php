<?php

namespace App\Http\Controllers;

use App\Services\MonitorVentasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

/**
 * SV-VTA-001 · Monitor de ventas del día (/sales/monitor).
 *
 * «Tiempo real» por sondeo: la pantalla pide /sales/monitor/datos cada
 * 15 s y pinta lo nuevo. Se eligió sondeo y no WebSockets (Reverb) porque
 * no exige un proceso aparte corriendo en el servidor del cliente —que en
 * Laragon nadie arrancaría— y para un supervisor 15 s es «en vivo».
 */
class MonitorVentasController extends Controller implements HasMiddleware
{
    private const ESTADOS = ['todas', 'pagadas', 'anuladas', 'alto'];

    public function __construct(private MonitorVentasService $monitor)
    {
    }

    public static function middleware(): array
    {
        return [new Middleware('permission:reportes.ver')];
    }

    public function index(Request $request)
    {
        $estado = $this->estado($request);
        $hoy    = now();
        $umbral = $this->monitor->umbralAltoValor($hoy);

        return view('sales.monitor', [
            'resumen' => $this->monitor->resumen($hoy),
            'filas'   => $this->monitor->ventas($hoy, $estado)->map(fn ($v) => $this->monitor->fila($v, $umbral)),
            'estado'  => $estado,
            'estados' => ['todas' => 'Todas', 'pagadas' => 'Pagadas', 'anuladas' => 'Anuladas', 'alto' => 'Alto valor'],
        ]);
    }

    public function datos(Request $request): JsonResponse
    {
        $hoy    = now();
        $umbral = $this->monitor->umbralAltoValor($hoy);
        $estado = $this->estado($request);

        return response()->json([
            'resumen' => $this->monitor->resumen($hoy),
            // Lo nuevo desde la última venta que la pantalla ya tiene.
            'nuevas'  => $this->monitor->ventas($hoy, $estado, 50, (int) $request->query('despues', 0))
                ->map(fn ($v) => $this->monitor->fila($v, $umbral))->values(),
            // Las anuladas cambian de estado sin ser nuevas: se mandan los ids del día.
            'anuladas' => $this->monitor->ventas($hoy, 'anuladas', 200)->pluck('id'),
            'hora'     => $hoy->format('H:i:s'),
        ]);
    }

    private function estado(Request $request): string
    {
        $e = (string) $request->query('estado', 'todas');

        return in_array($e, self::ESTADOS, true) ? $e : 'todas';
    }
}
