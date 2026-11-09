<?php

namespace App\Http\Requests\Nomina;

use App\Models\EmploymentContract;
use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** NR-NOM-001 · Crear o renovar un contrato. Las reglas de negocio (SMLMV, fecha fin) viven en ContratoService. */
class GuardarContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $renovar = $this->routeIs('contracts.renovar');

        return [
            'type'        => [$renovar ? 'nullable' : 'required', Rule::in(array_keys(EmploymentContract::TIPOS))],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'position_id' => ['nullable', Rule::exists('positions', 'id')->whereIn('tienda_id', Contexto::tiendaIds() ?? [])],
            'arl_class'   => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    public function attributes(): array
    {
        return ['type' => 'tipo de contrato', 'start_date' => 'fecha de inicio', 'end_date' => 'fecha de finalización',
                'base_salary' => 'salario', 'position_id' => 'cargo', 'arl_class' => 'clase de riesgo ARL'];
    }

    /** @return array<string, mixed> */
    public function datos(): array
    {
        return array_filter($this->validated(), fn ($v) => $v !== null && $v !== '');
    }
}
