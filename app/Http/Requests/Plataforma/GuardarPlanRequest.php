<?php

namespace App\Http\Requests\Plataforma;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * SA-PLN-001 · Crear o editar un plan. El permiso lo exige el middleware
 * (`sistema.superadmin`).
 *
 * Límites: vacío o 0 = sin límite (la HU lo pide así: «0 se interpreta como
 * ilimitado»). En la base se guarda NULL, que es lo que el resto del sistema
 * entiende como «sin tope»; el cero nunca llega a la tabla.
 */
class GuardarPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $limpio = [];

        foreach (['max_tiendas', 'max_usuarios', 'max_productos', 'max_ventas_mes'] as $campo) {
            $valor = $this->input($campo);
            $limpio[$campo] = ($valor === null || $valor === '' || (string) $valor === '0') ? null : $valor;
        }

        $nombre = trim((string) $this->input('nombre'));
        $slug   = trim((string) $this->input('slug'));

        $this->merge($limpio + [
            'nombre'    => $nombre,
            'slug'      => Str::slug($slug !== '' ? $slug : $nombre),
            'visible'   => $this->boolean('visible'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'nombre'              => ['required', 'string', 'max:80'],
            'slug'                => ['required', 'alpha_dash', 'max:90', Rule::unique('planes', 'slug')->ignore($plan)],
            'descripcion'         => ['nullable', 'string', 'max:200'],
            'precio_mensual'      => ['required', 'numeric', 'min:0', 'max:99999999'],
            'precio_anual'        => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'precio_tienda_extra' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'max_tiendas'         => ['nullable', 'integer', 'min:1', 'max:9999'],
            'max_usuarios'        => ['nullable', 'integer', 'min:1', 'max:99999'],
            'max_productos'       => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'max_ventas_mes'      => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'funciones'           => ['nullable', 'array'],
            'funciones.*'         => ['string', Rule::in(array_keys(Plan::FUNCIONES))],
            'dias_prueba'         => ['nullable', 'integer', 'min:0', 'max:90'],
            'orden'               => ['nullable', 'integer', 'min:0', 'max:999'],
            'visible'             => ['boolean'],
            'is_active'           => ['boolean'],
        ];
    }

    /** Lo que se guarda: con los valores por defecto ya puestos. */
    public function datos(): array
    {
        $d = $this->validated();

        $d['precio_anual']        = $d['precio_anual'] ?? round((float) $d['precio_mensual'] * 12, 2);
        $d['precio_tienda_extra'] = $d['precio_tienda_extra'] ?? 0;
        $d['funciones']           = array_values($d['funciones'] ?? []);
        $d['dias_prueba']         = $d['dias_prueba'] ?? 0;
        $d['orden']               = $d['orden'] ?? 0;

        return $d;
    }

    public function messages(): array
    {
        return [
            'nombre.required'         => 'Ponle nombre al plan.',
            'slug.unique'             => 'Ya hay un plan con ese identificador.',
            'precio_mensual.required' => 'Falta el precio mensual.',
            'max_tiendas.min'         => 'Deja vacío o en 0 para «sin límite».',
            'max_usuarios.min'        => 'Deja vacío o en 0 para «sin límite».',
            'funciones.*.in'          => 'Esa función no existe.',
        ];
    }
}
