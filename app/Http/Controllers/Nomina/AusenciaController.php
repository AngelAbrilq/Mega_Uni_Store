<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Http\Requests\MotivoRequest;
use App\Http\Requests\Nomina\RegistrarAusenciaRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Nomina\NovedadesService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

/** NR-NOM-006 · Vacaciones y ausencias con saldo y calendario. */
class AusenciaController extends Controller implements HasMiddleware
{
    public function __construct(private NovedadesService $novedades) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:nomina.novedades', only: ['index', 'store', 'soporte']),
            new Middleware('permission:nomina.aprobar_novedades', only: ['aprobar', 'rechazar']),
        ];
    }

    public function index(Request $request)
    {
        $tipo = array_key_exists((string) $request->query('tipo'), LeaveRequest::TIPOS) ? $request->query('tipo') : null;
        $empleados = Employee::activos()->deTienda(null)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code', 'hire_date', 'created_at']);

        return view('nomina.ausencias.index', [
            'tipo'       => $tipo,
            'pendientes' => LeaveRequest::deTienda(null)->where('status', 'pendiente')->with('employee', 'registeredBy:id,name,last_name')->orderBy('start_date')->get(),
            'novedades'  => LeaveRequest::deTienda(null)->when($tipo, fn ($q) => $q->where('type', $tipo))
                ->with('employee', 'reviewedBy:id,name,last_name')->orderByDesc('start_date')->paginate(20)->withQueryString(),
            'proximas'   => LeaveRequest::deTienda(null)->where('status', 'aprobada')->whereDate('end_date', '>=', today())
                ->whereDate('start_date', '<=', today()->addDays(45))->with('employee')->orderBy('start_date')->get(),
            'empleados'  => $empleados,
            'saldos'     => $empleados->mapWithKeys(fn ($e) => [$e->id => $this->novedades->saldoVacaciones($e)]),
        ]);
    }

    public function store(RegistrarAusenciaRequest $request)
    {
        $n = $this->novedades->registrarAusencia(Employee::findOrFail($request->input('employee_id')), $request->validated(),
            $request->user(), $request->file('document'));

        return back()->with('success', $n->tipoTexto() . " registrada ({$n->days} días" . ($n->type === 'vacaciones' ? ' hábiles' : '') . ')'
            . ($n->status === LeaveRequest::PENDIENTE ? ': queda pendiente de aprobación.' : '.'));
    }

    public function aprobar(Request $request, LeaveRequest $ausencia)
    {
        $this->novedades->aprobarAusencia($ausencia, $request->user());

        return back()->with('success', $ausencia->tipoTexto() . ' aprobada.');
    }

    public function rechazar(MotivoRequest $request, LeaveRequest $ausencia)
    {
        $this->novedades->rechazarAusencia($ausencia, $request->user(), $request->input('motivo'));

        return back()->with('success', 'Solicitud rechazada; se avisó a quien la registró.');
    }

    public function soporte(LeaveRequest $ausencia)
    {
        abort_unless($ausencia->document_path && Storage::disk('local')->exists($ausencia->document_path), 404);

        return Storage::disk('local')->response($ausencia->document_path);
    }
}
