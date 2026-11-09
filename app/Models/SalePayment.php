<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlPadre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de una venta (TBL-PAG-001).
 *
 * Nace aprobado, salvo que el medio exija verificación
 * (`payment_methods.requires_verification`): entonces queda pendiente
 * hasta que alguien con `ventas.verificar_pagos` lo apruebe o lo rechace.
 */
class SalePayment extends Model
{
    use PerteneceAlPadre;

    public const APROBADO  = 'aprobado';
    public const PENDIENTE = 'pendiente';
    public const RECHAZADO = 'rechazado';

    /** Cuelga de Sale: de ahí saca a qué negocio pertenece. */
    public function documentoPadre(): array
    {
        return [Sale::class, 'sale_id'];
    }

    protected $fillable = [
        'sale_id', 'payment_method_id', 'method_name', 'amount', 'reference',
        'status', 'verified_by', 'verified_at', 'rejection_reason',
    ];

    protected $attributes = ['status' => self::APROBADO];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'verified_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    /** Los que cuentan como dinero recibido: aprobados y pendientes (RN-32). */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->where($q->getModel()->getTable() . '.status', '!=', self::RECHAZADO);
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where($q->getModel()->getTable() . '.status', self::PENDIENTE);
    }

    public function getPendienteAttribute(): bool
    {
        return $this->status === self::PENDIENTE;
    }
}
