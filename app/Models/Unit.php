<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use Auditable, PerteneceAEmpresa;
    protected $fillable = ['empresa_id', 'name', 'symbol', 'type'];

    /** Productos que se venden en esta unidad. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** "Unidad (und)" — para los selectores. */
    public function getLabelAttribute(): string
    {
        return $this->name . ' (' . $this->symbol . ')';
    }
}
