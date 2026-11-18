<?php

namespace App\Http\Requests\Nomina;

use Illuminate\Foundation\Http\FormRequest;

/** Terminar un contrato: cuándo y por qué (NR-NOM-001). */
class TerminarContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ended_at' => ['required', 'date'],
            'motivo'   => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Explica el motivo.',
            'motivo.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return ['ended_at' => 'fecha de terminación'];
    }
}
