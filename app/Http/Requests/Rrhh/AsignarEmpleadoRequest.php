<?php

namespace App\Http\Requests\Rrhh;

use Illuminate\Foundation\Http\FormRequest;

/** Cargo, área u horario para un empleado (AT-CGO-001 · TBL-EHA-001 · AT-HOR-001). */
class AsignarEmpleadoRequest extends FormRequest
{
    use ReglasDelLocal;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empleado = $this->route('employee');

        return [
            'tipo'             => ['required', 'in:cargo,area,horario'],
            'position_id'      => ['required_if:tipo,cargo', 'nullable', 'integer', $this->existeEnLocal('positions')->where('is_active', true)],
            'department_id'    => ['required_if:tipo,area', 'nullable', 'integer', $this->existeEnLocal('departments')->where('is_active', true)],
            'is_primary'       => ['sometimes', 'boolean'],
            'work_schedule_id' => ['required_if:tipo,horario', 'nullable', 'integer', $this->existeEnLocal('work_schedules')->where('is_active', true)],
            'desde'            => ['nullable', 'date', 'after_or_equal:' . $empleado?->hire_date?->toDateString()],
        ];
    }

    public function messages(): array
    {
        return [
            'position_id.required_if'      => 'Escoge el cargo.',
            'department_id.required_if'    => 'Escoge el área.',
            'work_schedule_id.required_if' => 'Escoge el horario.',
            'desde.after_or_equal'         => 'No puede empezar antes de la fecha de ingreso.',
        ];
    }
}
