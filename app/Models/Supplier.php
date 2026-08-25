<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ConImagen;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use Auditable, ConImagen, PerteneceAEmpresa, SoftDeletes;
    protected $fillable = [
        'empresa_id', 'name', 'tax_id', 'phone', 'email', 'address', 'contact_name', 'is_active', 'image_url',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Productos que se le compran a este proveedor. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Compras que se le han hecho a este proveedor. */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class)->latest('ordered_at');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);

        if ($t === '') {
            return $q;
        }

        return $q->where(fn (Builder $s) => $s->where('name', 'like', "%{$t}%")
            ->orWhere('tax_id', 'like', "%{$t}%")
            ->orWhere('contact_name', 'like', "%{$t}%")
            ->orWhere('email', 'like', "%{$t}%"));
    }
}
