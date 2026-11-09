<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Position;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RRHH operativo del local (RN-44): empleados, cargos, áreas y horarios.
 *
 *   · Un solo cargo activo: asignar otro cierra el anterior (queda la historia).
 *   · Una sola área principal: marcar otra apaga la anterior; las demás son secundarias.
 *   · Un solo horario vigente: asignar otro cierra el anterior el día antes.
 *   · Desactivar a un empleado (retiro) lo saca de los activos, cierra su cargo y
 *     su horario, le quita la responsabilidad de áreas y lo borra con SoftDeletes.
 *   · Un área con empleados no se elimina; un horario o un turno que alguien
 *     tiene hoy tampoco.
 */
class RrhhService
{
    /** @param array<string, mixed> $datos */
    public function registrar(array $datos): Employee
    {
        return DB::transaction(function () use ($datos) {
            $empleado = Employee::create(collect($datos)->only((new Employee)->getFillable())->all());
            $desde    = Carbon::parse($datos['hire_date']);

            if (! empty($datos['position_id'])) {
                $this->asignarCargo($empleado, Position::findOrFail($datos['position_id']), $desde);
            }

            if (! empty($datos['department_id'])) {
                $this->asignarArea($empleado, Department::findOrFail($datos['department_id']), true, $desde);
            }

            if (! empty($datos['work_schedule_id'])) {
                $this->asignarHorario($empleado, WorkSchedule::findOrFail($datos['work_schedule_id']), $desde);
            }

            return $empleado;
        });
    }

    /** AT-CGO-001 · Escenario 3: el cargo anterior queda inactivo con fecha de fin. */
    public function asignarCargo(Employee $empleado, Position $cargo, ?Carbon $desde = null): void
    {
        $desde ??= today();

        DB::transaction(function () use ($empleado, $cargo, $desde) {
            $actual = DB::table('employee_position')->where('employee_id', $empleado->id)->where('is_active', true)->first();

            if ($actual && (int) $actual->position_id === $cargo->id) {
                return; // ya lo tiene
            }

            DB::table('employee_position')->where('employee_id', $empleado->id)->where('is_active', true)
                ->update(['is_active' => false, 'end_date' => $desde->copy()->subDay()->max(Carbon::parse($actual->start_date ?? $desde))->toDateString(), 'updated_at' => now()]);

            $empleado->positions()->attach($cargo->id, ['start_date' => $desde->toDateString(), 'is_active' => true]);
        });
    }

    /** TBL-EHA-001 · Una principal; varias secundarias. */
    public function asignarArea(Employee $empleado, Department $area, bool $principal, ?Carbon $desde = null): void
    {
        DB::transaction(function () use ($empleado, $area, $principal, $desde) {
            if ($principal) {
                DB::table('department_employee')->where('employee_id', $empleado->id)->update(['is_primary' => false]);
            }

            $existe = DB::table('department_employee')->where('employee_id', $empleado->id)->where('department_id', $area->id);

            if ($existe->exists()) {
                $existe->update(['is_primary' => $principal, 'updated_at' => now()]);
            } else {
                // La primera área de alguien es su principal aunque no se marque.
                $primera = ! DB::table('department_employee')->where('employee_id', $empleado->id)->exists();
                $empleado->departments()->attach($area->id, [
                    'assigned_at' => ($desde ?? today())->toDateString(),
                    'is_primary'  => $principal || $primera,
                ]);
            }
        });
    }

    public function quitarArea(Employee $empleado, Department $area): void
    {
        DB::transaction(function () use ($empleado, $area) {
            $eraPrincipal = (bool) DB::table('department_employee')
                ->where('employee_id', $empleado->id)->where('department_id', $area->id)->value('is_primary');

            $empleado->departments()->detach($area->id);

            // Si se fue la principal, la más antigua de las que quedan pasa a serlo.
            if ($eraPrincipal) {
                $siguiente = DB::table('department_employee')->where('employee_id', $empleado->id)->orderBy('assigned_at')->value('id');
                if ($siguiente) {
                    DB::table('department_employee')->where('id', $siguiente)->update(['is_primary' => true]);
                }
            }
        });
    }

    /** AT-HOR-001 · Escenario 3: el horario anterior termina el día antes. */
    public function asignarHorario(Employee $empleado, WorkSchedule $horario, ?Carbon $desde = null): EmployeeSchedule
    {
        $desde ??= today();

        return DB::transaction(function () use ($empleado, $horario, $desde) {
            EmployeeSchedule::query()->where('employee_id', $empleado->id)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $desde->toDateString()))
                ->get()
                ->each(function (EmployeeSchedule $a) use ($desde) {
                    $fin = $desde->copy()->subDay();
                    // Una asignación que empezaba el mismo día (o después) se reemplaza.
                    $fin->lt($a->start_date) ? $a->delete() : $a->update(['end_date' => $fin->toDateString()]);
                });

            return EmployeeSchedule::create([
                'employee_id'      => $empleado->id,
                'work_schedule_id' => $horario->id,
                'start_date'       => $desde->toDateString(),
            ]);
        });
    }

    /** AT-EMP-001 · Escenario 5 (retiro). */
    public function desactivar(Employee $empleado, Carbon $fecha, ?string $motivo): void
    {
        DB::transaction(function () use ($empleado, $fecha, $motivo) {
            DB::table('employee_position')->where('employee_id', $empleado->id)->where('is_active', true)
                ->update(['is_active' => false, 'end_date' => $fecha->toDateString(), 'updated_at' => now()]);

            EmployeeSchedule::query()->where('employee_id', $empleado->id)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', $fecha->toDateString()))
                ->update(['end_date' => $fecha->toDateString(), 'updated_at' => now()]);

            Department::query()->where('manager_id', $empleado->id)->update(['manager_id' => null]);

            $empleado->forceFill([
                'status'             => Employee::INACTIVO,
                'termination_date'   => $fecha->toDateString(),
                'termination_reason' => $motivo,
            ])->save();

            $empleado->delete();
        });
    }

    /** AT-ARE-001 · Escenario 3. */
    public function eliminarArea(Department $area): void
    {
        if ($area->employees()->exists()) {
            throw ValidationException::withMessages(['area' => 'No se puede eliminar el área porque tiene empleados asignados.']);
        }

        $area->delete();
    }

    /** Un cargo con historia no se borra: se desactiva. Devuelve true si se borró. */
    public function eliminarCargo(Position $cargo): bool
    {
        if (DB::table('employee_position')->where('position_id', $cargo->id)->exists()) {
            $cargo->update(['is_active' => false]);

            return false;
        }

        $cargo->delete();

        return true;
    }

    /** TBL-TUR-001 · Franja nueva: fin después del inicio y sin cruzarse con otra del mismo día. */
    public function agregarTurno(WorkSchedule $horario, int $dia, string $inicio, string $fin): WorkShift
    {
        if ($fin <= $inicio) {
            throw ValidationException::withMessages(['end_time' => 'La hora de salida debe ser después de la de entrada.']);
        }

        $choca = $horario->shifts()->where('weekday', $dia)
            ->where('start_time', '<', $fin)->where('end_time', '>', $inicio)->first();

        if ($choca) {
            throw ValidationException::withMessages([
                'start_time' => 'Se cruza con la franja de ' . WorkSchedule::DIAS[$dia] . ' ' . $choca->rango . '.',
            ]);
        }

        return $horario->shifts()->create(['weekday' => $dia, 'start_time' => $inicio, 'end_time' => $fin]);
    }

    /** TBL-TUR-001 · Escenario 4: no se quita una franja de un horario que alguien tiene hoy. */
    public function eliminarTurno(WorkShift $turno): void
    {
        $this->exigirSinEmpleados($turno->schedule, 'quitar esta franja');
        $turno->delete();
    }

    public function eliminarHorario(WorkSchedule $horario): void
    {
        $this->exigirSinEmpleados($horario, 'eliminar este horario');

        if ($horario->assignments()->exists()) {
            $horario->update(['is_active' => false]); // queda en la historia de quienes lo tuvieron

            return;
        }

        $horario->delete();
    }

    private function exigirSinEmpleados(WorkSchedule $horario, string $accion): void
    {
        $n = $horario->asignacionesVigentes()->count();

        if ($n > 0) {
            throw ValidationException::withMessages([
                'horario' => "No se puede {$accion}: {$n} " . ($n === 1 ? 'empleado lo tiene' : 'empleados lo tienen') . ' asignado hoy.',
            ]);
        }
    }
}
