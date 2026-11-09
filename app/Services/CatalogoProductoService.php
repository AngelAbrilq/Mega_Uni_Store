<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Relaciones N:M del producto que llegan desde su formulario
 * (AT-PRI-001, TBL-TPL-001, TBL-PPA-001).
 *
 * Solo sincroniza lo que el formulario envió: una llamada que no trae
 * proveedores, impuestos o atributos (la creación rápida, Smart Inventory,
 * las pruebas viejas) no borra nada.
 */
class CatalogoProductoService
{
    /**
     * @param  array<string, mixed>  $datos  Datos validados del producto.
     */
    public function sincronizar(Product $producto, array $datos): void
    {
        DB::transaction(function () use ($producto, $datos) {
            $this->impuestos($producto, $datos);
            $this->proveedores($producto, $datos);
            $this->atributos($producto, $datos);
        });
    }

    /** Los adicionales, sin repetir el impuesto principal. */
    private function impuestos(Product $producto, array $datos): void
    {
        if (! array_key_exists('extra_taxes', $datos) && empty($datos['proveedores_presentes'])) {
            return;
        }

        $ids = collect($datos['extra_taxes'] ?? [])
            ->map('intval')
            ->reject(fn ($id) => $id === (int) $producto->tax_id)
            ->unique()
            ->values()
            ->all();

        $producto->extraTaxes()->sync($ids);
    }

    /**
     * Uno solo es el preferido, y es el que queda en `products.supplier_id`
     * (el «proveedor habitual» que ya usaban compras y Smart Inventory).
     * Si no se marcó ninguno y hay uno solo, ese es el preferido.
     */
    private function proveedores(Product $producto, array $datos): void
    {
        if (empty($datos['proveedores_presentes'])) {
            return;
        }

        $filas = collect($datos['proveedores'] ?? [])
            ->filter(fn ($f) => ! empty($f['supplier_id']))
            ->keyBy(fn ($f) => (int) $f['supplier_id']);

        $preferido = (int) ($datos['proveedor_preferido'] ?? 0);

        if (! $filas->has($preferido)) {
            $preferido = $filas->count() === 1 ? (int) $filas->keys()->first() : 0;
        }

        $producto->suppliers()->sync($filas->map(fn ($f, $id) => [
            'supplier_sku'   => $f['supplier_sku'] ?? null ?: null,
            'supplier_price' => isset($f['supplier_price']) && $f['supplier_price'] !== '' ? round((float) $f['supplier_price'], 2) : null,
            'lead_time_days' => isset($f['lead_time_days']) && $f['lead_time_days'] !== '' ? (int) $f['lead_time_days'] : null,
            'is_preferred'   => $id === $preferido,
        ])->all());

        if ((int) $producto->supplier_id !== $preferido) {
            $producto->forceFill(['supplier_id' => $preferido ?: null])->save();
        }
    }

    /** Un atributo vacío se quita del producto. */
    private function atributos(Product $producto, array $datos): void
    {
        if (! array_key_exists('atributos', $datos)) {
            return;
        }

        $producto->attributeValues()->sync(
            collect($datos['atributos'])
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '')
                ->mapWithKeys(fn ($v, $id) => [(int) $id => ['value' => $v]])
                ->all()
        );
    }
}
