<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un rato en que este recurso no está, aunque su horario diga que sí.
 *
 * Vacaciones, un festivo, una cita médica, «hoy salgo a las 3».
 *
 * ── Por qué no se resuelve editando el horario ──
 *
 * Porque el horario es la regla y esto es la excepción. Si se metieran en
 * la misma tabla, cada vez que alguien se enferma un martes habría que
 * reescribir el horario de los martes y después acordarse de devolverlo.
 * Nadie se acuerda; el horario queda mal para siempre.
 */
class Bloqueo extends Model
{
    protected $table = 'bloqueos';

    protected $fillable = ['recurso_id', 'inicio', 'fin', 'motivo'];

    protected function casts(): array
    {
        return ['inicio' => 'datetime', 'fin' => 'datetime'];
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }

    /**
     * Los que pisan este rango.
     *
     * La condición del solape es la misma de toda la vida y conviene
     * leerla despacio: dos rangos se pisan cuando uno empieza antes de que
     * el otro termine Y termina después de que el otro empiece. Lo que NO
     * es solape es tocarse en el borde — una cita que empieza justo cuando
     * termina la anterior cabe perfecto.
     */
    public function scopeQuePisan(Builder $q, $inicio, $fin): Builder
    {
        return $q->where('inicio', '<', $fin)->where('fin', '>', $inicio);
    }
}
