<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Http\Requests\MotivoRequest;
use App\Http\Requests\Nomina\GuardarContratoRequest;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Services\Nomina\ContratoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

/** NR-NOM-001 · Contratos laborales (el historial vive en la ficha del empleado). */
class ContratoController extends Controller implements HasMiddleware
{
    public function __construct(private ContratoService $contratos) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:nomina.ver', only: ['index']),
            new Middleware('permission:nomina.gestionar', except: ['index']),
        ];
    }

    public function index(Request $request)
    {
        $filtro = in_array($request->query('ver'), ['activos', 'por_vencer', 'historicos'], true) ? $request->query('ver') : 'activos';

        $contratos = EmploymentContract::query()->deTienda(null)
            ->with(['employee:id,first_name,last_name,code,deleted_at', 'position:id,name'])
            ->when($filtro === 'activos', fn ($q) => $q->activos())
            ->when($filtro === 'por_vencer', fn ($q) => $q->activos()->whereNotNull('end_date')->whereDate('end_date', '<=', today()->addDays(30)))
            ->when($filtro === 'historicos', fn ($q) => $q->where('status', '!=', EmploymentContract::ACTIVO))
            ->orderBy('end_date')->orderByDesc('start_date')
            ->paginate(25)->withQueryString();

        $base = EmploymentContract::query()->deTienda(null);

        return view('nomina.contratos.index', [
            'contratos' => $contratos,
            'filtro'    => $filtro,
            'conteo'    => [
                'activos'    => (clone $base)->activos()->count(),
                'por_vencer' => (clone $base)->activos()->whereNotNull('end_date')->whereDate('end_date', '<=', today()->addDays(30))->count(),
                'historicos' => (clone $base)->where('status', '!=', EmploymentContract::ACTIVO)->count(),
            ],
            'sinContrato' => Employee::activos()->deTienda(null)->whereDoesntHave('contracts', fn ($q) => $q->activos())->count(),
        ]);
    }

    public function store(GuardarContratoRequest $request, Employee $employee)
    {
        $this->contratos->crear($employee, $request->datos());

        return redirect()->to(route('employees.show', $employee) . '#nomina')->with('success', 'Contrato registrado.');
    }

    public function renovar(GuardarContratoRequest $request, EmploymentContract $contrato)
    {
        $this->contratos->renovar($contrato, $request->datos());

        return redirect()->to(route('employees.show', $contrato->employee_id) . '#nomina')
            ->with('success', 'Contrato renovado: el anterior quedó finalizado.');
    }

    public function terminar(MotivoRequest $request, EmploymentContract $contrato)
    {
        $request->validate(['ended_at' => ['required', 'date']]);
        $this->contratos->terminar($contrato, Carbon::parse($request->input('ended_at')), $request->input('motivo'));

        return redirect()->to(route('employees.show', $contrato->employee_id) . '#nomina')->with('success', 'Contrato terminado.');
    }
}
