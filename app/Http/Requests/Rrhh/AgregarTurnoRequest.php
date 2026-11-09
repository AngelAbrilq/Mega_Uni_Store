<?php

namespace App\Http\Requests\Rrhh;

use Illuminate\Foundation\Http\FormRequest;

/** TBL-TUR-001 · Franja de un horario: uno o varios días con la misma hora. */
class AgregarTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weekdays'   => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'weekdays.required' => 'Escoge al menos un día.',
            'end_time.after'    => 'La hora de salida debe ser después de la de entrada.',
        ];
    }
}
