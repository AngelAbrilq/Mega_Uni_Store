<?php

namespace App\Http\Requests\Nomina;

use App\Models\PayrollConcept;
use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** NR-NOM-002 E1/E3 · El nombre y el código no se repiten en la empresa. */
class GuardarConceptoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
    }

    public function rules(): array
    {
        $id      = $this->route('concepto')?->id;
        $sistema = (bool) $this->route('concepto')?->is_system;
        $unico   = fn (string $col) => Rule::unique('payroll_concepts', $col)->where('empresa_id', Contexto::empresaId())->ignore($id);

        return [
            'name'         => ['required', 'string', 'max:80', $unico('name')],
            'code'         => $sistema ? ['nullable'] : ['required', 'alpha_dash', 'max:20', $unico('code')],
            'type'         => $sistema ? ['nullable'] : ['required', Rule::in([PayrollConcept::DEVENGADO, PayrollConcept::DEDUCCION])],
            'calculation'  => $sistema ? ['nullable'] : ['required', Rule::in(['fijo', 'pct_salario', 'pct_smlmv', 'manual'])],
            'value'        => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'is_mandatory' => ['boolean'],
            'is_salary'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Ya existe un concepto con ese nombre.', 'code.unique' => 'Ya existe un concepto con ese código.'];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'code' => 'código', 'type' => 'tipo', 'calculation' => 'fórmula', 'value' => 'valor'];
    }

    /** @return array<string, mixed> */
    public function datos(): array
    {
        $d = $this->validated();
        $d['is_mandatory'] = $this->boolean('is_mandatory');
        $d['is_salary']    = $this->boolean('is_salary');
        $d['value']        = (float) ($d['value'] ?? 0);

        if ($this->route('concepto')?->is_system) {
            // Del sistema: solo cambian el nombre y la tasa; el código lo usa el cálculo.
            unset($d['code'], $d['type'], $d['calculation'], $d['is_mandatory'], $d['is_salary']);
        }

        if (($d['calculation'] ?? null) === 'manual') {
            $d['is_mandatory'] = false; // un valor que se digita por empleado no puede aplicarse solo
        }

        return array_filter($d, fn ($v) => $v !== null);
    }
}
