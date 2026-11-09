<?php

namespace App\Http\Requests\Plataforma;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Panel de plataforma: extender la prueba gratuita. El permiso lo exige el middleware de la ruta (H-03)
 */
class ExtenderPruebaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'minutos' => ['required', 'integer', 'min:15', 'max:10080'],
        ];
    }
}
