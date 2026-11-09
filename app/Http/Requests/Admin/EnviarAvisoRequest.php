<?php

namespace App\Http\Requests\Admin;

use App\Notifications\AvisoDelSistema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Enviar un aviso a los usuarios (SA-NOT-001). */
class EnviarAvisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo'  => ['required', 'string', 'max:120'],
            'mensaje' => ['required', 'string', 'max:1000'],
            'nivel'   => ['required', Rule::in(array_keys(AvisoDelSistema::NIVELES))],
            'url'     => ['nullable', 'string', 'max:255', 'regex:/^\//'],
            // Solo el Superadministrador puede mandar a todos los negocios.
            'alcance' => ['required', Rule::in($this->user()->can('sistema.superadmin') ? ['negocio', 'todos'] : ['negocio'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'titulo.required'  => 'Ponle un título al aviso.',
            'mensaje.required' => 'Escribe el mensaje.',
            'url.regex'        => 'El enlace debe ser una ruta del sistema, por ejemplo /reports.',
            'alcance.in'       => 'Solo la plataforma puede avisar a todos los negocios.',
        ];
    }
}
