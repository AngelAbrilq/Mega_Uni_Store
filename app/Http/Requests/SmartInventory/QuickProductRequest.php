<?php

namespace App\Http\Requests\SmartInventory;

use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta rápida de un producto desde un renglón de factura que no está en el
 * catálogo. Pide lo mínimo: el resto (categoría, foto, stock mínimo…) se
 * completa después en la ficha del producto.
 */
class QuickProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('productos.crear');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Sin whereNull('deleted_at'): el índice único (empresa_id, sku) también
        // cuenta los productos borrados; validarlos evita un error 500 al guardar.
        $unico = fn (string $col) => DelNegocio::unico('products', $col);

        return [
            'name'    => ['required', 'string', 'max:200'],
            'sku'     => ['nullable', 'string', 'max:50', $unico('sku')],
            'barcode' => ['nullable', 'string', 'max:50', $unico('barcode')],
            'price'   => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cost'    => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'  => 'El producto necesita un nombre.',
            'price.required' => 'Indica el precio de venta.',
            'sku.unique'     => 'Ya existe un producto con ese SKU.',
            'barcode.unique' => 'Ya existe un producto con ese código de barras.',
        ];
    }
}
