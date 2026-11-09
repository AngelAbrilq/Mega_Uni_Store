<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El cliente en un local: su cuenta de puntos y su historia de compras
 * en ESE local (CL-TDC-001 · CL-PUN-001).
 */
class CustomerTienda extends Model
{
    use PerteneceATienda;

    protected $table = 'customer_tienda';

    protected $fillable = ['customer_id', 'tienda_id', 'points_balance', 'total_spent', 'purchases_count', 'is_active'];

    protected $attributes = ['points_balance' => 0, 'total_spent' => 0, 'purchases_count' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'points_balance'  => 'integer',
            'total_spent'     => 'decimal:2',
            'purchases_count' => 'integer',
            'is_active'       => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
