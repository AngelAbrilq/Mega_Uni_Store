<?php

namespace App\Http\Requests\Catalogo;

use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de Category (H-03). Las reglas `unique`/`exists` van
 * acotadas al negocio activo (H-F10 / H-F11). El permiso de crear o
 * editar lo exige el middleware de la ruta.
 */
class GuardarCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', DelNegocio::unico('categories', 'name')->ignore($this->route('category')?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id'   => ['nullable', DelNegocio::existe('categories'), Rule::notIn([$this->route('category')?->id])],
            'imagen'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'  => 'La categoría necesita un nombre.',
            'name.unique'    => 'Ya existe una categoría con ese nombre.',
            'parent_id.not_in' => 'Una categoría no puede ser su propia categoría padre.',
            'imagen.image'   => 'El archivo debe ser una imagen.',
            'imagen.mimes'   => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'     => 'La imagen no puede pesar más de 2 MB.',
        ];
    }
}
