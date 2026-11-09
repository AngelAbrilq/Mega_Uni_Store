<?php

namespace App\Http\Requests\Plataforma;

use App\Support\Rubros;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Registro público de una cuenta de prueba. El permiso lo exige el middleware de la ruta (H-03)
 */
class CrearDemoRequest extends FormRequest
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
            'nombre'   => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'rubro'    => ['required', Rule::in(array_keys(Rubros::paraEscoger()))],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'negocio.required'   => '¿Cómo se llama tu negocio?',
            'nombre.required'    => '¿Cómo te llamas?',
            'email.required'     => 'Necesitamos un correo para tu cuenta.',
            'email.unique'       => 'Ese correo ya tiene una cuenta. Entra con ella.',
            'rubro.required'     => 'Escoge qué clase de negocio tienes.',
            'rubro.in'           => 'Esa clase de negocio no está en la lista.',
            'password.required'  => 'Define una contraseña para entrar.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ];
    }
}
