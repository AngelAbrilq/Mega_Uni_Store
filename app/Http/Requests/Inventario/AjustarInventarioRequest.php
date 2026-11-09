<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajuste manual de inventario (conteo, entrada o salida). (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class AjustarInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'modo'     => ['required', 'in:conteo,entrada,salida'],
            'cantidad' => ['required', 'numeric', 'min:0', 'max:999999'],
            'motivo'   => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cantidad.required' => 'Indica la cantidad.',
            'motivo.required'   => 'Explica por qué se ajusta el inventario.',
            'motivo.min'        => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
