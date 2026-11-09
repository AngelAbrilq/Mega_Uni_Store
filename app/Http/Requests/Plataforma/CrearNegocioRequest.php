<?php

namespace App\Http\Requests\Plataforma;

use App\Support\Rubros;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Panel de plataforma: alta de un negocio con su dueño. El permiso lo exige el middleware de la ruta (H-03)
 */
class CrearNegocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'negocio'  => ['required', 'string', 'max:120'],
            'nit'      => ['nullable', 'string', 'max:30'],
            'rubro'    => ['required', Rule::in(array_keys(Rubros::paraEscoger()))],
            'nombre'   => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::min(8)],
            'plan_id'  => ['nullable', 'integer', Rule::exists('planes', 'id')->where('is_active', true)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'negocio.required'  => '¿Cómo se llama el negocio?',
            'rubro.required'    => 'Escoge de qué clase de negocio se trata.',
            'nombre.required'   => '¿Cómo se llama el dueño?',
            'email.required'    => 'El dueño necesita un correo para entrar.',
            'email.unique'      => 'Ese correo ya tiene cuenta en el sistema.',
            'password.required' => 'Define la contraseña con la que va a entrar.',
        ];
    }
}
