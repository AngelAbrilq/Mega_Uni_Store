<?php

namespace App\Http\Requests\Nomina;

use Illuminate\Foundation\Http\FormRequest;

/** NR-NOM-003 E1 · Período de la nómina. */
class LiquidarNominaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'notes'        => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return ['period_start' => 'inicio del período', 'period_end' => 'fin del período'];
    }
}
