<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends Model
{
    use Auditable, PerteneceAEmpresa;
    protected $fillable = ['empresa_id', 'name', 'description', 'rate', 'type', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate'      => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** "IVA 19%" o "Impoconsumo $2.000" segun el tipo. */
    public function getLabelAttribute(): string
    {
        return $this->type === 'fixed'
            ? $this->name . ' ($' . number_format((float) $this->rate, 0, ',', '.') . ')'
            : $this->name . ' (' . rtrim(rtrim(number_format((float) $this->rate, 2, ',', '.'), '0'), ',') . '%)';
    }
}
