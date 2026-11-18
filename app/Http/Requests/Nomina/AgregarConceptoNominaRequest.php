<?php

namespace App\Http\Requests\Nomina;

use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un concepto manual sobre una nómina en borrador (NR-NOM-003).
 * El permiso lo exige el controlador; aquí se cierra el paso a ids ajenos:
 * el empleado debe estar en ESTA nómina y el concepto ser del negocio (RN-26).
 */
class AgregarConceptoNominaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id'        => ['required', 'integer', Rule::exists('payroll_employees', 'employee_id')->where('payroll_id', $this->route('payroll')?->id)],
            'payroll_concept_id' => ['required', 'integer', DelNegocio::existe('payroll_concepts')->where('is_active', true)],
            'amount'             => ['required', 'numeric', 'min:1', 'max:999999999'],
            'quantity'           => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists'        => 'El empleado no está en esta nómina.',
            'payroll_concept_id.exists' => 'Ese concepto no existe o está inactivo.',
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'valor', 'payroll_concept_id' => 'concepto', 'quantity' => 'cantidad'];
    }
}
