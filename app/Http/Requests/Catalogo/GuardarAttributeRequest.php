<?php

namespace App\Http\Requests\Catalogo;

use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de Attribute (H-03). Las reglas `unique`/`exists` van
 * acotadas al negocio activo (H-F10 / H-F11). El permiso de crear o
 * editar lo exige el middleware de la ruta.
 */
class GuardarAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', DelNegocio::unico('attributes', 'name')->ignore($this->route('attribute')?->id)],
            'type' => ['required', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El atributo necesita un nombre (Color, Talla…).',
            'name.unique'   => 'Ya existe un atributo con ese nombre.',
            'type.required' => 'Indica cómo se captura el valor.',
        ];
    }
}
