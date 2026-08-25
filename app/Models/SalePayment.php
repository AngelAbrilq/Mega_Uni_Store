<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlPadre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use PerteneceAlPadre;

    /** Cuelga de Sale: de ahí saca a qué negocio pertenece. */
    public function documentoPadre(): array
    {
        return [Sale::class, 'sale_id'];
    }

    protected $fillable = ['sale_id', 'payment_method_id', 'method_name', 'amount', 'reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
