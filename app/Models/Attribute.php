<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attribute extends Model
{
    use Auditable, PerteneceAEmpresa;
    protected $fillable = ['empresa_id', 'name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** TBL-PPA-001 · Productos que tienen un valor para este atributo. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'attribute_product')->withPivot('value')->withTimestamps();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
