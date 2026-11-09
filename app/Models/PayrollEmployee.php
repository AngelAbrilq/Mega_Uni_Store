<?php

namespace App\Models;

use App\Exceptions\NominaInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * NR-NOM-010 E2 · Un empleado dentro de una nómina (`nomina_empleado`).
 * Es también el desprendible: `verification_code` va en el QR (NR-NOM-008).
 */
class PayrollEmployee extends Model
{
    protected $fillable = [
        'payroll_id', 'employee_id', 'employment_contract_id', 'department_id', 'base_salary', 'days_worked',
        'absence_days', 'ibc', 'earned', 'deductions', 'net', 'verification_code',
    ];

    protected function casts(): array
    {
        return ['base_salary' => 'decimal:2', 'ibc' => 'decimal:2', 'earned' => 'decimal:2', 'deductions' => 'decimal:2', 'net' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        $guarda = function (PayrollEmployee $fila) {
            $estado = Payroll::query()->withoutGlobalScopes()->whereKey($fila->payroll_id)->value('status');

            if ($estado !== null && $estado !== Payroll::BORRADOR) {
                throw new NominaInmutable();
            }
        };

        static::updating($guarda);
        static::deleting($guarda);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class, 'employment_contract_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->orderBy('id');
    }

    public function contribution(): HasOne
    {
        return $this->hasOne(PayrollContribution::class);
    }

    public function devengados()
    {
        return $this->items->where('type', PayrollConcept::DEVENGADO);
    }

    public function deducciones()
    {
        return $this->items->where('type', PayrollConcept::DEDUCCION);
    }
}
