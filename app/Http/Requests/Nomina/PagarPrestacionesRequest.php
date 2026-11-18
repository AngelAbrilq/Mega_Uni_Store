<?php

namespace App\Http\Requests\Nomina;

use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Marcar prestaciones como pagadas: solo las de un local del negocio activo. */
class PagarPrestacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', Rule::exists('social_benefits', 'id')->whereIn('tienda_id', Contexto::tiendaIds() ?? [])],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Selecciona al menos una prestación.',
            'ids.*.exists' => 'Una de las prestaciones seleccionadas no existe.',
        ];
    }
}
