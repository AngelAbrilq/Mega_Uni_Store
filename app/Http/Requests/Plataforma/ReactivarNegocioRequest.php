<?php

namespace App\Http\Requests\Plataforma;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel de plataforma: reactivar un negocio suspendido. El permiso lo exige el middleware de la ruta (H-03)
 */
class ReactivarNegocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists('planes', 'id')->where('is_active', true)],
            'periodo' => ['required', Rule::in(['mensual', 'anual'])],
            'motivo'  => ['nullable', 'string', 'max:200'],
        ];
    }
}
