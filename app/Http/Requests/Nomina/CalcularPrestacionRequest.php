<?php

namespace App\Http\Requests\Nomina;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Prima o cesantías de todo el local para un período (NR-NOM-007). */
class CalcularPrestacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'         => ['required', Rule::in(['cesantias', 'prima'])],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }

    public function attributes(): array
    {
        return ['period_start' => 'desde', 'period_end' => 'hasta'];
    }
}
