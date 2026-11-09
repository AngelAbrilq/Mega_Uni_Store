<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    /**
     * Impuestos de un renglón (AT-PRI-001).
     *
     * Los porcentuales se calculan sobre la base ya descontada; los fijos,
     * por unidad. Devuelve lo que se congela en `sale_items`: los nombres
     * juntos, la suma de las tasas porcentuales y el total.
     *
     * @param  Collection<int, Tax>  $impuestos
     * @return array{nombre: ?string, tasa: float, monto: float}
     */
    public static function sobreRenglon(Collection $impuestos, float $base, float $cantidad): array
    {
        $tasa  = 0.0;
        $monto = 0.0;

        foreach ($impuestos as $impuesto) {
            if ($impuesto->type === 'fixed') {
                $monto += round((float) $impuesto->rate * $cantidad, 2);
            } else {
                $tasa  += (float) $impuesto->rate;
                $monto += round($base * (float) $impuesto->rate / 100, 2);
            }
        }

        return [
            'nombre' => $impuestos->isEmpty() ? null : $impuestos->pluck('name')->join(' + '),
            'tasa'   => round($tasa, 2),
            'monto'  => round($monto, 2),
        ];
    }

    /** "IVA 19%" o "Impoconsumo $2.000" segun el tipo. */
    public function getLabelAttribute(): string
    {
        return $this->type === 'fixed'
            ? $this->name . ' ($' . number_format((float) $this->rate, 0, ',', '.') . ')'
            : $this->name . ' (' . rtrim(rtrim(number_format((float) $this->rate, 2, ',', '.'), '0'), ',') . '%)';
    }
}
