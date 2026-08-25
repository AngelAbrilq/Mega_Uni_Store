<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cuándo trabaja un recurso, por día de la semana.
 *
 * Una fila por franja: un recurso con jornada partida tiene dos filas del
 * mismo día. Por eso no hay índice único de (recurso, día) — el único
 * impediría el horario de 8 a 12 y de 2 a 6, que es como trabaja medio
 * comercio del país.
 */
class Horario extends Model
{
    protected $table = 'horarios';

    public const DIAS = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo',
    ];

    protected $fillable = ['recurso_id', 'dia_semana', 'desde', 'hasta'];

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }

    public function nombreDia(): string
    {
        return self::DIAS[(int) $this->dia_semana] ?? '—';
    }

    /* ─────────────── Horas ─────────────── */

    /** El inicio de esta franja, puesto sobre un día concreto. */
    public function inicioEn(Carbon $dia): Carbon
    {
        return $this->conHora($dia, (string) $this->desde);
    }

    public function finEn(Carbon $dia): Carbon
    {
        return $this->conHora($dia, (string) $this->hasta);
    }

    /**
     * ¿Esta franja contiene ENTERO el rango?
     *
     * Los bordes cuentan como dentro: una cita que termina exactamente a
     * las 12 cabe en un horario que va hasta las 12. Es la lectura que
     * espera cualquiera que mire un reloj.
     */
    public function contiene(Carbon $inicio, Carbon $fin): bool
    {
        return $inicio->greaterThanOrEqualTo($this->inicioEn($inicio))
            && $fin->lessThanOrEqualTo($this->finEn($inicio));
    }

    public function minutos(): int
    {
        $hoy = Carbon::today();

        return (int) $this->inicioEn($hoy)->diffInMinutes($this->finEn($hoy));
    }

    public function texto(): string
    {
        return substr((string) $this->desde, 0, 5) . ' a ' . substr((string) $this->hasta, 0, 5);
    }

    private function conHora(Carbon $dia, string $hora): Carbon
    {
        [$h, $m] = array_pad(explode(':', $hora), 2, '0');

        return $dia->copy()->setTime((int) $h, (int) $m, 0);
    }
}
