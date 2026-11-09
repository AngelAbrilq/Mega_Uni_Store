<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Http\Requests\MotivoRequest;
use App\Http\Requests\Nomina\RegistrarHorasRequest;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Services\Nomina\NovedadesService;
use App\Services\Nomina\ReporteNominaService;
use App\Support\Exportes\XlsxWriter;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

/** NR-NOM-005 · Horas extra: registro, aprobación y reporte del período. */
class HorasExtraController extends Controller implements HasMiddleware
{
    public function __construct(private NovedadesService $novedades, private ReporteNominaService $reportes) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:nomina.novedades', only: ['index', 'store']),
            new Middleware('permission:nomina.aprobar_novedades', only: ['aprobar', 'rechazar']),
            new Middleware('permission:nomina.ver', only: ['excel']),
        ];
    }

    public function index(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $estado = array_key_exists((string) $request->query('estado'), OvertimeEntry::ESTADOS) ? $request->query('estado') : null;

        return view('nomina.horas.index', [
            'desde'      => $desde,
            'hasta'      => $hasta,
            'estado'     => $estado,
            'pendientes' => OvertimeEntry::deTienda(null)->where('status', 'pendiente')->with('employee', 'registeredBy:id,name,last_name')->orderBy('date')->get(),
            'grupos'     => $this->reportes->horasExtra($desde, $hasta, $estado),
            'empleados'  => Employee::activos()->deTienda(null)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code']),
            'tipos'      => collect(ReglasLaborales::TIPOS_HORA)->map(fn ($t, $k) => ['nombre' => $t, 'factor' => ReglasLaborales::factor($k)]),
            'horasMes'   => ReglasLaborales::horasMes(),
        ]);
    }

    public function store(RegistrarHorasRequest $request)
    {
        $h = $this->novedades->registrarHoras(Employee::findOrFail($request->input('employee_id')), $request->validated(), $request->user());

        return back()->with('success', "Registradas {$h->hours} h ({$h->tipoTexto()}) por $" . number_format((float) $h->amount, 0, ',', '.') . '. Quedan pendientes de aprobación.');
    }

    public function aprobar(Request $request, OvertimeEntry $horas)
    {
        $this->novedades->aprobarHoras($horas, $request->user());

        return back()->with('success', 'Horas extra aprobadas: entran en la próxima liquidación.');
    }

    public function rechazar(MotivoRequest $request, OvertimeEntry $horas)
    {
        $this->novedades->rechazarHoras($horas, $request->user(), $request->input('motivo'));

        return back()->with('success', 'Horas extra rechazadas; se avisó a quien las registró.');
    }

    public function excel(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $estado = array_key_exists((string) $request->query('estado'), OvertimeEntry::ESTADOS) ? $request->query('estado') : null;

        return response((new XlsxWriter())->render($this->reportes->tablaHorasExtra($desde, $hasta, $estado)), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="horas_extra_' . $desde->format('Ymd') . '_' . $hasta->format('Ymd') . '.xlsx"',
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function rango(Request $request): array
    {
        try {
            $desde = Carbon::parse($request->query('desde', today()->startOfMonth()->toDateString()));
            $hasta = Carbon::parse($request->query('hasta', today()->endOfMonth()->toDateString()));
        } catch (\Throwable) {
            $desde = today()->startOfMonth();
            $hasta = today()->endOfMonth();
        }

        return $hasta->lt($desde) ? [$hasta, $desde] : [$desde, $hasta];
    }
}
