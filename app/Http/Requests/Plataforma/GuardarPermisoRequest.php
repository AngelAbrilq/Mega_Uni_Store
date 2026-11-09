<?php

namespace App\Http\Requests\Plataforma;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * SA-PER-001 · Crear o editar un permiso. El permiso de ruta lo exige el
 * middleware (`sistema.superadmin`).
 *
 * El nombre se arma solo («módulo.acción») en minúsculas y sin espacios:
 * es lo que el código pregunta con @can, y un espacio o una tilde ahí lo
 * dejaría inservible.
 */
class GuardarPermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $limpiar = fn ($v) => Str::of((string) $v)->lower()->ascii()->trim()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->value();

        $modulo = $limpiar($this->input('modulo_nuevo') ?: $this->input('module'));
        $accion = $limpiar($this->input('action'));

        $this->merge(['module' => $modulo, 'action' => $accion, 'name' => "{$modulo}.{$accion}"]);
    }

    public function rules(): array
    {
        $permiso = $this->route('permiso');

        return [
            'module'      => ['required', 'string', 'min:2', 'max:40', Rule::notIn(['sistema'])],
            'action'      => ['required', 'string', 'min:2', 'max:40'],
            'name'        => ['required', Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($permiso)],
            'description' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'module.required' => 'Escoge o escribe el módulo.',
            'module.not_in'   => 'El módulo «sistema» es del panel de plataforma.',
            'action.required' => 'Escribe la acción (ver, crear, exportar…).',
            'name.unique'     => 'Ese permiso ya existe.',
        ];
    }
}
