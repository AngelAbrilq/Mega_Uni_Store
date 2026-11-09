<?php

namespace App\Http\Requests\Agenda;

use App\Models\Recurso;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un recurso (estilista, consultorio…). (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class GuardarRecursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre'  => ['required', 'string', 'max:120'],
            'tipo'    => ['required', Rule::in(array_keys(Recurso::TIPOS))],
            'color'   => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'user_id' => ['nullable', 'integer', DelNegocio::usuario()],
            'activo'  => ['nullable', 'boolean'],
            'orden'   => ['nullable', 'integer', 'min:0', 'max:999'],
            'notas'   => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => '¿Cómo se llama?',
            'color.regex'     => 'El color va en formato #RRGGBB.',
        ];
    }
}
