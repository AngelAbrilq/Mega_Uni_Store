<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use Auditable, PerteneceATienda;
    protected $fillable = [
        'tienda_id', 'number', 'sale_id', 'user_id', 'subtotal', 'tax_total', 'total',
        'reason', 'restock', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'    => 'decimal:2',
            'tax_total'   => 'decimal:2',
            'total'       => 'decimal:2',
            'restock'     => 'boolean',
            'returned_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSearch(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);

        if ($t === '') {
            return $q;
        }

        return $q->where(fn (Builder $s) => $s->where('number', 'like', "%{$t}%")
            ->orWhereHas('sale', fn ($v) => $v->where('number', 'like', "%{$t}%")));
    }

    /**
     * Siguiente consecutivo del documento.
     *
     * Antes salía de `max(id) + 1`, que se rompe si dos personas registran
     * al mismo tiempo. Ahora lo entrega ConsecutivoService, que incrementa
     * una fila de contador y deja que la base serialice.
     */
    public static function siguienteNumero(): string
    {
        return \App\Services\ConsecutivoService::siguiente('devoluciones');
    }
}
