<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** VD-VTA-002 · Una solicitud de anulación de venta (RN-43). */
class SaleVoidRequest extends Model
{
    use Auditable, PerteneceATienda;

    public const PENDIENTE = 'pendiente';
    public const APROBADA  = 'aprobada';
    public const RECHAZADA = 'rechazada';

    protected $fillable = ['tienda_id', 'sale_id', 'requested_by', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason'];

    protected $attributes = ['status' => self::PENDIENTE];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('status', self::PENDIENTE);
    }

    /** @return array{0: string, 1: string} [etiqueta, tono] */
    public function getEstadoEtiquetaAttribute(): array
    {
        return match ($this->status) {
            self::APROBADA  => ['Aprobada', 'ok'],
            self::RECHAZADA => ['Rechazada', 'bad'],
            default         => ['Pendiente', 'warn'],
        };
    }
}
