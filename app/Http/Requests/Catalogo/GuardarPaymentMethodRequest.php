<?php

namespace App\Http\Requests\Catalogo;

use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de PaymentMethod (H-03). Las reglas `unique`/`exists` van
 * acotadas al negocio activo (H-F10 / H-F11). El permiso de crear o
 * editar lo exige el middleware de la ruta.
 */
class GuardarPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', DelNegocio::unico('payment_methods', 'name')->ignore($this->route('payment_method')?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El medio de pago necesita un nombre.',
            'name.unique'   => 'Ya existe un medio de pago con ese nombre.',
        ];
    }
}
