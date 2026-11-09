<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use Auditable, PerteneceATienda, SoftDeletes;
    public const PAGADA  = 'pagada';
    public const ANULADA = 'anulada';

    protected $fillable = [
        'tienda_id', 'number', 'customer_id', 'user_id', 'cash_session_id',
        'subtotal', 'discount_total', 'tax_total', 'total',
        'paid_total', 'change_amount', 'cost_total', 'profit_total',
        'status', 'notes', 'sold_at',
        'voided_by', 'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'       => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total'      => 'decimal:2',
            'total'          => 'decimal:2',
            'paid_total'     => 'decimal:2',
            'change_amount'  => 'decimal:2',
            'cost_total'     => 'decimal:2',
            'profit_total'   => 'decimal:2',
            'sold_at'        => 'datetime',
            'voided_at'      => 'datetime',
        ];
    }

    /* ─────────────────────── Relaciones ─────────────────────── */

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** CL-PUN-001 · Puntos que ganó (o se le devolvieron a) esta venta. */
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /** VD-VTA-002 · Solicitudes de anulación (RN-43). */
    public function voidRequests(): HasMany
    {
        return $this->hasMany(SaleVoidRequest::class)->latest('id');
    }

    /** La solicitud de anulación que espera aprobación, si hay. */
    public function getAnulacionPendienteAttribute(): ?SaleVoidRequest
    {
        return $this->voidRequests->firstWhere('status', SaleVoidRequest::PENDIENTE);
    }

    /** TBL-VCU-001 · Cupones aplicados en esta venta. */
    public function coupons(): HasMany
    {
        return $this->hasMany(SaleCoupon::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class)->latest('id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'source_id')
            ->where('source_type', self::class);
    }

    /* ─────────────────────── Consultas ─────────────────────── */

    /** Solo las ventas que cuentan para los reportes. */
    public function scopePagadas(Builder $q): Builder
    {
        return $q->where('status', self::PAGADA);
    }

    public function scopeEntre(Builder $q, $desde, $hasta): Builder
    {
        return $q->whereBetween('sold_at', [$desde, $hasta]);
    }

    public function scopeSearch(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);

        if ($t === '') {
            return $q;
        }

        return $q->where(fn (Builder $s) => $s->where('number', 'like', "%{$t}%")
            ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', "%{$t}%")
                                                  ->orWhere('last_name', 'like', "%{$t}%")
                                                  ->orWhere('document_number', 'like', "%{$t}%")));
    }

    /* ─────────────────────── Ayudas ─────────────────────── */

    /** Total devuelto al cliente sobre esta venta. */
    public function getTotalDevueltoAttribute(): float
    {
        return (float) $this->returns()->aprobadas()->sum('total');
    }

    /** ¿Queda algo por devolver? */
    /**
     * Lo que el cliente todavía debe porque un pago fue rechazado (RN-32).
     *
     * Lo recibido de verdad son los pagos no rechazados menos el vuelto que
     * se le devolvió. Si eso no alcanza el total, la diferencia es saldo.
     */
    public function getSaldoPendienteAttribute(): float
    {
        $pagos  = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        $valido = (float) $pagos->where('status', '!=', SalePayment::RECHAZADO)->sum('amount');

        return round(max(0, (float) $this->total - ($valido - (float) $this->change_amount)), 2);
    }

    /** ¿Tiene algún pago esperando verificación? */
    public function getPagoPorVerificarAttribute(): bool
    {
        $pagos = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        return $pagos->contains('status', SalePayment::PENDIENTE);
    }

    public function getTieneDevolucionAttribute(): bool
    {
        // Una rechazada no cuenta: la venta vuelve a poderse anular (RN-25, RN-30).
        return $this->returns()->vigentes()->exists();
    }

    public function getAnuladaAttribute(): bool
    {
        return $this->status === self::ANULADA;
    }

    /** Margen de la venta en porcentaje. */
    public function getMarginAttribute(): float
    {
        $t = (float) $this->total;

        return $t > 0 ? (float) $this->profit_total / $t * 100 : 0.0;
    }

    /**
     * Siguiente consecutivo: V-000001, V-000002…
     * Se calcula dentro de la transacción de la venta, con la fila
     * bloqueada, para que dos cajeros simultáneos no repitan número.
     */
    /**
     * Siguiente consecutivo del documento.
     *
     * Antes salía de `max(id) + 1`, que se rompe si dos personas registran
     * al mismo tiempo. Ahora lo entrega ConsecutivoService, que incrementa
     * una fila de contador y deja que la base serialice.
     */
    public static function siguienteNumero(): string
    {
        return \App\Services\ConsecutivoService::siguiente('ventas');
    }
}
