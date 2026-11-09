<?php

namespace App\Http\Requests\Admin;

use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de roles propios del negocio (H-03)
 */
class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Un rol propio de OTRO negocio no existe para este (antes: soloPropios()).
        $rol = $this->route('role');
        abort_if($rol && $rol->empresa_id !== Contexto::empresaId(), 404);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:80',
                // Único dentro del negocio. Y además se comprueba contra
                // los del sistema, para que nadie cree un «Cajero» propio
                // que se confunda con el de siempre.
                Rule::unique('roles', 'name')
                    ->where(fn ($q) => $q->where('guard_name', 'web')
                        ->where(fn ($s) => $s->whereNull('empresa_id')
                            ->orWhere('empresa_id', Contexto::empresaId())))
                    ->ignore($this->route('role')?->id),
            ],
            'permisos'   => ['array'],
            // H-F06: un rol propio solo puede llevar permisos que quien lo crea ya tiene.
            // Sin esto, un Administrador armaba un rol con `sistema.superadmin` y se lo asignaba.
            'permisos.*' => ['string', Rule::in($this->user()->getAllPermissions()->pluck('name')->all())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre al rol.',
            'name.unique'   => 'Ya existe un rol con ese nombre.',
            'permisos.*.in' => 'No puedes dar un permiso que tú no tienes.',
        ];
    }
}
