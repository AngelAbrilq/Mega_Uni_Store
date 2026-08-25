<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Quien atiende: una persona, una silla, una cabina.
 *
 * ── Por qué personas y cosas son lo mismo aquí ──
 *
 * Porque el problema que resuelven es idéntico: una sola cita a la vez.
 * Una peluquería agenda estilistas, un taller agenda bahías, un consultorio
 * agenda consultorios. Modelarlos aparte obligaría a escribir dos veces la
 * comprobación de solapes — que es la única regla que de verdad importa en
 * una agenda, y la que no se puede tener duplicada.
 *
 * El `tipo` es solo para el ícono y el texto de la pantalla.
 */
class Recurso extends Model
{
    use PerteneceATienda, SoftDeletes;

    protected $table = 'recursos';

    public const TIPOS = [
        'persona' => 'Persona',
        'espacio' => 'Espacio',
        'equipo'  => 'Equipo',
    ];

    protected $fillable = [
        'tienda_id', 'nombre', 'tipo', 'color', 'user_id', 'activo', 'orden', 'notas',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /* ─────────────── Relaciones ─────────────── */

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class)->orderBy('dia_semana')->orderBy('desde');
    }

    public function bloqueos(): HasMany
    {
        return $this->hasMany(Bloqueo::class)->orderBy('inicio');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* ─────────────── Consultas ─────────────── */

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }

    /* ─────────────── Horario ─────────────── */

    /**
     * Las franjas en que trabaja ese día.
     *
     * @return \Illuminate\Support\Collection<int, Horario>
     */
    public function franjasDe(Carbon $dia)
    {
        return $this->horarios->where('dia_semana', (int) $dia->dayOfWeek)->values();
    }

    /** ¿Trabaja ese día? */
    public function trabajaEl(Carbon $dia): bool
    {
        return $this->franjasDe($dia)->isNotEmpty();
    }

    /**
     * ¿Esta franja cae dentro de su horario?
     *
     * Tiene que caber ENTERA dentro de una sola franja. Una cita que
     * empieza a las 11:40 y dura una hora no cabe en un horario que
     * termina a las 12, aunque su inicio sí esté dentro — y aceptarla
     * significaría que alguien se queda trabajando sin saberlo.
     */
    public function cabeEnSuHorario(Carbon $inicio, Carbon $fin): bool
    {
        foreach ($this->franjasDe($inicio) as $franja) {
            if ($franja->contiene($inicio, $fin)) {
                return true;
            }
        }

        return false;
    }

    /** El nombre corto para el calendario: «Laura M.» */
    public function nombreCorto(): string
    {
        $partes = preg_split('/\s+/u', trim($this->nombre)) ?: [];

        if (count($partes) < 2) {
            return (string) $this->nombre;
        }

        return $partes[0] . ' ' . mb_strtoupper(mb_substr($partes[1], 0, 1)) . '.';
    }

    /**
     * Un color de texto que se lea sobre el suyo.
     *
     * Se calcula la luminancia en vez de guardar un segundo color: si el
     * usuario escoge un amarillo claro, el blanco encima no se ve — y
     * pedirle que además escoja el color del texto sería trasladarle un
     * problema que la máquina resuelve sola.
     */
    public function colorTexto(): string
    {
        $hex = ltrim((string) $this->color, '#');

        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        // Luminancia percibida: el ojo pesa mucho más el verde que el azul.
        $luz = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luz > 0.62 ? '#17212F' : '#ffffff';
    }
}
