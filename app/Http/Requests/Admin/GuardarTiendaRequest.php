<?php

namespace App\Http\Requests\Admin;

use App\Services\LimiteService;
use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de locales del negocio (H-03)
 */
class GuardarTiendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El local de otro negocio no existe para este (mismo criterio que suya()).
        $tienda = $this->route('tienda');
        abort_if($tienda && $tienda->empresa_id !== Contexto::empresaId(), 404);

        return true;
    }

    /** Al crear: el tope de locales del plan, ANTES de revisar los datos. */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post')) {
            app(LimiteService::class)->exigir('tiendas');
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre'       => ['required', 'string', 'max:150'],
            'codigo'       => [
                'nullable', 'string', 'max:6', 'alpha_num',
                /**
                 * Se cuentan también los locales cerrados, y es a propósito.
                 *
                 * El índice único de la base es `(empresa_id, codigo)` a
                 * secas: no sabe nada de borrados lógicos. Cuando esta
                 * regla excluía los cerrados, la validación dejaba pasar un
                 * código que la base rechazaba un milisegundo después con
                 * un 500 —«Duplicate entry '1-N'»—. Pasó en la primera
                 * prueba real: cerrar la Sede Norte y volver a abrirla con
                 * el mismo prefijo tumbaba la pantalla.
                 *
                 * De las dos formas de cuadrarlo —que la validación mienta
                 * menos, o que el índice sepa de borrados— esta es la
                 * honesta: el código de un local cerrado sigue estando en
                 * sus documentos viejos, así que reutilizarlo mezclaría dos
                 * numeraciones distintas bajo el mismo prefijo.
                 */
                Rule::unique('tiendas', 'codigo')
                    ->where(fn ($c) => $c->where('empresa_id', Contexto::empresaId()))
                    ->ignore($this->route('tienda')?->id),
            ],
            'razon_social' => ['nullable', 'string', 'max:180'],
            'nit'          => ['nullable', 'string', 'max:30'],
            'direccion'    => ['nullable', 'string', 'max:180'],
            'ciudad'       => ['nullable', 'string', 'max:80'],
            'telefono'     => ['nullable', 'string', 'max:30'],
            'correo'       => ['nullable', 'email', 'max:150'],
            // Cajón del local (servidor en la nube → agente en el PC de caja).
            'drawer_mode'  => ['nullable', Rule::in(array_keys(\App\Models\Tienda::MODOS_CAJON))],
            'agent_port'   => ['nullable', 'integer', 'min:1024', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => '¿Cómo se llama el local? Por ejemplo: Sede Norte.',
            'codigo.unique'   => 'Ya hay otro local de este negocio con ese código — puede ser uno que cerraste: '
                . 'su prefijo sigue impreso en los documentos que alcanzó a numerar.',
            'codigo.alpha_num' => 'El código va sin espacios ni guiones: solo letras y números.',
            'correo.email'    => 'Ese correo no tiene forma de correo.',
        ];
    }
}
