<?php

namespace App\Http\Requests\Rrhh;

use Illuminate\Foundation\Http\FormRequest;

/** AT-ARE-001 · Área del local con su responsable (un empleado activo del local). */
class GuardarAreaRequest extends FormRequest
{
    use ReglasDelLocal;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:60', $this->unicoEnLocal('departments', 'name')->ignore($this->route('department')?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'manager_id'  => ['nullable', 'integer', $this->existeEnLocal('employees')->where('status', 'activo')->whereNull('deleted_at')],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Este local ya tiene un área con ese nombre.', 'manager_id.exists' => 'El responsable debe ser un empleado activo de este local.'];
    }
}
