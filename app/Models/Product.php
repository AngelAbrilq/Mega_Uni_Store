<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ConImagen;
use App\Models\Concerns\ConSlug;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\Contexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, ConImagen, ConSlug, PerteneceAEmpresa, SoftDeletes;

    /**
     * La existencia del local actual viaja siempre con el producto.
     *
     * Es una decisión deliberada: hay 92 líneas en el sistema que dicen
     * `$producto->stock`, y todas siguen funcionando porque el accesor lee
     * de aquí. Sin este `$with`, cada una de esas líneas dispararía una
     * consulta propia — el problema N+1 clásico, que en una tabla de 50
     * productos son 50 consultas de más.
     */
    protected $with = ['existencia'];
    protected $fillable = [
        'empresa_id', 'name', 'slug', 'sku', 'description', 'barcode', 'image_url',
        'category_id', 'unit_id', 'tax_id', 'supplier_id',
        'price', 'cost', 'is_active', 'is_public',
        // En nulo = no es un servicio que se agenda. Con un número, aparece
        // en la agenda y ese número es lo que dura la cita por omisión.
        'duracion_minutos',
        'created_by', 'updated_by',
    ];

    /**
     * Sin esto, price y cost llegan a la vista como cadenas ("7800.00")
     * y is_active como 1/0 en vez de true/false.
     */
    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'cost'      => 'decimal:2',
            // El total de todos los locales. La existencia de CADA local
            // vive en la tabla `existencias`; esto es la suma, y la mantiene
            // StockService.
            'stock_total'     => 'decimal:3',
            'min_stock_total' => 'decimal:3',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            // Entero o nulo, nunca "0": un servicio de cero minutos no
            // existe, y "0" desde un formulario vacío lo volvería agendable.
            'duracion_minutos' => 'integer',
        ];
    }

    /** ¿Es un servicio que se agenda, o una cosa que se vende y ya? */
    public function esServicio(): bool
    {
        return $this->duracion_minutos !== null && $this->duracion_minutos > 0;
    }

    /** Solo los que la agenda puede ofrecer. */
    public function scopeServicios(Builder $q): Builder
    {
        return $q->whereNotNull('duracion_minutos')->where('duracion_minutos', '>', 0);
    }

    /* ─────────────────────────── Relaciones ─────────────────────────── */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Usuario que registró el producto. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Último usuario que lo modificó. */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Movimientos de inventario de este producto (kardex). */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    /** Renglones de venta donde aparece. */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Renglones de compra donde aparece. */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** Este producto en todos los locales donde se vende. */
    public function existencias(): HasMany
    {
        return $this->hasMany(Existencia::class);
    }

    /**
     * Este producto en el local donde se está trabajando ahora.
     *
     * Si no hay fila, este local no vende este producto: el accesor de
     * `stock` devuelve 0 y las consultas que piden `enTienda()` lo dejan
     * fuera del punto de venta.
     */
    public function existencia(): HasOne
    {
        $tiendaId = Contexto::tiendaId();

        return $this->hasOne(Existencia::class)
            ->where('existencias.tienda_id', $tiendaId ?? 0);
    }

    /* ─────────────────────────── Consultas ─────────────────────────── */

    /** Solo los productos que se pueden vender. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Productos que llegaron al punto de reorden EN ESTE LOCAL. */
    public function scopeLowStock(Builder $q): Builder
    {
        return $q->whereHas('existencia', fn (Builder $e) => $e->bajoMinimo());
    }

    /** Sin una sola unidad en este local. */
    public function scopeAgotados(Builder $q): Builder
    {
        return $q->whereHas('existencia', fn (Builder $e) => $e->agotado());
    }

    /** Los que este local vende: tiene fila y está activa. */
    public function scopeEnTienda(Builder $q, ?int $tiendaId = null): Builder
    {
        $tiendaId ??= Contexto::tiendaId();

        return $q->whereHas('existencias', fn (Builder $e) => $e
            ->where('tienda_id', $tiendaId ?? 0)
            ->where('activo', true));
    }

    /**
     * Ordenar por existencia obliga a unirse con la otra tabla: la columna
     * ya no vive aquí. El `select` explícito evita que las columnas de
     * `existencias` pisen las del producto —las dos tienen `id`— y el
     * `leftJoin` deja pasar los que no se venden en este local, que salen
     * al final con existencia nula.
     */
    public function scopeOrdenPorExistencia(Builder $q, string $direccion = 'asc'): Builder
    {
        $tiendaId = Contexto::tiendaId() ?? 0;

        return $q->leftJoin('existencias', function ($j) use ($tiendaId) {
                $j->on('existencias.product_id', '=', 'products.id')
                  ->where('existencias.tienda_id', '=', $tiendaId);
            })
            ->select('products.*')
            ->orderBy('existencias.stock', $direccion);
    }

    /** Búsqueda por nombre, SKU o código de barras. */
    public function scopeSearch(Builder $q, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $q;
        }

        return $q->where(function (Builder $sub) use ($termino) {
            $sub->where('name', 'like', "%{$termino}%")
                ->orWhere('sku', 'like', "%{$termino}%")
                ->orWhere('barcode', 'like', "%{$termino}%");
        });
    }

    /**
     * Lo que puede ver alguien de la calle.
     *
     * Consulta aparte a propósito: la tienda pública NUNCA reutiliza las
     * consultas del panel. Si mañana se agrega un campo interno y el
     * catálogo público hereda la consulta del panel, ese campo se filtra
     * sin que nadie se dé cuenta. Aquí se dice explícitamente qué sale.
     */
    public function scopePublico(Builder $q): Builder
    {
        return $q->where('is_active', true)
                 ->where('is_public', true);
    }

    /**
     * Cantidad escrita como la lee una persona: «180», no «180,000», y
     * «0,5» cuando de verdad hay medio kilo. Los ceros de relleno sobran.
     */
    public static function cantidad($valor, int $decimales = 3): string
    {
        $texto = number_format((float) $valor, $decimales, ',', '.');

        return str_contains($texto, ',') ? rtrim(rtrim($texto, '0'), ',') : $texto;
    }

    /* ──────────────────── Existencias del local actual ────────────────────
       Estos accesores son los que hacen que las 92 líneas que dicen
       `$producto->stock` sigan diciendo la verdad después de la mudanza.
       Antes leían una columna; ahora leen la fila del local en el que se
       está trabajando. Para quien escribió esas líneas, nada cambió.

       La columna vieja sigue existiendo con otro nombre —`stock_total`—
       y guarda la suma de todos los locales. Renombrarla fue a propósito:
       cualquier consulta que se me haya escapado revienta con «Unknown
       column 'stock'» en vez de devolver un número equivocado en silencio.
       ──────────────────────────────────────────────────────────────────── */

    /** Cuánto hay de esto EN ESTE LOCAL. */
    public function getStockAttribute(): float
    {
        /**
         * Primero lo que ya venga en el atributo.
         *
         * `products` no tiene columna `stock` desde que la existencia es
         * por local, así que este atributo solo existe si alguien lo puso
         * a propósito. Dos casos, y los dos legítimos:
         *
         *   1. Una consulta que se une con `existencias` y selecciona su
         *      stock. Sin esta línea, el accessor ignoraría ese dato ya
         *      traído y dispararía OTRA consulta por cada fila.
         *
         *   2. Un producto armado en memoria para calcular —lo que hacen
         *      las pruebas de cálculo y cualquier simulación—. Sin esto,
         *      `$p->stock = 180` no serviría de nada y el valor en bodega
         *      daría cero.
         *
         * Y si no viene, se pregunta a la existencia del local activo, que
         * es el camino normal.
         */
        if (array_key_exists('stock', $this->attributes)) {
            return (float) $this->attributes['stock'];
        }

        return (float) ($this->existencia?->stock ?? 0);
    }

    /** Desde cuánto hay que reponer EN ESTE LOCAL. */
    public function getMinStockAttribute(): float
    {
        if (array_key_exists('min_stock', $this->attributes)) {
            return (float) $this->attributes['min_stock'];
        }

        return (float) ($this->existencia?->min_stock ?? 0);
    }

    /**
     * El precio de este local, o el del producto si no puso el suyo.
     *
     * Es el que tiene que usar el punto de venta. `$producto->price` sigue
     * siendo el precio base del catálogo, y sirve de referencia.
     */
    public function getPrecioTiendaAttribute(): float
    {
        return $this->existencia?->precioVenta() ?? (float) $this->price;
    }

    /** ¿Este local lo tiene activado para la venta? */
    public function getSeVendeAquiAttribute(): bool
    {
        return (bool) ($this->existencia?->activo ?? false);
    }

    public function getStockTextoAttribute(): string
    {
        return static::cantidad($this->stock);
    }

    public function getMinStockTextoAttribute(): string
    {
        return static::cantidad($this->min_stock);
    }

    /** Existencias que este local puede ofrecer (nunca un número negativo). */
    public function getDisponibleAttribute(): float
    {
        return max(0.0, (float) $this->stock);
    }

    /* ─────────────────────────── Cálculos ─────────────────────────── */

    /** Ganancia por unidad en pesos. */
    public function getProfitAttribute(): float
    {
        return (float) $this->price - (float) $this->cost;
    }

    /** Margen sobre el precio de venta, en porcentaje. */
    public function getMarginAttribute(): float
    {
        $precio = (float) $this->price;

        return $precio > 0 ? ($precio - (float) $this->cost) / $precio * 100 : 0.0;
    }

    /**
     * Cuánto dinero hay inmovilizado en este producto, en este local.
     *
     * Antes multiplicaba por `(int) $this->stock`, que truncaba: medio
     * kilo de café valía cero pesos. Con existencias decimales eso ya no
     * es aceptable.
     */
    public function getStockValueAttribute(): float
    {
        return (float) $this->cost * (float) $this->stock;
    }



    /** agotado | bajo | ok — lo usa la etiqueta de color del listado. */
    public function getStockStateAttribute(): string
    {
        if ((float) $this->stock <= 0) {
            return 'agotado';
        }

        return (float) $this->stock <= (float) $this->min_stock ? 'bajo' : 'ok';
    }
}
