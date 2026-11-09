<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /returns/{return}/rechazar · RN-30.
 *
 * El permiso `ventas.aprobar_devolucion` lo exige el middleware de la ruta;
 * la Policy exige que siga pendiente y que se revise desde su local.
 */
class RechazarDevolucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('revisar', $this->route('return'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo_rechazo' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo_rechazo.required' => 'Escribe por qué se rechaza la devolución.',
            'motivo_rechazo.min'      => 'El motivo debe tener al menos 5 caracteres.',
            'motivo_rechazo.max'      => 'El motivo admite hasta 255 caracteres.',
        ];
    }
}
