<?php

namespace App\Http\Requests\Nomina;

use App\Models\LeaveRequest;
use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** NR-NOM-006 E1/E3/E4. */
class RegistrarAusenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tienda_id', Contexto::tiendaId())->whereNull('deleted_at')],
            'type'        => ['required', Rule::in(array_keys(LeaveRequest::TIPOS))],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'notes'       => ['nullable', 'string', 'max:200'],
            'document'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return ['employee_id.exists' => 'El empleado no es de este local.', 'document.mimes' => 'El soporte debe ser PDF o imagen.'];
    }

    public function attributes(): array
    {
        return ['employee_id' => 'empleado', 'type' => 'tipo', 'start_date' => 'desde', 'end_date' => 'hasta', 'document' => 'soporte'];
    }
}
