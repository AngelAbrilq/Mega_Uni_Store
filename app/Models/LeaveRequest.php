<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NR-NOM-006 · Vacaciones y ausencias.
 *
 * Las vacaciones se cuentan en días hábiles (lunes a sábado) y se descuentan
 * del saldo; las ausencias, en días calendario. Solo la licencia no
 * remunerada y la ausencia injustificada descuentan salario.
 */
class LeaveRequest extends Model
{
    use Auditable, PerteneceATienda;

    public const TIPOS = [
        'vacaciones'             => 'Vacaciones',
        'incapacidad'            => 'Incapacidad',
        'licencia_remunerada'    => 'Licencia remunerada',
        'licencia_no_remunerada' => 'Licencia no remunerada',
        'injustificada'          => 'Ausencia injustificada',
    ];

    /** Descuentan salario (y auxilio) en la liquidación. */
    public const NO_REMUNERADAS = ['licencia_no_remunerada', 'injustificada'];

    /** Hechos ya ocurridos: se registran aprobados. */
    public const DIRECTAS = ['incapacidad', 'injustificada'];

    public const PENDIENTE = 'pendiente', APROBADA = 'aprobada', RECHAZADA = 'rechazada';

    public const ESTADOS = [self::PENDIENTE => 'Pendiente', self::APROBADA => 'Aprobada', self::RECHAZADA => 'Rechazada'];

    protected $fillable = [
        'tienda_id', 'employee_id', 'type', 'start_date', 'end_date', 'days', 'status', 'notes', 'document_path',
        'registered_by', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected $attributes = ['status' => self::PENDIENTE];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by')->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function tipoTexto(): string
    {
        return self::TIPOS[$this->type] ?? $this->type;
    }

    public function descuentaSalario(): bool
    {
        return in_array($this->type, self::NO_REMUNERADAS, true);
    }
}
