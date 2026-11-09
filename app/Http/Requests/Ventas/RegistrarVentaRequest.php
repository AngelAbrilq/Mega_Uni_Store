<?php

namespace App\Http\Requests\Ventas;

use App\Models\Setting;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /pos · Datos de una venta.
 *
 * Solo valida forma y pertenencia al negocio. Las reglas de dinero
 * (precio, descuento máximo, stock, pago suficiente) viven en SaleService,
 * porque también las usa la agenda.
 */
class RegistrarVentaRequest extends FormRequest
{
    /** El permiso `ventas.crear` lo exige el middleware de la ruta. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // H-F02 · Configuración › Ventas › «Cliente obligatorio».
            'customer_id'               => [Setting::activo('venta.cliente_obligatorio') ? 'required' : 'nullable', DelNegocio::existe('customers')],
            'notes'                     => ['nullable', 'string', 'max:500'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.product_id'        => ['required', 'integer', DelNegocio::existe('products')],
            'items.*.quantity'          => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'        => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'          => ['nullable', 'numeric', 'min:0'],
            'cupon'                     => ['nullable', 'string', 'max:40'], // VD-CUP-001: lo valida SaleService
            'pagos'                     => ['required', 'array', 'min:1'],
            'pagos.*.payment_method_id' => ['required', 'integer', DelNegocio::existe('payment_methods')],
            'pagos.*.amount'            => ['required', 'numeric', 'min:0'],
            'pagos.*.reference'         => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required'             => 'Agrega al menos un producto antes de cobrar.',
            'pagos.required'             => 'Indica cómo se pagó la venta.',
            'customer_id.required'       => 'Esta tienda exige elegir un cliente antes de cobrar.',
            'customer_id.exists'         => 'El cliente no existe en este negocio.',
            'items.*.product_id.exists'  => 'Uno de los productos no existe en este negocio.',
            'pagos.*.payment_method_id.exists' => 'El medio de pago no existe en este negocio.',
        ];
    }
}
