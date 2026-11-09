<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rrhh\AsignarEmpleadoRequest;
use App\Http\Requests\Rrhh\GuardarEmpleadoRequest;
use App\Http\Requests\MotivoRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\RrhhService;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

/**
 * AT-EMP-001 · Empleados del local activo (RN-44). Cargo, áreas y horario
 * se asignan desde la ficha (AT-CGO-001 · TBL-EHA-001 · AT-HOR-001).
 */
class EmployeeController extends Controller implements HasMiddleware
{
    public function __construct(private RrhhService $rrhh)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:empleados.ver', only: ['index', 'show']),
            new Middleware('permission:empleados.crear', only: ['create', 'store']),
            new Middleware('permission:empleados.editar', only: ['edit', 'update', 'asignar', 'quitarArea']),
            new Middleware('permission:empleados.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $estado = $request->query('estado') === 'inactivos' ? 'inactivos' : 'activos';
        $area   = (int) $request->query('area', 0);

        $empleados = Employee::query()
            ->when($estado === 'activos', fn ($w) => $w->activos(), fn ($w) => $w->onlyTrashed())
            ->search($q)
            ->when($area, fn ($w) => $w->whereHas('departments', fn ($d) => $d->whereKey($area)))
            ->with(['positions', 'departments', 'user:id,name,email'])
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate(20)->withQueryString();

        return view('employees.index', [
            'empleados' => $empleados,
            'q'         => $q,
            'estado'    => $estado,
            'area'      => $area,
            'areas'     => Department::orderBy('name')->pluck('name', 'id'),
            'conteo'    => ['activos' => Employee::activos()->count(), 'inactivos' => Employee::onlyTrashed()->count()],
        ]);
    }

    public function create()
    {
        return view('employees.create', $this->listas() + ['empleado' => null, 'sugerido' => $this->siguienteCodigo()]);
    }

    public function store(GuardarEmpleadoRequest $request)
    {
        $empleado = $this->rrhh->registrar($request->validated());

        return redirect()->route('employees.show', $empleado)
            ->with('success', "Empleado {$empleado->nombre_completo} ({$empleado->code}) registrado.");
    }

    public function show(Employee $employee)
    {
        $employee->load(['positions', 'departments', 'scheduleAssignments.schedule.shifts', 'user:id,name,last_name,email']);

        return view('employees.show', $this->listas() + ['empleado' => $employee]);
    }

    public function edit(Employee $employee)
    {
        return view('employees.edit', $this->listas() + ['empleado' => $employee, 'sugerido' => null]);
    }

    public function update(GuardarEmpleadoRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return redirect()->route('employees.show', $employee)->with('success', 'Datos del empleado actualizados.');
    }

    /** AT-EMP-001 · Escenario 5: retiro (estado inactivo + borrado lógico). */
    public function destroy(MotivoRequest $request, Employee $employee)
    {
        $fecha = Carbon::parse($request->input('fecha', today()->toDateString()));

        if ($fecha->lt($employee->hire_date) || $fecha->isFuture()) {
            return back()->withErrors(['fecha' => 'La fecha de retiro va entre el ingreso y hoy.']);
        }

        $this->rrhh->desactivar($employee, $fecha, $request->validated('motivo'));

        return redirect()->route('employees.index')->with('success', "{$employee->nombre_completo} quedó retirado. Su historia se conserva.");
    }

    public function asignar(AsignarEmpleadoRequest $request, Employee $employee)
    {
        $datos = $request->validated();
        $desde = isset($datos['desde']) ? Carbon::parse($datos['desde']) : today();

        $mensaje = match ($datos['tipo']) {
            'cargo' => tap('Cargo asignado.', fn () => $this->rrhh->asignarCargo($employee, Position::findOrFail($datos['position_id']), $desde)),
            'area'  => tap('Área asignada.', fn () => $this->rrhh->asignarArea($employee, Department::findOrFail($datos['department_id']), (bool) ($datos['is_primary'] ?? false), $desde)),
            default => tap('Horario asignado.', fn () => $this->rrhh->asignarHorario($employee, WorkSchedule::findOrFail($datos['work_schedule_id']), $desde)),
        };

        return redirect()->route('employees.show', $employee)->with('success', $mensaje);
    }

    public function quitarArea(Employee $employee, Department $department)
    {
        $this->rrhh->quitarArea($employee, $department);

        return back()->with('success', "Ya no figura en {$department->name}.");
    }

    /** @return array<string, mixed> */
    private function listas(): array
    {
        return [
            'cargos'   => Position::where('is_active', true)->orderBy('level')->orderBy('name')->get(),
            'areas'    => Department::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'horarios' => WorkSchedule::where('is_active', true)->with('shifts')->orderBy('name')->get(),
            'usuarios' => User::query()->deLaEmpresa(Contexto::empresaId())->activos()->orderBy('name')->get(['id', 'name', 'last_name', 'email']),
        ];
    }

    /** EMP-001, EMP-002…: el siguiente libre del local. */
    private function siguienteCodigo(): string
    {
        $n = Employee::withTrashed()->count() + 1;

        while (Employee::withTrashed()->where('code', sprintf('EMP-%03d', $n))->exists()) {
            $n++;
        }

        return sprintf('EMP-%03d', $n);
    }
}
