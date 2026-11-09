<?php

namespace App\Services\Nomina;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeEntry;
use App\Models\User;
use App\Notifications\AvisoDelSistema;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Novedades de personal (NR-NOM-005 y NR-NOM-006).
 *
 * Horas extra
 *   · valor_hora = salario / horas_mes del día (220 → 210 según Ley 2101).
 *   · valor = valor_hora × factor × horas; factor según tipo y fecha.
 *   · No se registran en días de vacaciones aprobadas (calendario bloqueado).
 *   · Rechazar exige motivo y avisa a quien la registró.
 *
 * Vacaciones y ausencias
 *   · Vacaciones en días hábiles (lunes a sábado); el saldo es
 *     15 días por cada 360 trabajados menos lo ya aprobado.
 *   · No puede haber dos novedades aprobadas o pendientes que se crucen.
 *   · La incapacidad exige soporte; con la injustificada se registra ya aprobada.
 */
class NovedadesService
{
    public const DIAS_VACACIONES_ANUALES = 15;

    /* ═════════════ Horas extra ═════════════ */

    public function registrarHoras(Employee $empleado, array $datos, User $quien): OvertimeEntry
    {
        $fecha = Carbon::parse($datos['date']);

        if ($this->enVacaciones($empleado, $fecha)) {
            throw ValidationException::withMessages(['date' => 'Ese día el empleado está de vacaciones aprobadas.']);
        }

        $salario = (float) ($empleado->contratoActivo()?->base_salary ?? $empleado->base_salary);

        if ($salario <= 0) {
            throw ValidationException::withMessages(['employee_id' => 'El empleado no tiene salario: regístrale un contrato.']);
        }

        $factor = ReglasLaborales::factor($datos['type'], $fecha);
        $hora   = ReglasLaborales::valorHora($salario, $fecha);

        return OvertimeEntry::create([
            'tienda_id'     => $empleado->tienda_id,
            'employee_id'   => $empleado->id,
            'date'          => $fecha,
            'hours'         => $datos['hours'],
            'type'          => $datos['type'],
            'factor'        => $factor,
            'hour_value'    => $hora,
            'amount'        => ReglasLaborales::redondear($hora * $factor * (float) $datos['hours']),
            'notes'         => $datos['notes'] ?? null,
            'registered_by' => $quien->id,
        ]);
    }

    public function aprobarHoras(OvertimeEntry $horas, User $quien): void
    {
        $this->pendiente($horas->status);
        $horas->update(['status' => OvertimeEntry::APROBADA, 'reviewed_by' => $quien->id, 'reviewed_at' => now()]);
    }

    public function rechazarHoras(OvertimeEntry $horas, User $quien, string $motivo): void
    {
        $this->pendiente($horas->status);
        $horas->update(['status' => OvertimeEntry::RECHAZADA, 'reviewed_by' => $quien->id, 'reviewed_at' => now(), 'rejection_reason' => $motivo]);

        $this->avisar($horas->registered_by, $quien, 'Horas extra rechazadas',
            "{$horas->hours} h ({$horas->tipoTexto()}) de {$horas->employee->nombre_completo} del {$horas->date->format('d/m/Y')}: «{$motivo}».",
            route('overtime.index'));
    }

    /* ═════════════ Vacaciones y ausencias ═════════════ */

    public function registrarAusencia(Employee $empleado, array $datos, User $quien, ?UploadedFile $soporte = null): LeaveRequest
    {
        $tipo  = $datos['type'];
        $desde = Carbon::parse($datos['start_date']);
        $hasta = Carbon::parse($datos['end_date']);

        if ($tipo === 'incapacidad' && ! $soporte) {
            throw ValidationException::withMessages(['document' => 'La incapacidad requiere el documento soporte.']);
        }

        $dias = $tipo === 'vacaciones' ? ReglasLaborales::diasHabiles($desde, $hasta) : $desde->diffInDays($hasta) + 1;

        return DB::transaction(function () use ($empleado, $datos, $quien, $soporte, $tipo, $desde, $hasta, $dias) {
            Employee::whereKey($empleado->id)->lockForUpdate()->first();

            $this->sinCruce($empleado, $desde, $hasta);

            if ($tipo === 'vacaciones') {
                $saldo = $this->saldoVacaciones($empleado);

                if ($dias > $saldo) {
                    throw ValidationException::withMessages(['end_date' => "El empleado solo tiene {$saldo} días de vacaciones disponibles. Ajusta la solicitud o espera a que acumule más días."]);
                }
            }

            $directa = in_array($tipo, LeaveRequest::DIRECTAS, true);

            return LeaveRequest::create([
                'tienda_id'     => $empleado->tienda_id,
                'employee_id'   => $empleado->id,
                'type'          => $tipo,
                'start_date'    => $desde,
                'end_date'      => $hasta,
                'days'          => $dias,
                'status'        => $directa ? LeaveRequest::APROBADA : LeaveRequest::PENDIENTE,
                'notes'         => $datos['notes'] ?? null,
                'document_path' => $soporte?->store('ausencias', 'local'),
                'registered_by' => $quien->id,
                'reviewed_by'   => $directa ? $quien->id : null,
                'reviewed_at'   => $directa ? now() : null,
            ]);
        });
    }

    public function aprobarAusencia(LeaveRequest $novedad, User $quien): void
    {
        DB::transaction(function () use ($novedad, $quien) {
            $novedad = LeaveRequest::whereKey($novedad->id)->lockForUpdate()->firstOrFail();
            $this->pendiente($novedad->status);

            if ($novedad->type === 'vacaciones') {
                $saldo = $this->saldoVacaciones($novedad->employee, excluir: $novedad->id);

                if ($novedad->days > $saldo) {
                    throw ValidationException::withMessages(['novedad' => "El empleado solo tiene {$saldo} días de vacaciones disponibles. Ajusta la solicitud o espera a que acumule más días."]);
                }
            }

            $novedad->update(['status' => LeaveRequest::APROBADA, 'reviewed_by' => $quien->id, 'reviewed_at' => now()]);
        });
    }

    public function rechazarAusencia(LeaveRequest $novedad, User $quien, string $motivo): void
    {
        $this->pendiente($novedad->status);
        $novedad->update(['status' => LeaveRequest::RECHAZADA, 'reviewed_by' => $quien->id, 'reviewed_at' => now(), 'rejection_reason' => $motivo]);

        $this->avisar($novedad->registered_by, $quien, $novedad->tipoTexto() . ' rechazada',
            "{$novedad->employee->nombre_completo}, {$novedad->start_date->format('d/m')} a {$novedad->end_date->format('d/m/Y')}: «{$motivo}».",
            route('leaves.index'));
    }

    /**
     * Días hábiles de vacaciones disponibles: 15 por año trabajado,
     * proporcionales, menos los aprobados (y los pendientes, que ya están
     * comprometidos) y los pagados en dinero.
     */
    public function saldoVacaciones(Employee $empleado, ?Carbon $al = null, ?int $excluir = null): int
    {
        return (int) floor($this->causadas($empleado, $al) - $this->tomadas($empleado, $excluir));
    }

    public function causadas(Employee $empleado, ?Carbon $al = null): float
    {
        $al ??= today();
        $ingreso = $empleado->hire_date ?? $empleado->created_at;

        if (! $ingreso || $ingreso->gt($al)) {
            return 0;
        }

        return ReglasLaborales::diasComerciales($ingreso, $al) * self::DIAS_VACACIONES_ANUALES / 360;
    }

    public function tomadas(Employee $empleado, ?int $excluir = null): int
    {
        $enTiempo = (int) LeaveRequest::where('employee_id', $empleado->id)->where('type', 'vacaciones')
            ->whereIn('status', [LeaveRequest::APROBADA, LeaveRequest::PENDIENTE])
            ->when($excluir, fn ($q) => $q->whereKeyNot($excluir))
            ->sum('days');

        $enDinero = (int) \App\Models\SocialBenefit::where('employee_id', $empleado->id)->where('type', 'vacaciones_dinero')->sum('days');

        return $enTiempo + $enDinero;
    }

    public function enVacaciones(Employee $empleado, Carbon $fecha): bool
    {
        return LeaveRequest::where('employee_id', $empleado->id)->where('type', 'vacaciones')
            ->where('status', LeaveRequest::APROBADA)
            ->whereDate('start_date', '<=', $fecha)->whereDate('end_date', '>=', $fecha)->exists();
    }

    private function sinCruce(Employee $empleado, Carbon $desde, Carbon $hasta): void
    {
        $cruce = LeaveRequest::where('employee_id', $empleado->id)
            ->whereIn('status', [LeaveRequest::APROBADA, LeaveRequest::PENDIENTE])
            ->whereDate('start_date', '<=', $hasta)->whereDate('end_date', '>=', $desde)->first();

        if ($cruce) {
            throw ValidationException::withMessages(['start_date' => "Se cruza con {$cruce->tipoTexto()} del {$cruce->start_date->format('d/m')} al {$cruce->end_date->format('d/m/Y')}."]);
        }
    }

    private function pendiente(string $estado): void
    {
        if ($estado !== 'pendiente') {
            throw ValidationException::withMessages(['novedad' => 'Esta novedad ya fue revisada.']);
        }
    }

    private function avisar(?int $destino, User $quien, string $titulo, string $mensaje, string $url): void
    {
        if (! $destino || $destino === $quien->id) {
            return;
        }

        $usuario = User::find($destino);

        if ($usuario) {
            Notification::send($usuario, new AvisoDelSistema($titulo, $mensaje, 'warning', $url, $quien->nombre_completo));
        }
    }
}
