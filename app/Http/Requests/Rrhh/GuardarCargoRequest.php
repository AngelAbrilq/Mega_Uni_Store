<?php

namespace App\Http\Requests\Rrhh;

use Illuminate\Foundation\Http\FormRequest;

/** AT-CGO-001 · Cargo del local con nivel jerárquico (1 = el más alto). */
class GuardarCargoRequest extends FormRequest
{
    use ReglasDelLocal;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:60', $this->unicoEnLocal('positions', 'name')->ignore($this->route('position')?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'level'       => ['required', 'integer', 'min:1', 'max:10'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Este local ya tiene un cargo con ese nombre.', 'level.max' => 'El nivel va de 1 (más alto) a 10.'];
    }
}
