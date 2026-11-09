<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Mensajes en español para la pantalla de autenticación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required'    => 'Escribe tu correo electrónico.',
            'email.email'       => 'Ese correo no tiene un formato válido.',
            'password.required' => 'Escribe tu contraseña.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credenciales = $this->only('email', 'password');

        // SA-USR-003: un usuario desactivado no entra aunque su clave sea correcta.
        // Los dados de baja (soft delete) ni siquiera se encuentran.
        $entro = Auth::attemptWhen(
            $credenciales,
            fn (User $usuario) => $usuario->is_active,
            $this->boolean('remember')
        );

        if (! $entro) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $this->esCuentaDesactivada($credenciales)
                    ? 'Tu usuario está desactivado. Habla con el administrador del negocio.'
                    : 'El correo o la contraseña no coinciden con nuestros registros.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * ¿La clave era correcta pero la cuenta está desactivada?
     *
     * Solo se dice «desactivado» a quien demostró conocer la contraseña:
     * a cualquier otro se le da el mensaje genérico, para no revelar qué
     * correos existen.
     *
     * @param array{email: string, password: string} $credenciales
     */
    private function esCuentaDesactivada(array $credenciales): bool
    {
        $usuario = User::where('email', $credenciales['email'])->first();

        return $usuario !== null
            && ! $usuario->is_active
            && Hash::check($credenciales['password'], $usuario->password);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Demasiados intentos fallidos. Vuelve a intentarlo en '
                . ceil($seconds / 60) . ' minuto(s).',
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
