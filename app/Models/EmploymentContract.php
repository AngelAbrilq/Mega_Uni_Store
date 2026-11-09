<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** NR-NOM-001 · Contrato laboral con historia (renovaciones encadenadas). */
class EmploymentContract extends Model
{
    use Auditable, PerteneceATienda;

    public const TIPOS = [
        'indefinido'  => 'Término indefinido',
        'fijo'        => 'Término fijo',
        'obra_labor'  => 'Obra o labor',
        'aprendizaje' => 'Aprendizaje (SENA)',
        'prestacion'  => 'Prestación de servicios',
    ];

    public const ACTIVO = 'activo', FINALIZADO = 'finalizado', TERMINADO = 'terminado';

    public const ESTADOS = [self::ACTIVO => 'Activo', self::FINALIZADO => 'Finalizado', self::TERMINADO => 'Terminado'];

    protected $fillable = [
        'tienda_id', 'employee_id', 'position_id', 'type', 'start_date', 'end_date', 'base_salary',
        'arl_class', 'status', 'renewed_from_id', 'ended_at', 'end_reason',
    ];

    protected $attributes = ['status' => self::ACTIVO, 'arl_class' => 1];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'ended_at' => 'date', 'base_salary' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function scopeActivos(Builder $q): void
    {
        $q->where('status', self::ACTIVO);
    }

    /** La prestación de servicios no es vínculo laboral: no entra a nómina. */
    public function esLaboral(): bool
    {
        return $this->type !== 'prestacion';
    }

    public function vencido(): bool
    {
        return $this->end_date !== null && $this->end_date->lt(today());
    }

    public function etiquetaAuditoria(): string
    {
        return (self::TIPOS[$this->type] ?? $this->type) . ' · ' . ($this->employee?->nombre_completo ?? '#' . $this->employee_id);
    }
}
