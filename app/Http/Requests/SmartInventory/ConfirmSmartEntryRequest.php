<?php

namespace App\Http\Requests\SmartInventory;

use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfirmSmartEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('compras.crear');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Rule::exists ignora los global scopes de Eloquent: el filtro por
        // empresa se pone a mano o un usuario podría referenciar IDs de otro inquilino.
        $empresaId = Contexto::empresaId();

        return [
            'supplier_id'    => ['required', 'integer', Rule::exists('suppliers', 'id')->where('empresa_id', $empresaId)->whereNull('deleted_at')],
            'numero_factura' => ['nullable', 'string', 'max:50'],
            'fecha'          => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'imagen_ref'     => ['nullable', 'string', 'max:255', 'regex:#^smart-inventory/[\w\-/]+\.(jpe?g|png|webp)$#'],

            'items'                          => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id'             => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('empresa_id', $empresaId)->whereNull('deleted_at')],
            'items.*.cantidad_cajas'         => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.cantidad_unidades'      => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.costo_total_compra'     => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.precio_venta_unitario'  => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.iva_porcentaje'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.actualizar_costo'       => ['sometimes', 'boolean'],
            'items.*.actualizar_precio'      => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $wantsPrice = collect($this->input('items', []))->contains(fn ($i) => filter_var($i['actualizar_precio'] ?? false, FILTER_VALIDATE_BOOL));
                if ($wantsPrice && ! $this->user()->can('productos.editar')) {
                    $validator->errors()->add('items', 'No tienes permiso para cambiar precios de venta.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'supplier_id.required'          => 'Selecciona el proveedor.',
            'items.*.product_id.required'   => 'Asocia cada renglón a un producto del catálogo.',
            'items.*.product_id.distinct'   => 'Hay dos renglones con el mismo producto; únelos en uno.',
            'items.*.cantidad_cajas.gt'     => 'La cantidad de cajas debe ser mayor que cero.',
            'items.*.cantidad_unidades.gt'  => 'Las unidades por caja deben ser mayores que cero.',
        ];
    }
}
