<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ConImagen;
use App\Models\Concerns\ConSlug;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use Auditable, ConImagen, ConSlug, PerteneceAEmpresa, SoftDeletes;
    protected $fillable = ['empresa_id', 'name', 'slug', 'description', 'parent_id', 'image_url',
                           'is_active', 'is_public'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_public' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Productos que pertenecen a esta categoría.
     * La usa el panel para contar productos por categoría.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }



    /** "Papelería › Cuadernos" */
    public function getPathAttribute(): string
    {
        return $this->parent ? $this->parent->name . ' › ' . $this->name : $this->name;
    }
}
