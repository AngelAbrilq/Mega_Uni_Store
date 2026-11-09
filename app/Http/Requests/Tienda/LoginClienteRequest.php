<?php

namespace App\Http\Requests\Tienda;

use App\Support\Contexto;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso del cliente a la tienda de UN negocio.
 *
 * `Auth::guard('cliente')->attempt()` busca con el modelo `Customer`, que
 * lleva el filtro por empresa: el mismo correo registrado en dos negocios
 * son dos cuentas distintas, y la de uno no abre la del otro.
 */
class LoginClienteRequest extends FormRequest
{
    private const INTENTOS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required'    => 'Escribe tu correo electrónico.',
            'email.email'       => 'Ese correo no tiene un formato válido.',
            'password.required' => 'Escribe tu contraseña.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    /**
     * Intenta el ingreso. Lanza ValidationException con un mensaje que no
     * dice si el correo existe (no se le regala a nadie la lista de clientes).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $ok = Auth::guard('cliente')->attempt(
            $this->only('email', 'password'),
            $this->boolean('remember')
        );

        if (! $ok) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no coinciden.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /** @throws ValidationException */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::INTENTOS)) {
            return;
        }

        event(new Lockout($this));

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Demasiados intentos. Vuelve a intentarlo en ' . ceil($segundos / 60) . ' minuto(s).',
        ]);
    }

    /** Por empresa: equivocarse en una tienda no bloquea en otra. */
    private function throttleKey(): string
    {
        return 'tienda|' . (Contexto::empresaId() ?? 0) . '|'
            . Str::transliterate((string) $this->input('email')) . '|' . $this->ip();
    }
}
