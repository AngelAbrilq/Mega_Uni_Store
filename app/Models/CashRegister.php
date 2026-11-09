<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Caja registradora física de un local (AT-CAJ-001).
 *
 * Un turno de caja (CashSession) se abre EN una caja; la caja admite un
 * solo turno abierto a la vez (RN-31). Si el local no tiene cajas
 * definidas, los turnos siguen siendo solo por persona.
 */
class CashRegister extends Model
{
    use Auditable, PerteneceATienda;

    protected $fillable = ['tienda_id', 'name', 'code', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /** Solo las cajas que se pueden usar para abrir turno. */
    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** El turno abierto en esta caja, si hay uno. */
    public function turnoAbierto(): ?CashSession
    {
        return $this->sessions()->abierta()->with('user:id,name,last_name')->latest('opened_at')->first();
    }

    /** «Caja 1 · Principal» o solo el nombre si no tiene código. */
    public function getEtiquetaAttribute(): string
    {
        return $this->code ? "{$this->code} · {$this->name}" : $this->name;
    }
}
