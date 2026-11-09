<?php

namespace App\Http\Requests\Tienda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Registro de cliente en la tienda pública.
 *
 * La unicidad del correo NO se valida aquí sino en RegistroClienteService:
 * un correo que ya existe puede ser un cliente del mostrador sin cuenta,
 * y ese caso no es un error sino «reclamar» su registro.
 */
class RegistroClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['nullable', 'string', 'max:100'],
            'email'             => ['required', 'string', 'email:rfc', 'max:150'],
            'phone'             => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s()-]{7,20}$/'],
            'password'          => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'acepta_privacidad' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required'        => 'Escribe tu nombre.',
            'email.required'             => 'Escribe tu correo electrónico.',
            'email.email'                => 'Ese correo no tiene un formato válido.',
            'phone.regex'                => 'El teléfono solo puede tener números, espacios y + ( ) -.',
            'password.required'          => 'Escribe una contraseña.',
            'password.confirmed'         => 'Las contraseñas no coinciden.',
            'acepta_privacidad.accepted' => 'Debes aceptar la política de tratamiento de datos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email'      => Str::lower(trim((string) $this->input('email'))),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name'  => $this->filled('last_name') ? trim((string) $this->input('last_name')) : null,
        ]);
    }
}
