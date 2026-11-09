<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ReglasDeImagen;
use App\Models\User;
use App\Rules\DelNegocio;
use App\Services\LimiteService;
use App\Support\Jerarquia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Alta y edición de usuarios del negocio (H-03)
 */
class GuardarUsuarioRequest extends FormRequest
{
    use ReglasDeImagen;

    public function authorize(): bool
    {
        // H-F06: no se edita a quien tiene más permisos que uno.
        abort_if(
            $this->objetivo() && Jerarquia::esSuperior($this->user(), $this->objetivo()),
            403,
            'No puedes modificar a un usuario con más permisos que tú.'
        );

        return true;
    }

    /**
     * Al crear: el tope de usuarios del plan, ANTES de revisar los datos.
     * (prepareForValidation corre antes que authorize y que las reglas.)
     */
    protected function prepareForValidation(): void
    {
        if (! $this->objetivo()) {
            app(LimiteService::class)->exigir('usuarios');
        }
    }

    /** El usuario que se edita; null al crear. */
    private function objetivo(): ?User
    {
        return $this->route('user');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:100'],
            // withTrashed implícito: el índice único de la BD también cuenta a los dados de baja.
            'email'     => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users', 'email')->ignore($this->objetivo()?->id)],
            'phone'     => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]{7,30}$/'],
            'is_active' => ['sometimes', 'boolean'],
            // SA-USR-002: locales donde trabaja. Vacío = todos los del negocio.
            // `tiendas_presentes` distingue «no marcó ninguno» de «el formulario no los envió».
            'tiendas_presentes' => ['sometimes', 'boolean'],
            'tiendas'   => ['sometimes', 'array'],
            'tiendas.*' => ['integer', 'distinct', DelNegocio::existe('tiendas')],
            'password' => [$this->objetivo() ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'roles'    => ['required', 'array', 'min:1'],
            // H-F06: solo los roles que quien asigna puede repartir.
            'roles.*'  => [Rule::in(Jerarquia::rolesAlAlcance($this->user())->pluck('name')->all())],
        ] + $this->reglasImagen('foto');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'      => 'Escribe el nombre completo.',
            'email.required'     => 'El correo es obligatorio.',
            'email.email'        => 'Escribe un correo electrónico válido.',
            'email.lowercase'    => 'El correo debe ir en minúsculas.',
            'email.unique'       => 'Ese correo ya tiene una cuenta (activa, inactiva o dada de baja).',
            'last_name.max'      => 'Los apellidos admiten hasta 100 caracteres.',
            'phone.regex'        => 'Escribe un teléfono válido: solo números, espacios, +, guiones o paréntesis (7 a 30).',
            'tiendas.*.exists'   => 'Uno de los locales elegidos no pertenece a este negocio.',
            'password.required'  => 'Define una contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'roles.required'     => 'Un usuario debe tener al menos un rol.',
            'roles.min'          => 'Un usuario debe tener al menos un rol.',
        ] + $this->mensajesImagen('foto');
    }
}
