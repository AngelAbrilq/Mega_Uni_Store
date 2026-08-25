<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Un envío de mercancía de un local a otro.
 *
 * ── Cómo se aísla ──
 *
 * No usa `PerteneceATienda` como los demás documentos, y no es un olvido:
 * un traslado tiene DOS locales, y el trait filtra por uno solo. Aquí el
 * filtro es propio y mira los dos extremos — porque el traslado que sale
 * de la sede del centro le interesa igual a la del norte, que es la que
 * lo va a recibir.
 *
 * El resultado es el mismo aislamiento: los locales de la lista son los de
 * la empresa activa, así que un traslado ajeno no puede aparecer por
 * ninguno de los dos lados.
 */
class Traslado extends Model
{
    use Auditable;

    protected $table = 'traslados';

    protected $fillable = [
        'numero', 'product_id', 'desde_tienda_id', 'hacia_tienda_id',
        'cantidad', 'costo_unitario', 'user_id', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'decimal:3',
            'costo_unitario' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('empresa', function (Builder $consulta) {
            if (\App\Support\Contexto::sinFiltroActivo()) {
                return;
            }

            $tiendas = \App\Support\Contexto::tiendaIds();

            if ($tiendas === null) {
                return;
            }

            if ($tiendas === []) {
                $consulta->whereRaw('1 = 0');

                return;
            }

            // Los dos extremos tienen que ser de la empresa. Con `orWhere`
            // bastaría con que uno lo fuera, y eso dejaría ver traslados
            // ajenos que casualmente apunten a un local propio.
            $consulta->whereIn('traslados.desde_tienda_id', $tiendas)
                ->whereIn('traslados.hacia_tienda_id', $tiendas);
        });
    }

    /* ─────────────── Relaciones ─────────────── */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function desde(): BelongsTo
    {
        return $this->belongsTo(Tienda::class, 'desde_tienda_id');
    }

    public function hacia(): BelongsTo
    {
        return $this->belongsTo(Tienda::class, 'hacia_tienda_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Los dos movimientos de kardex que produjo. */
    public function movimientos(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source')->orderBy('id');
    }

    /* ─────────────── Consultas ─────────────── */

    /** Lo que entró o salió de un local. */
    public function scopeDeTienda(Builder $q, ?int $tiendaId): Builder
    {
        if (! $tiendaId) {
            return $q;
        }

        return $q->where(fn (Builder $s) => $s
            ->where('desde_tienda_id', $tiendaId)
            ->orWhere('hacia_tienda_id', $tiendaId));
    }

    public function scopeEntre(Builder $q, $desde, $hasta): Builder
    {
        return $q->whereBetween('created_at', [$desde, $hasta]);
    }

    /* ─────────────── Cálculos ─────────────── */

    /** Lo que valía la mercancía movida. */
    public function valor(): float
    {
        return round((float) $this->cantidad * (float) $this->costo_unitario, 2);
    }

    public function etiquetaAuditoria(): string
    {
        return $this->numero . ' · ' . ($this->product->name ?? 'producto');
    }
}
