<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlPadre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use PerteneceAlPadre;

    /** Cuelga de Sale: de ahí saca a qué negocio pertenece. */
    public function documentoPadre(): array
    {
        return [Sale::class, 'sale_id'];
    }

    protected $fillable = [
        'sale_id', 'product_id', 'name', 'sku',
        'quantity', 'returned_quantity', 'unit_price', 'unit_cost', 'discount',
        'tax_name', 'tax_rate', 'tax_amount', 'subtotal', 'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity'          => 'decimal:3',
            'returned_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'unit_cost'  => 'decimal:2',
            'discount'   => 'decimal:2',
            'tax_rate'   => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal'   => 'decimal:2',
            'total'      => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Cuántas unidades de este renglón todavía se pueden devolver. */
    public function getDevolvibleAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->returned_quantity);
    }

    /** Utilidad del renglón. */
    public function getProfitAttribute(): float
    {
        return (float) $this->subtotal - ((float) $this->unit_cost * (float) $this->quantity);
    }
}
