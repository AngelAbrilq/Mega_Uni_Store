<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un producto, en un local.
 *
 * Responde tres preguntas a la vez —cuánto hay, desde cuánto reponer, a
 * qué precio se vende aquí— y una cuarta por omisión: si esta fila no
 * existe, este local no vende ese producto y no aparece en su punto de
 * venta.
 *
 * Esa cuarta es la importante. Es lo que permite que la ferretería del
 * centro maneje cemento y la del norte no, sin tener que crear el
 * producto dos veces ni corregirlo dos veces.
 */
class Existencia extends Model
{
    use PerteneceATienda;

    protected $table = 'existencias';

    protected $fillable = [
        'tienda_id', 'product_id', 'stock', 'min_stock', 'precio', 'activo', 'ubicacion',
    ];

    protected function casts(): array
    {
        return [
            'stock'     => 'decimal:3',
            'min_stock' => 'decimal:3',
            'precio'    => 'decimal:2',
            'activo'    => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /* ─────────────── Consultas ─────────────── */

    /** Llegó al punto de reorden. */
    public function scopeBajoMinimo(Builder $q): Builder
    {
        return $q->whereColumn('stock', '<=', 'min_stock');
    }

    public function scopeAgotado(Builder $q): Builder
    {
        return $q->where('stock', '<=', 0);
    }

    /* ─────────────── Cálculos ─────────────── */

    /** Lo que este local puede ofrecer. Nunca un número negativo. */
    public function getDisponibleAttribute(): float
    {
        return max(0.0, (float) $this->stock);
    }

    /** El precio de aquí, o el del producto si el local no puso el suyo. */
    public function precioVenta(): float
    {
        if ($this->precio !== null) {
            return (float) $this->precio;
        }

        return (float) ($this->product?->price ?? 0);
    }

    /** ¿Este local le puso precio propio? */
    public function tienePrecioPropio(): bool
    {
        return $this->precio !== null;
    }

    /** agotado | bajo | ok — lo usa la etiqueta de color del listado. */
    public function getEstadoAttribute(): string
    {
        if ((float) $this->stock <= 0) {
            return 'agotado';
        }

        return (float) $this->stock <= (float) $this->min_stock ? 'bajo' : 'ok';
    }
}
