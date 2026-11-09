<?php

namespace App\Models;

use App\Exceptions\NominaInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** NR-NOM-010 E3 · Cada concepto aplicado (`nomina_detalle`). */
class PayrollItem extends Model
{
    protected $fillable = ['payroll_employee_id', 'payroll_concept_id', 'code', 'name', 'type', 'quantity', 'amount', 'is_manual'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'amount' => 'decimal:2', 'is_manual' => 'boolean'];
    }

    protected static function booted(): void
    {
        $guarda = function (PayrollItem $item) {
            $estado = Payroll::query()->withoutGlobalScopes()
                ->whereKey(PayrollEmployee::whereKey($item->payroll_employee_id)->value('payroll_id'))
                ->value('status');

            if ($estado !== null && $estado !== Payroll::BORRADOR) {
                throw new NominaInmutable();
            }
        };

        static::updating($guarda);
        static::deleting($guarda);
    }

    public function payrollEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollEmployee::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(PayrollConcept::class, 'payroll_concept_id')->withoutGlobalScopes();
    }
}
