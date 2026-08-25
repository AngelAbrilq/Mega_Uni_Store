<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /**
     * La bitácora es de un negocio.
     *
     * Guarda copia de lo que cambió —nombres, precios, clientes— así que
     * sin este filtro la pantalla de Auditoría sería la ventana por donde
     * un cliente vería el negocio del otro.
     */
    use PerteneceAEmpresa;

    /** Solo tiene created_at: una bitácora no se edita. */
    public const UPDATED_AT = null;

    /**
     * Interruptor para apagar la bitácora un rato.
     *
     * Lo usan los seeders: sembrar un catálogo de mil productos escribiría
     * mil filas de auditoría que no le interesan a nadie —nadie «creó» esos
     * productos, los puso el instalador— y multiplicaría por dos el tiempo
     * de la siembra.
     *
     * Se prefiere esto a `WithoutModelEvents` porque aquel apaga TODOS los
     * eventos del modelo, incluidos los que rellenan la empresa y la tienda.
     * Con los eventos apagados, un producto sembrado quedaría sin dueño.
     */
    public static bool $silencio = false;

    public static function silenciar(): void
    {
        self::$silencio = true;
    }

    public static function escuchar(): void
    {
        self::$silencio = false;
    }

    protected $fillable = [
        'empresa_id',
        'user_id', 'user_name', 'event', 'auditable_type', 'auditable_id',
        'auditable_label', 'old_values', 'new_values', 'ip', 'user_agent', 'url',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSearch(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);

        if ($t === '') {
            return $q;
        }

        return $q->where(fn (Builder $s) => $s->where('user_name', 'like', "%{$t}%")
            ->orWhere('auditable_label', 'like', "%{$t}%"));
    }

    /** "Producto", "Venta"… a partir del nombre de la clase. */
    public function getModeloAttribute(): string
    {
        $corto = class_basename((string) $this->auditable_type);

        return [
            'Product'       => 'Producto',
            'Category'      => 'Categoría',
            'Customer'      => 'Cliente',
            'Supplier'      => 'Proveedor',
            'Unit'          => 'Unidad',
            'Tax'           => 'Impuesto',
            'PaymentMethod' => 'Medio de pago',
            'Attribute'     => 'Atributo',
            'User'          => 'Usuario',
            'Sale'          => 'Venta',
            'Purchase'      => 'Compra',
            'CashSession'   => 'Turno de caja',
        ][$corto] ?? $corto;
    }

    public function getEventoLabelAttribute(): string
    {
        return match ($this->event) {
            'creado'      => 'Creó',
            'actualizado' => 'Modificó',
            'eliminado'   => 'Eliminó',
            'restaurado'  => 'Restauró',
            default       => ucfirst((string) $this->event),
        };
    }

    /**
     * Campos que cambiaron, con su valor antes y después.
     *
     * @return array<int, array{campo:string, antes:mixed, despues:mixed}>
     */
    public function getCambiosAttribute(): array
    {
        $antes   = $this->old_values ?? [];
        $despues = $this->new_values ?? [];
        $salida  = [];

        foreach ($despues as $campo => $valor) {
            $previo = $antes[$campo] ?? null;

            if ($previo == $valor) {
                continue;
            }

            $salida[] = [
                'campo'   => $this->etiqueta($campo),
                'antes'   => $previo,
                'despues' => $valor,
            ];
        }

        return $salida;
    }

    private function etiqueta(string $campo): string
    {
        return [
            'name' => 'Nombre', 'sku' => 'SKU', 'barcode' => 'Código de barras',
            'price' => 'Precio', 'cost' => 'Costo', 'stock' => 'Existencias',
            'min_stock' => 'Stock mínimo', 'is_active' => 'Activo',
            'description' => 'Descripción', 'image_url' => 'Imagen',
            'category_id' => 'Categoría', 'unit_id' => 'Unidad',
            'tax_id' => 'Impuesto', 'supplier_id' => 'Proveedor',
            'email' => 'Correo', 'phone' => 'Teléfono', 'address' => 'Dirección',
            'first_name' => 'Nombres', 'last_name' => 'Apellidos',
            'document_number' => 'Documento', 'rate' => 'Tarifa',
            'status' => 'Estado', 'total' => 'Total',
        ][$campo] ?? ucfirst(str_replace('_', ' ', $campo));
    }
}
