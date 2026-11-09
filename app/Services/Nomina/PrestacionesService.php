<?php

namespace App\Services\Nomina;

use App\Events\PrestacionesPagadas;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\SocialBenefit;
use App\Models\User;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NR-NOM-007 · Prestaciones sociales (Código Sustantivo del Trabajo).
 *
 *   base       = salario (+ auxilio de transporte si salario ≤ 2 SMLMV: art. 7 Ley 1 de 1963)
 *   cesantías  = base × días / 360                     (art. 249)
 *   intereses  = cesantías × 12 % × días / 360          (Ley 52 de 1975)
 *   prima      = base × días del semestre / 360         (art. 306)
 *   vacaciones = salario / 30 × días pendientes         (art. 189, solo al terminar)
 *
 * Los aprendices SENA no causan prestaciones. Un período ya calculado para
 * el mismo empleado y tipo no se vuelve a calcular.
 */
class PrestacionesService
{
    public function __construct(private NovedadesService $novedades) {}

    /** E1/E2 · cesantías e intereses del año (o del rango) para todos los empleados. */
    public function cesantias(Carbon $desde, Carbon $hasta, ?Employee $solo = null): Collection
    {
        return DB::transaction(function () use ($desde, $hasta, $solo) {
            $creadas = collect();

            foreach ($this->vinculos($desde, $hasta, $solo) as [$empleado, $contrato, $d, $h, $dias]) {
                if ($this->yaExiste($empleado, 'cesantias', $d, $h)) {
                    continue;
                }

                $base = $this->base((float) $contrato->base_salary);
                $ces  = ReglasLaborales::redondear($base * $dias / 360);

                $creadas->push($this->crear($empleado, 'cesantias', $d, $h, $dias, $base, $ces));
                $creadas->push($this->crear($empleado, 'intereses_cesantias', $d, $h, $dias, $ces,
                    ReglasLaborales::redondear($ces * 0.12 * $dias / 360)));
            }

            return $creadas;
        });
    }

    /** E3 · prima del semestre (enero–junio o julio–diciembre). */
    public function prima(Carbon $desde, Carbon $hasta, ?Employee $solo = null): Collection
    {
        $semestre = $desde->month <= 6 ? 1 : 2;

        if ($desde->year !== $hasta->year || ($hasta->month <= 6 ? 1 : 2) !== $semestre) {
            throw ValidationException::withMessages(['period_end' => 'La prima se liquida por semestre: enero–junio o julio–diciembre.']);
        }

        return DB::transaction(function () use ($desde, $hasta, $solo) {
            $creadas = collect();

            foreach ($this->vinculos($desde, $hasta, $solo) as [$empleado, $contrato, $d, $h, $dias]) {
                if (! $this->yaExiste($empleado, 'prima', $d, $h)) {
                    $base = $this->base((float) $contrato->base_salary);
                    $creadas->push($this->crear($empleado, 'prima', $d, $h, $dias, $base, ReglasLaborales::redondear($base * $dias / 360)));
                }
            }

            return $creadas;
        });
    }

    /**
     * E4 · Liquidación final al terminar el vínculo: cesantías e intereses del
     * año en curso, prima del semestre y vacaciones pendientes en dinero.
     */
    public function liquidacionFinal(Employee $empleado, Carbon $fecha): Collection
    {
        $inicioAnio     = $fecha->copy()->startOfYear();
        $inicioSemestre = $fecha->month <= 6 ? $inicioAnio->copy() : $fecha->copy()->month(7)->startOfMonth();

        return DB::transaction(function () use ($empleado, $fecha, $inicioAnio, $inicioSemestre) {
            $todas = $this->cesantias($inicioAnio, $fecha, $empleado)->merge($this->prima($inicioSemestre, $fecha, $empleado));

            $pendientes = $this->novedades->saldoVacaciones($empleado, $fecha);
            $contrato   = $empleado->contracts()->where('type', '!=', 'prestacion')->first();

            if ($pendientes > 0 && $contrato) {
                $salario = (float) $contrato->base_salary;
                $todas->push($this->crear($empleado, 'vacaciones_dinero', $fecha, $fecha, $pendientes, $salario,
                    ReglasLaborales::redondear($salario / 30 * $pendientes)));
            }

            return $todas;
        });
    }

    /** E5 · Pagar: quedan en «pagada» con fecha y emiten el evento para contabilidad. */
    public function pagar(Collection $prestaciones, User $quien): int
    {
        $pendientes = $prestaciones->where('status', SocialBenefit::CALCULADA);

        if ($pendientes->isEmpty()) {
            throw ValidationException::withMessages(['ids' => 'No hay prestaciones calculadas por pagar en la selección.']);
        }

        DB::transaction(fn () => $pendientes->each->update([
            'status' => SocialBenefit::PAGADA, 'paid_by' => $quien->id, 'paid_at' => now(),
        ]));

        PrestacionesPagadas::dispatch($pendientes->values());

        return $pendientes->count();
    }

    /** Solo lo calculado se puede borrar (para recalcular tras un cambio de salario). */
    public function eliminar(SocialBenefit $p): void
    {
        if ($p->status !== SocialBenefit::CALCULADA) {
            throw ValidationException::withMessages(['prestacion' => 'Una prestación pagada no se elimina.']);
        }

        $p->delete();
    }

    /* ─────────────── internos ─────────────── */

    /** @return array<int, array{0: Employee, 1: EmploymentContract, 2: Carbon, 3: Carbon, 4: int}> */
    private function vinculos(Carbon $desde, Carbon $hasta, ?Employee $solo): array
    {
        if ($hasta->lt($desde)) {
            throw ValidationException::withMessages(['period_end' => 'El fin del período es anterior al inicio.']);
        }

        $contratos = EmploymentContract::query()
            ->whereNotIn('type', ['prestacion', 'aprendizaje'])
            ->whereDate('start_date', '<=', $hasta)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $desde))
            ->when($solo, fn ($q) => $q->where('employee_id', $solo->id), fn ($q) => $q->deTienda(null))
            ->with(['employee' => fn ($q) => $q->withTrashed()])
            ->orderBy('start_date')->get()
            ->groupBy('employee_id');

        $salida = [];

        foreach ($contratos as $delEmpleado) {
            $empleado = $delEmpleado->first()->employee;
            $vigente  = $delEmpleado->last();               // el salario del último contrato del rango
            $d = $desde->copy()->max($delEmpleado->first()->start_date)->max($empleado->hire_date ?? $desde);
            $h = collect([$hasta, $vigente->ended_at, $empleado->termination_date])->filter()->min();
            $h = Carbon::parse($h);

            $dias = ReglasLaborales::diasComerciales($d, $h);

            if ($dias > 0) {
                $salida[] = [$empleado, $vigente, $d, $h, $dias];
            }
        }

        return $salida;
    }

    private function base(float $salario): float
    {
        return $salario + ($salario <= 2 * ReglasLaborales::smlmv() ? ReglasLaborales::auxilioTransporte() : 0);
    }

    private function yaExiste(Employee $empleado, string $tipo, Carbon $desde, Carbon $hasta): bool
    {
        return SocialBenefit::where('employee_id', $empleado->id)->where('type', $tipo)
            ->whereDate('period_start', '<=', $hasta)->whereDate('period_end', '>=', $desde)->exists();
    }

    private function crear(Employee $e, string $tipo, Carbon $desde, Carbon $hasta, int $dias, float $base, float $valor): SocialBenefit
    {
        return SocialBenefit::create([
            'tienda_id' => $e->tienda_id, 'employee_id' => $e->id, 'type' => $tipo,
            'period_start' => $desde, 'period_end' => $hasta, 'days' => $dias, 'base' => $base, 'amount' => $valor,
        ]);
    }
}
