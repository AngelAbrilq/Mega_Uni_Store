<?php

namespace App\Http\Requests\Nomina;

use Illuminate\Foundation\Http\FormRequest;

/** Parámetros legales de la nómina del negocio (RN-45). */
class GuardarParametrosNominaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'smlmv'              => ['required', 'numeric', 'min:1', 'max:99999999'],
            'auxilio_transporte' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'horas_mes'          => ['nullable', 'integer', 'between:150,240'],
            'recargo_dominical'  => ['nullable', 'numeric', 'between:0,200'],
            'ratio_alerta'       => ['required', 'numeric', 'between:1,100'],
            'exoneracion_114'    => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['smlmv' => 'SMLMV', 'horas_mes' => 'horas al mes', 'auxilio_transporte' => 'auxilio de transporte'];
    }

    /**
     * Los ajustes tal como se guardan. Vacío = «auto»: el sistema usa el valor
     * legal vigente en la fecha de cada liquidación.
     *
     * @return array<string, string|float|int>
     */
    public function ajustes(): array
    {
        $d = $this->validated();

        return [
            'nomina.smlmv'              => $d['smlmv'],
            'nomina.auxilio_transporte' => $d['auxilio_transporte'],
            'nomina.horas_mes'          => $d['horas_mes'] ?? 'auto',
            'nomina.recargo_dominical'  => $d['recargo_dominical'] ?? 'auto',
            'nomina.exoneracion_114'    => $this->boolean('exoneracion_114') ? '1' : '0',
            'nomina.ratio_alerta'       => $d['ratio_alerta'],
        ];
    }
}
