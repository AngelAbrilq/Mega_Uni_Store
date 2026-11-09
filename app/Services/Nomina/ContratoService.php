<?php

namespace App\Services\Nomina;

use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NR-NOM-001 · Contratos laborales.
 *
 *   · Salario ≥ SMLMV salvo prestación de servicios (no es vínculo laboral)
 *     y aprendizaje (cuota de sostenimiento: mínimo 75 % del SMLMV).
 *   · Término fijo exige fecha de fin posterior al inicio.
 *   · Un solo contrato activo por empleado.
 *   · Renovar finaliza el anterior y crea uno nuevo enlazado (renewed_from_id).
 *   · El salario del contrato activo se copia a employees.base_salary.
 */
class ContratoService
{
    /** @param array<string, mixed> $datos */
    public function crear(Employee $empleado, array $datos): EmploymentContract
    {
        $this->validar($datos);

        return DB::transaction(function () use ($empleado, $datos) {
            if ($empleado->contracts()->where('status', EmploymentContract::ACTIVO)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'type' => 'El empleado ya tiene un contrato activo. Renuévalo o termínalo antes de crear otro.',
                ]);
            }

            return $this->guardar($empleado, $datos);
        });
    }

    /** E4 · El anterior queda «finalizado» y el nuevo arranca donde diga el admin. */
    public function renovar(EmploymentContract $anterior, array $datos): EmploymentContract
    {
        if ($anterior->status !== EmploymentContract::ACTIVO) {
            throw ValidationException::withMessages(['type' => 'Solo se renueva un contrato activo.']);
        }

        $datos += ['type' => $anterior->type, 'position_id' => $anterior->position_id, 'arl_class' => $anterior->arl_class];
        $this->validar($datos);

        if (Carbon::parse($datos['start_date'])->lte($anterior->start_date)) {
            throw ValidationException::withMessages(['start_date' => 'La renovación debe empezar después del contrato anterior.']);
        }

        return DB::transaction(function () use ($anterior, $datos) {
            $anterior->update([
                'status'     => EmploymentContract::FINALIZADO,
                'ended_at'   => Carbon::parse($datos['start_date'])->subDay(),
                'end_reason' => 'Renovado',
            ]);

            return $this->guardar($anterior->employee, $datos + ['renewed_from_id' => $anterior->id]);
        });
    }

    /** Terminación anticipada (renuncia, despido…). */
    public function terminar(EmploymentContract $contrato, Carbon $fecha, string $motivo): void
    {
        if ($contrato->status !== EmploymentContract::ACTIVO) {
            throw ValidationException::withMessages(['ended_at' => 'El contrato ya no está activo.']);
        }

        if ($fecha->lt($contrato->start_date)) {
            throw ValidationException::withMessages(['ended_at' => 'La fecha de terminación es anterior al inicio del contrato.']);
        }

        $contrato->update(['status' => EmploymentContract::TERMINADO, 'ended_at' => $fecha, 'end_reason' => $motivo]);
    }

    /** E2 · E3 */
    public function validar(array $datos): void
    {
        $smlmv  = ReglasLaborales::smlmv();
        $minimo = match ($datos['type'] ?? null) {
            'prestacion'  => 0,
            'aprendizaje' => round($smlmv * 0.75),
            default       => $smlmv,
        };

        $errores = [];

        if ((float) ($datos['base_salary'] ?? 0) < $minimo) {
            $errores['base_salary'] = ($datos['type'] ?? null) === 'aprendizaje'
                ? 'La cuota de sostenimiento no puede ser menor al 75 % del SMLMV ($' . number_format($minimo, 0, ',', '.') . ').'
                : 'El salario no puede ser menor al SMLV ($' . number_format($smlmv, 0, ',', '.') . '). Ajusta el salario.';
        }

        if (in_array($datos['type'] ?? null, ['fijo', 'aprendizaje'], true) && empty($datos['end_date'])) {
            $errores['end_date'] = ($datos['type'] === 'fijo')
                ? 'Los contratos a término fijo requieren fecha de finalización.'
                : 'Los contratos de aprendizaje requieren fecha de finalización.';
        }

        if (! empty($datos['end_date']) && ! empty($datos['start_date'])
            && Carbon::parse($datos['end_date'])->lte(Carbon::parse($datos['start_date']))) {
            $errores['end_date'] = 'La fecha de finalización debe ser posterior a la de inicio.';
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function guardar(Employee $empleado, array $datos): EmploymentContract
    {
        $contrato = EmploymentContract::create([
            'tienda_id'       => $empleado->tienda_id,
            'employee_id'     => $empleado->id,
            'position_id'     => $datos['position_id'] ?? null,
            'type'            => $datos['type'],
            'start_date'      => $datos['start_date'],
            'end_date'        => $datos['type'] === 'indefinido' ? null : ($datos['end_date'] ?? null),
            'base_salary'     => $datos['base_salary'],
            'arl_class'       => $datos['arl_class'] ?? 1,
            'renewed_from_id' => $datos['renewed_from_id'] ?? null,
        ]);

        $empleado->update(['base_salary' => $contrato->base_salary]);

        return $contrato;
    }
}
