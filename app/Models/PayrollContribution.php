<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** NR-NOM-004 · Aportes a seguridad social y parafiscales (`aportes_seguridad_social`). */
class PayrollContribution extends Model
{
    protected $fillable = [
        'payroll_employee_id', 'ibc', 'ibc_capped', 'health_employee', 'health_employer', 'pension_employee',
        'pension_employer', 'solidarity_fund', 'arl_class', 'arl', 'ccf', 'icbf', 'sena', 'total_employer',
    ];

    protected function casts(): array
    {
        return array_fill_keys(['ibc', 'health_employee', 'health_employer', 'pension_employee', 'pension_employer',
            'solidarity_fund', 'arl', 'ccf', 'icbf', 'sena', 'total_employer'], 'decimal:2') + ['ibc_capped' => 'boolean'];
    }

    public function payrollEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollEmployee::class);
    }
}
