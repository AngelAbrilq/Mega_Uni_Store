<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rrhh\AgregarTurnoRequest;
use App\Http\Requests\Rrhh\GuardarHorarioRequest;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Services\RrhhService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** AT-HOR-001 · TBL-TUR-001 · Horarios del local y sus franjas semanales (RN-44). */
class WorkScheduleController extends Controller implements HasMiddleware
{
    public function __construct(private RrhhService $rrhh)
    {
    }

    public static function middleware(): array
    {
        return [new Middleware('permission:rrhh.configurar')];
    }

    public function index()
    {
        return view('work_schedules.index', [
            'horarios' => WorkSchedule::with('shifts')->withCount('asignacionesVigentes')
                ->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('work_schedules.create', ['horario' => null]);
    }

    public function store(GuardarHorarioRequest $request)
    {
        $horario = WorkSchedule::create($request->validated());

        return redirect()->route('work_schedules.show', $horario)->with('success', "Horario «{$horario->name}» creado. Agrégale sus franjas.");
    }

    /** TBL-TUR-001 · Escenario 3: franjas ordenadas por día. */
    public function show(WorkSchedule $workSchedule)
    {
        $workSchedule->load(['shifts', 'asignacionesVigentes.employee']);

        return view('work_schedules.show', ['horario' => $workSchedule]);
    }

    public function edit(WorkSchedule $workSchedule)
    {
        return view('work_schedules.edit', ['horario' => $workSchedule]);
    }

    public function update(GuardarHorarioRequest $request, WorkSchedule $workSchedule)
    {
        $workSchedule->update($request->validated());

        return redirect()->route('work_schedules.show', $workSchedule)->with('success', 'Horario actualizado.');
    }

    public function destroy(WorkSchedule $workSchedule)
    {
        try {
            $this->rrhh->eliminarHorario($workSchedule);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('work_schedules.index')->with('success', "Horario «{$workSchedule->name}» retirado.");
    }

    /** Una franja para uno o varios días a la vez (todo o nada). */
    public function agregarTurno(AgregarTurnoRequest $request, WorkSchedule $workSchedule)
    {
        $d = $request->validated();

        try {
            DB::transaction(function () use ($d, $workSchedule) {
                foreach (array_unique($d['weekdays']) as $dia) {
                    $this->rrhh->agregarTurno($workSchedule, (int) $dia, $d['start_time'], $d['end_time']);
                }
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Franja agregada.');
    }

    public function quitarTurno(WorkSchedule $workSchedule, WorkShift $shift)
    {
        abort_unless($shift->work_schedule_id === $workSchedule->id, 404);

        try {
            $this->rrhh->eliminarTurno($shift);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Franja quitada.');
    }
}
