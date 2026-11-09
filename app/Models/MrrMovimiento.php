<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de la bitácora del ingreso.
 *
 * Cada vez que un cliente entra, sube de plan, baja, se va o vuelve, queda
 * una fila aquí. Es lo que permite responder «¿por qué cambió el MRR?» en
 * vez de solo «¿cuánto es?».
 *
 * ── Los cinco movimientos ──
 *
 *   nuevo        Nunca había pagado y empieza. Una ferretería entra en
 *                Básico: +$29.000.
 *   expansion    Ya pagaba y ahora paga más: sube de plan o suma sedes.
 *   contraccion  Sigue siendo cliente pero paga menos. Es la más
 *                importante de vigilar: no aparece en la lista de bajas y
 *                sin embargo el ingreso se cae igual.
 *   baja         Cancela. El que duele.
 *   reactivacion Se había ido y volvió. Cuenta aparte de «nuevo» porque
 *                conseguirlo costó distinto.
 */
class MrrMovimiento extends Model
{
    protected $table = 'mrr_movimientos';

    public const TIPOS = ['nuevo', 'expansion', 'contraccion', 'baja', 'reactivacion'];

    protected $fillable = [
        'empresa_id', 'tipo', 'plan_antes_id', 'plan_despues_id',
        'mrr_antes', 'mrr_despues', 'delta', 'motivo', 'ocurrio_en',
    ];

    protected function casts(): array
    {
        return [
            'mrr_antes'   => 'decimal:2',
            'mrr_despues' => 'decimal:2',
            'delta'       => 'decimal:2',
            'ocurrio_en'  => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function planAntes(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_antes_id');
    }

    public function planDespues(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_despues_id');
    }

    /* ─────────────── Consultas ─────────────── */

    /**
     * Los de un mes.
     *
     * Se filtra por `ocurrio_en` y no por `created_at` a propósito: una
     * baja que se registra tres días tarde sigue siendo del día que el
     * cliente se fue. Si se contara por la fecha de la fila, el churn de
     * un mes se le cargaría al siguiente y los dos quedarían mal.
     */
    public function scopeDelMes(Builder $q, ?string $mes = null): Builder
    {
        $inicio = $mes ? \Illuminate\Support\Carbon::parse($mes . '-01') : now()->startOfMonth();

        return $q->whereBetween('ocurrio_en', [
            $inicio->copy()->startOfMonth()->toDateString(),
            $inicio->copy()->endOfMonth()->toDateString(),
        ]);
    }

    public function scopeDeTipo(Builder $q, string $tipo): Builder
    {
        return $q->where('tipo', $tipo);
    }

    /** ¿Sumó o restó? Lo usa el color de la fila. */
    public function suma(): bool
    {
        return (float) $this->delta >= 0;
    }

    public function etiqueta(): string
    {
        return match ($this->tipo) {
            'nuevo'        => 'Cliente nuevo',
            'expansion'    => 'Expansión',
            'contraccion'  => 'Contracción',
            'baja'         => 'Baja',
            'reactivacion' => 'Reactivación',
            default        => ucfirst($this->tipo),
        };
    }
}
