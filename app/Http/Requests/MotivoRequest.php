<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Un motivo obligatorio (VD-VTA-002: pedir o rechazar una anulación). El
 * permiso lo exige la ruta; la policy, el controlador.
 */
class MotivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Explica el motivo.',
            'motivo.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
