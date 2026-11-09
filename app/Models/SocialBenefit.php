<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** NR-NOM-007 · Prestaciones sociales (`prestaciones_sociales`). */
class SocialBenefit extends Model
{
    use Auditable, PerteneceATienda;

    public const TIPOS = [
        'cesantias'           => 'Cesantías',
        'intereses_cesantias' => 'Intereses sobre cesantías',
        'prima'               => 'Prima de servicios',
        'vacaciones_dinero'   => 'Vacaciones en dinero',
    ];

    public const CALCULADA = 'calculada', PAGADA = 'pagada';

    protected $fillable = ['tienda_id', 'employee_id', 'type', 'period_start', 'period_end', 'days', 'base', 'amount', 'status', 'paid_by', 'paid_at'];

    protected $attributes = ['status' => self::CALCULADA];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'paid_at' => 'datetime', 'base' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by')->withTrashed();
    }

    public function tipoTexto(): string
    {
        return self::TIPOS[$this->type] ?? $this->type;
    }
}
