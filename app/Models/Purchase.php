<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use Auditable, PerteneceATienda, SoftDeletes;
    public const BORRADOR = 'borrador';
    public const RECIBIDA = 'recibida';
    public const ANULADA  = 'anulada';

    protected $fillable = [
        'tienda_id', 'number', 'invoice_number', 'supplier_id', 'user_id', 'received_by',
        'subtotal', 'tax_total', 'total', 'status', 'notes',
        'ordered_at', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'    => 'decimal:2',
            'tax_total'   => 'decimal:2',
            'total'       => 'decimal:2',
            'ordered_at'  => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeSearch(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);

        if ($t === '') {
            return $q;
        }

        return $q->where(fn (Builder $s) => $s->where('number', 'like', "%{$t}%")
            ->orWhere('invoice_number', 'like', "%{$t}%")
            ->orWhereHas('supplier', fn ($p) => $p->where('name', 'like', "%{$t}%")));
    }

    public function getRecibidaAttribute(): bool
    {
        return $this->status === self::RECIBIDA;
    }

    public function getEditableAttribute(): bool
    {
        return $this->status === self::BORRADOR;
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->status) {
            self::BORRADOR => 'Borrador',
            self::RECIBIDA => 'Recibida',
            self::ANULADA  => 'Anulada',
            default        => ucfirst((string) $this->status),
        };
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
        return \App\Services\ConsecutivoService::siguiente('compras');
    }
}
