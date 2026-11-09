<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlPadre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use PerteneceAlPadre;

    /** Cuelga de SaleReturn: de ahí saca a qué negocio pertenece. */
    public function documentoPadre(): array
    {
        return [SaleReturn::class, 'sale_return_id'];
    }

    protected $fillable = [
        'sale_return_id', 'sale_item_id', 'product_id', 'name',
        'quantity', 'unit_price', 'unit_cost', 'tax_rate', 'tax_amount',
        'subtotal', 'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:3',
            'unit_price' => 'decimal:2',
            'unit_cost'  => 'decimal:2',
            'tax_rate'   => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal'   => 'decimal:2',
            'total'      => 'decimal:2',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
