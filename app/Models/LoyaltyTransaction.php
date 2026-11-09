<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de puntos de fidelidad (CL-PUN-001 · RN-38).
 */
class LoyaltyTransaction extends Model
{
    use PerteneceATienda;

    public const GANADOS  = 'earn';
    public const REVERSA  = 'reverse';

    protected $fillable = ['customer_id', 'tienda_id', 'sale_id', 'sale_return_id', 'user_id', 'type', 'points', 'balance_after', 'notes'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'balance_after' => 'integer'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }
}
