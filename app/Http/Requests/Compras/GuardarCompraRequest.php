<?php

namespace App\Http\Requests\Compras;

use Illuminate\Support\Facades\Gate;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /purchases y PUT /purchases/{purchase} · Cabecera y renglones.
 * La regla «solo se edita en borrador» vive en PurchaseService.
 */
class GuardarCompraRequest extends FormRequest
{
    /** Los permisos `compras.crear` / `compras.editar` los exige el middleware. */
    /** H-04 · Al editar: desde el local de la compra (PurchasePolicy). */
    public function authorize(): bool
    {
        if ($compra = $this->route('purchase')) {
            Gate::authorize('update', $compra);
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id'         => ['required', 'integer', DelNegocio::existe('suppliers')],
            'invoice_number'      => ['nullable', 'string', 'max:50'],
            'ordered_at'          => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'integer', DelNegocio::existe('products')],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_cost'   => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.update_cost' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'supplier_id.required'      => 'Elige el proveedor.',
            'supplier_id.exists'        => 'El proveedor no existe en este negocio.',
            'items.required'            => 'Agrega al menos un producto a la compra.',
            'items.min'                 => 'Agrega al menos un producto a la compra.',
            'items.*.product_id.exists' => 'Uno de los productos no existe en este negocio.',
        ];
    }
}
