<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bloquear la agenda de un recurso. (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class BloquearRecursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'inicio' => ['required', 'date'],
            'fin'    => ['required', 'date', 'after:inicio'],
            'motivo' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fin.after' => 'El bloqueo tiene que terminar después de empezar.',
        ];
    }
}
