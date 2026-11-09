<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use Auditable, PerteneceATienda;

    /** Registrada por quien no puede aprobar: reserva la cantidad, no mueve inventario. */
    public const PENDIENTE = 'pendiente';

    /** Aplicada: la mercancía volvió al inventario (si `restock`). */
    public const APROBADA = 'aprobada';

    /** Descartada: la cantidad reservada vuelve a estar disponible para devolver. */
    public const RECHAZADA = 'rechazada';

    /** Las que cuentan contra la venta: impiden anularla y ocupan cantidad (RN-25). */
    public const VIGENTES = [self::PENDIENTE, self::APROBADA];

    protected $fillable = [
        'tienda_id', 'number', 'sale_id', 'user_id', 'subtotal', 'tax_total', 'total',
        'reason', 'restock', 'returned_at', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    /** @var array<string, mixed> Igual al DEFAULT de la columna. */
    protected $attributes = [
        'status' => self::APROBADA,
    ];

    protected function casts(): array
    {
        return [
            'subtotal'    => 'decimal:2',
            'tax_total'   => 'decimal:2',
            'total'       => 'decimal:2',
            'restock'     => 'boolean',
            'returned_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Quién la aprobó o la rechazó. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function scopeVigentes(Builder $q): Builder
    {
        return $q->whereIn('status', self::VIGENTES);
    }

    public function scopeAprobadas(Builder $q): Builder
    {
        return $q->where('status', self::APROBADA);
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('status', self::PENDIENTE);
    }

    public function getPendienteAttribute(): bool
    {
        return $this->status === self::PENDIENTE;
    }

    /** Texto y tono del distintivo de estado en las vistas. */
    public function getEstadoEtiquetaAttribute(): array
    {
        return match ($this->status) {
            self::PENDIENTE => ['Pendiente', 'warn'],
            self::RECHAZADA => ['Rechazada', 'bad'],
            default         => ['Aprobada', 'ok'],
        };
    }

    public function scopeSearch(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);

        if ($t === '') {
            return $q;
        }

        return $q->where(fn (Builder $s) => $s->where('number', 'like', "%{$t}%")
            ->orWhereHas('sale', fn ($v) => $v->where('number', 'like', "%{$t}%")));
    }

    /**
     * Siguiente consecutivo del documento.
     *
     * Antes salía de `max(id) + 1`, que se rompe si dos personas registran
     * al mismo tiempo. Ahora lo entrega ConsecutivoService, que incrementa
     * una fila de contador y deja que la base serialice.
     */
    public static function siguienteNumero(): string
    {
        return \App\Services\ConsecutivoService::siguiente('devoluciones');
    }
}
