<?php

namespace App\Http\Requests\Nomina;

use App\Support\Contexto;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** NR-NOM-005 E1. */
class RegistrarHorasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tienda_id', Contexto::tiendaId())->whereNull('deleted_at')],
            'date'        => ['required', 'date', 'before_or_equal:today'],
            'hours'       => ['required', 'numeric', 'min:0.25', 'max:12'],
            'type'        => ['required', Rule::in(array_keys(ReglasLaborales::TIPOS_HORA))],
            'notes'       => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return ['hours.max' => 'No se registran más de 12 horas extra en un día.', 'employee_id.exists' => 'El empleado no es de este local.'];
    }

    public function attributes(): array
    {
        return ['employee_id' => 'empleado', 'date' => 'fecha', 'hours' => 'horas', 'type' => 'tipo'];
    }
}
