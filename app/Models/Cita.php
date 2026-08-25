<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Una cita.
 *
 * ── Los cinco estados y por qué son cinco ──
 *
 *   pendiente    Se agendó. Nadie ha confirmado nada.
 *   confirmada   El cliente dijo que viene.
 *   atendida     Se cumplió. De aquí sale la venta.
 *   no_llego     No vino y no avisó.
 *   cancelada    Avisó que no viene.
 *
 * Las dos últimas parecen la misma y no lo son. Una cancelación avisada
 * deja el cupo libre para otro; un plantón se pierde entero. Guardarlas
 * juntas le quitaría al dueño el único dato que sirve para decidir a quién
 * le pide abono la próxima vez.
 */
class Cita extends Model
{
    use Auditable, PerteneceATienda, SoftDeletes;

    protected $table = 'citas';

    public const ESTADOS = [
        'pendiente'  => 'Pendiente',
        'confirmada' => 'Confirmada',
        'atendida'   => 'Atendida',
        'no_llego'   => 'No llegó',
        'cancelada'  => 'Cancelada',
    ];

    /** Los que siguen ocupando el cupo. Los otros lo liberan. */
    public const VIVAS = ['pendiente', 'confirmada', 'atendida'];

    protected $fillable = [
        'tienda_id', 'numero', 'recurso_id', 'customer_id', 'product_id',
        'titulo', 'inicio', 'fin', 'estado', 'precio', 'notas',
        'user_id', 'sale_id', 'recordada_en', 'atendida_en',
    ];

    protected function casts(): array
    {
        return [
            'inicio'       => 'datetime',
            'fin'          => 'datetime',
            'precio'       => 'decimal:2',
            'recordada_en' => 'datetime',
            'atendida_en'  => 'datetime',
        ];
    }

    /* ─────────────── Relaciones ─────────────── */

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ─────────────── Consultas ─────────────── */

    /**
     * Las que pisan este rango.
     *
     * Dos rangos se pisan cuando uno empieza antes de que el otro termine
     * Y termina después de que el otro empiece. Tocarse en el borde NO es
     * pisarse: una cita de 10 a 11 y otra de 11 a 12 caben las dos.
     */
    public function scopeQuePisan(Builder $q, $inicio, $fin): Builder
    {
        return $q->where('inicio', '<', $fin)->where('fin', '>', $inicio);
    }

    /** Las que todavía ocupan el cupo. */
    public function scopeVivas(Builder $q): Builder
    {
        return $q->whereIn('estado', self::VIVAS);
    }

    public function scopeDelDia(Builder $q, $dia): Builder
    {
        $dia = $dia instanceof Carbon ? $dia : Carbon::parse($dia);

        return $q->whereBetween('inicio', [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()]);
    }

    public function scopeEntre(Builder $q, $desde, $hasta): Builder
    {
        return $q->where('inicio', '<', $hasta)->where('fin', '>', $desde);
    }

    public function scopeDeRecurso(Builder $q, ?int $recursoId): Builder
    {
        return $recursoId ? $q->where('recurso_id', $recursoId) : $q;
    }

    /* ─────────────── Preguntas ─────────────── */

    public function duracion(): int
    {
        return (int) $this->inicio->diffInMinutes($this->fin);
    }

    public function estaViva(): bool
    {
        return in_array($this->estado, self::VIVAS, true);
    }

    /**
     * ¿Se puede convertir en venta desde aquí?
     *
     * Hacen falta tres cosas: que se haya atendido, que no esté cobrada ya,
     * y que tenga un SERVICIO asociado — porque una venta se arma con
     * líneas de producto, y una cita suelta («revisión», «visita») no tiene
     * ninguno. Esas se cobran en el punto de venta como cualquier otra
     * cosa; forzar aquí un producto inventado ensuciaría el catálogo.
     */
    public function sePuedeCobrar(): bool
    {
        return $this->estado === 'atendida'
            && $this->sale_id === null
            && $this->product_id !== null
            && (float) $this->precio > 0;
    }

    public function yaPaso(): bool
    {
        return $this->fin->isPast();
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? ucfirst($this->estado);
    }

    /** El tono de la etiqueta, para no repetir el `match` en cada vista. */
    public function tono(): string
    {
        return match ($this->estado) {
            'confirmada' => 'ok',
            'atendida'   => 'info',
            'no_llego'   => 'off',
            'cancelada'  => 'off',
            default      => 'warn',
        };
    }

    public function horas(): string
    {
        return $this->inicio->format('h:i a') . ' – ' . $this->fin->format('h:i a');
    }

    /** A quién se le agendó, aunque no haya cliente registrado. */
    public function quien(): string
    {
        return $this->customer?->full_name
            ?: ($this->customer?->first_name ?: 'Sin cliente');
    }

    public function etiquetaAuditoria(): string
    {
        return $this->numero . ' · ' . $this->titulo;
    }
}
