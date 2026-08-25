<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use PerteneceATienda;

    public const ENTRADA   = 'entrada';
    public const SALIDA    = 'salida';
    public const AJUSTE    = 'ajuste';
    public const INICIAL   = 'inicial';

    protected $fillable = [
        'tienda_id', 'product_id', 'type', 'reason', 'quantity', 'balance_after',
        'unit_cost', 'source_type', 'source_id', 'user_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost'     => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** La venta, compra o ajuste que originó el movimiento. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeDe(Builder $q, int $productId): Builder
    {
        return $q->where('product_id', $productId);
    }

    /** Etiqueta legible del motivo. */
    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'venta'          => 'Venta',
            'compra'         => 'Compra',
            'ajuste_manual'  => 'Ajuste manual',
            'conteo'         => 'Conteo físico',
            'devolucion'     => 'Devolución',
            'anulacion'      => 'Anulación de venta',
            'inicial'        => 'Saldo inicial',
            default          => ucfirst(str_replace('_', ' ', (string) $this->reason)),
        };
    }
}
