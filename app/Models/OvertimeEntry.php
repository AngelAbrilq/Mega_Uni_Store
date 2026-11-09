<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** NR-NOM-005 · Horas extra y recargos con aprobación. */
class OvertimeEntry extends Model
{
    use Auditable, PerteneceATienda;

    public const PENDIENTE = 'pendiente', APROBADA = 'aprobada', RECHAZADA = 'rechazada';

    public const ESTADOS = [self::PENDIENTE => 'Pendiente', self::APROBADA => 'Aprobada', self::RECHAZADA => 'Rechazada'];

    protected $fillable = [
        'tienda_id', 'employee_id', 'date', 'hours', 'type', 'factor', 'hour_value', 'amount', 'status', 'notes',
        'registered_by', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'payroll_id',
    ];

    protected $attributes = ['status' => self::PENDIENTE];

    protected function casts(): array
    {
        return ['date' => 'date', 'hours' => 'decimal:2', 'factor' => 'decimal:4', 'hour_value' => 'decimal:2',
                'amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
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

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function tipoTexto(): string
    {
        return ReglasLaborales::TIPOS_HORA[$this->type] ?? $this->type;
    }
}
