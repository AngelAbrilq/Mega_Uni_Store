<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlPadre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cupón aplicado en una venta, con el descuento que otorgó (TBL-VCU-001).
 */
class SaleCoupon extends Model
{
    use PerteneceAlPadre;

    protected $fillable = ['sale_id', 'coupon_id', 'code', 'discount_amount'];

    public function documentoPadre(): array
    {
        return [Sale::class, 'sale_id'];
    }

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class)->withTrashed();
    }
}
