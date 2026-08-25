<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use PerteneceATienda;

    public const ABIERTA = 'abierta';
    public const CERRADA = 'cerrada';

    protected $fillable = [
        'tienda_id', 'user_id', 'closed_by', 'opening_amount', 'expected_amount',
        'counted_amount', 'difference', 'status', 'notes', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount'  => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'counted_amount'  => 'decimal:2',
            'difference'      => 'decimal:2',
            'opened_at'       => 'datetime',
            'closed_at'       => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeAbierta(Builder $q): Builder
    {
        return $q->where('status', self::ABIERTA);
    }

    /** El turno abierto de un usuario, si lo tiene. */
    public static function abiertaDe(int $userId): ?self
    {
        return static::abierta()->where('user_id', $userId)->latest('opened_at')->first();
    }

    /** Efectivo esperado: base inicial + todo lo cobrado en efectivo. */
    public function calcularEsperado(): float
    {
        $efectivo = SalePayment::query()
            ->whereHas('sale', fn ($q) => $q->where('cash_session_id', $this->id)
                                            ->where('status', Sale::PAGADA))
            ->whereHas('paymentMethod', fn ($q) => $q->where('name', 'like', '%fectivo%'))
            ->sum('amount');

        return (float) $this->opening_amount + (float) $efectivo;
    }

    public function getAbiertaAttribute(): bool
    {
        return $this->status === self::ABIERTA;
    }
}
