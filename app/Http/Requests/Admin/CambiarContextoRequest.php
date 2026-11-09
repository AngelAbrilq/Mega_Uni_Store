<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambiar de negocio o de local activo. El permiso lo exige el middleware de la ruta (H-03)
 */
class CambiarContextoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'empresa_id' => ['required', 'integer'],
            'tienda_id'  => ['nullable', 'integer'],
        ];
    }
}
