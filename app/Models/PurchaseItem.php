<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlPadre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use PerteneceAlPadre;

    /** Cuelga de Purchase: de ahí saca a qué negocio pertenece. */
    public function documentoPadre(): array
    {
        return [Purchase::class, 'purchase_id'];
    }

    protected $fillable = [
        'purchase_id', 'product_id', 'name', 'quantity', 'unit_cost',
        'tax_rate', 'tax_amount', 'subtotal', 'total', 'update_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity'    => 'decimal:3',
            'unit_cost'   => 'decimal:2',
            'tax_rate'    => 'decimal:2',
            'tax_amount'  => 'decimal:2',
            'subtotal'    => 'decimal:2',
            'total'       => 'decimal:2',
            'update_cost' => 'boolean',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
