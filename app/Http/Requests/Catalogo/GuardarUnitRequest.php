<?php

namespace App\Http\Requests\Catalogo;

use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de Unit (H-03). Las reglas `unique`/`exists` van
 * acotadas al negocio activo (H-F10 / H-F11). El permiso de crear o
 * editar lo exige el middleware de la ruta.
 */
class GuardarUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:80', DelNegocio::unico('units', 'name')->ignore($this->route('unit')?->id)],
            'symbol' => ['required', 'string', 'max:10', DelNegocio::unico('units', 'symbol')->ignore($this->route('unit')?->id)],
            'type'   => ['required', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'   => 'La unidad necesita un nombre (por ejemplo, Kilogramo).',
            'name.unique'     => 'Ya existe una unidad con ese nombre.',
            'symbol.required' => 'Escribe el símbolo corto (kg, L, und…).',
            'symbol.unique'   => 'Ese símbolo ya está en uso.',
            'type.required'   => 'Indica qué tipo de medida es.',
        ];
    }
}
