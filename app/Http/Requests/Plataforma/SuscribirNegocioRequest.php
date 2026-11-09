<?php

namespace App\Http\Requests\Plataforma;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel de plataforma: suscribir un negocio a un plan. El permiso lo exige el middleware de la ruta (H-03)
 */
class SuscribirNegocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // SA-PLN-001: un plan inactivo no se asigna, salvo que ya sea el suyo.
            'plan_id'        => ['nullable', 'integer', Rule::exists('planes', 'id')->where(
                fn ($q) => $q->where('is_active', true)->orWhere('id', $this->route('empresa')?->plan_id)
            )],
            'periodo'        => ['required', Rule::in(['mensual', 'anual'])],
            'precio_pactado' => ['nullable', 'numeric', 'min:0'],
            'tiendas'        => ['nullable', 'integer', 'min:1', 'max:99'],
            'motivo'         => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'periodo.required' => 'Di si paga mes a mes o el año.',
            'plan_id.exists'   => 'Ese plan ya no se vende.',
        ];
    }
}
