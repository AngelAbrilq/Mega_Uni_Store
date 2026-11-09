<?php

namespace App\Http\Requests\Rrhh;

use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/** AT-EMP-001 · Alta y edición de un empleado del local activo. */
class GuardarEmpleadoRequest extends FormRequest
{
    use ReglasDelLocal;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code'    => mb_strtoupper(trim((string) $this->input('code'))),
            'user_id' => $this->input('user_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        $empleado = $this->route('employee');
        $alta     = $empleado === null;

        return [
            'code'             => ['required', 'string', 'max:20', 'alpha_dash', $this->unicoEnLocal('employees', 'code')->ignore($empleado?->id)],
            'user_id'          => ['nullable', 'integer', DelNegocio::usuario(), $this->unicoEnLocal('employees', 'user_id')->ignore($empleado?->id)],
            'first_name'       => ['required', 'string', 'max:80'],
            'last_name'        => ['nullable', 'string', 'max:80'],
            'document_type'    => ['nullable', 'in:CC,CE,TI,PAS,PPT'],
            'document_number'  => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z-]+$/'],
            'phone'            => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +()-]+$/'],
            'email'            => ['nullable', 'email', 'max:120'],
            'hire_date'        => ['required', 'date', 'before_or_equal:today'],
            'base_salary'      => ['required', 'numeric', 'min:0', 'max:999999999'],
            // Solo en el alta: cargo, área principal y horario de entrada.
            'position_id'      => $alta ? ['nullable', 'integer', $this->existeEnLocal('positions')->where('is_active', true)] : ['prohibited'],
            'department_id'    => $alta ? ['nullable', 'integer', $this->existeEnLocal('departments')->where('is_active', true)] : ['prohibited'],
            'work_schedule_id' => $alta ? ['nullable', 'integer', $this->existeEnLocal('work_schedules')->where('is_active', true)] : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'          => 'Asígnale un código de empleado.',
            'code.unique'            => 'El código de empleado ya existe en esta tienda.',
            'user_id.unique'         => 'Este usuario ya está registrado como empleado en esta tienda.',
            'user_id.exists'         => 'Ese usuario no es de este negocio.',
            'hire_date.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'phone.regex'            => 'El teléfono solo lleva números, espacios, +, ( ) y -.',
        ];
    }
}
