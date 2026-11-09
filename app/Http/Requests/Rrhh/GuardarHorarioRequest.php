<?php

namespace App\Http\Requests\Rrhh;

use Illuminate\Foundation\Http\FormRequest;

/** AT-HOR-001 · Horario del local (sus franjas se agregan aparte). */
class GuardarHorarioRequest extends FormRequest
{
    use ReglasDelLocal;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:60', $this->unicoEnLocal('work_schedules', 'name')->ignore($this->route('work_schedule')?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Este local ya tiene un horario con ese nombre.'];
    }
}
