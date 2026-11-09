<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cupón de descuento del negocio (AT-CUP-001 · RN-35).
 */
class Coupon extends Model
{
    use Auditable, PerteneceAEmpresa, SoftDeletes;

    public const PORCENTAJE = 'percentage';
    public const FIJO       = 'fixed';
    public const TIPOS      = [self::PORCENTAJE => 'Porcentaje', self::FIJO => 'Valor fijo'];

    protected $fillable = [
        'empresa_id', 'code', 'description', 'type', 'value', 'max_discount', 'min_purchase',
        'max_uses', 'used_count', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $attributes = ['is_active' => true, 'used_count' => 0, 'type' => self::PORCENTAJE];

    protected function casts(): array
    {
        return [
            'value'        => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_uses'     => 'integer',
            'used_count'   => 'integer',
            'starts_at'    => 'datetime',
            'ends_at'      => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    /** El código siempre se guarda en mayúsculas y sin espacios. */
    public function setCodeAttribute(?string $valor): void
    {
        $this->attributes['code'] = self::normalizar((string) $valor);
    }

    public static function normalizar(string $codigo): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', trim($codigo)));
    }

    public function usos(): HasMany
    {
        return $this->hasMany(SaleCoupon::class);
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Por qué este cupón NO se puede usar sobre esa base, o null si sí.
     * Las mismas palabras las ve el cajero en el POS y el servidor al cobrar.
     */
    public function motivoParaNoAplicar(float $base): ?string
    {
        return match (true) {
            ! $this->is_active || $this->trashed()           => "El cupón {$this->code} no está activo.",
            $this->starts_at && now()->lt($this->starts_at)  => "El cupón {$this->code} empieza a valer el " . $this->starts_at->format('d/m/Y') . '.',
            $this->ends_at && now()->gt($this->ends_at)      => "El cupón {$this->code} venció el " . $this->ends_at->format('d/m/Y') . '.',
            $this->max_uses !== null && $this->used_count >= $this->max_uses
                                                             => "El cupón {$this->code} ya se usó las {$this->max_uses} veces permitidas.",
            $this->min_purchase !== null && $base + 0.009 < (float) $this->min_purchase
                                                             => "El cupón {$this->code} exige una compra mínima de $" . number_format((float) $this->min_purchase, 0, ',', '.') . '.',
            default                                          => null,
        };
    }

    /** Cuánto descuenta sobre la base (antes de impuestos), con el tope y sin pasarse de la base. */
    public function calcularDescuento(float $base): float
    {
        $descuento = $this->type === self::FIJO
            ? (float) $this->value
            : $base * (float) $this->value / 100;

        if ($this->type === self::PORCENTAJE && $this->max_discount !== null) {
            $descuento = min($descuento, (float) $this->max_discount);
        }

        return round(max(0, min($descuento, $base)), 2);
    }

    /** «10 %» o «$5.000», para listados y recibos. */
    public function getEtiquetaAttribute(): string
    {
        return $this->type === self::FIJO
            ? '$' . number_format((float) $this->value, 0, ',', '.')
            : rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',') . ' %';
    }

    /** vigente | programado | vencido | agotado | inactivo */
    public function getEstadoAttribute(): string
    {
        return match (true) {
            ! $this->is_active                                         => 'inactivo',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'agotado',
            $this->ends_at && now()->gt($this->ends_at)                => 'vencido',
            $this->starts_at && now()->lt($this->starts_at)            => 'programado',
            default                                                    => 'vigente',
        };
    }
}
