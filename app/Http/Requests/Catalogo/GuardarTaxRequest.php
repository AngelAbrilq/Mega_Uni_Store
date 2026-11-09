<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Controllers\TaxController;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de Tax (H-03). Las reglas `unique`/`exists` van
 * acotadas al negocio activo (H-F10 / H-F11). El permiso de crear o
 * editar lo exige el middleware de la ruta.
 */
class GuardarTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:80', DelNegocio::unico('taxes', 'name')->ignore($this->route('tax')?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'rate'        => ['required', 'numeric', 'min:0', $this->esPorcentaje() ? 'max:100' : 'max:999.99'],
            'type'        => ['required', Rule::in(array_keys(TaxController::TIPOS))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El impuesto necesita un nombre (por ejemplo, IVA general).',
            'name.unique'   => 'Ya existe un impuesto con ese nombre.',
            'rate.required' => 'Indica la tarifa.',
            'rate.max'      => $this->esPorcentaje()
                ? 'Un porcentaje no puede pasar de 100.'
                : 'El valor fijo es demasiado alto.',
            'type.required' => 'Indica si es porcentaje o valor fijo.',
        ];
    }

    /** La tarifa es un porcentaje salvo que el tipo sea «fixed». */
    private function esPorcentaje(): bool
    {
        return $this->input('type') !== 'fixed';
    }
}
