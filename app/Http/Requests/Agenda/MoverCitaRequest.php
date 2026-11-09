<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mover una cita de hora o de recurso. (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class MoverCitaRequest extends FormRequest
{
    /** H-04 · Desde el local de la cita (CitaPolicy). */
    public function authorize(): bool
    {
        Gate::authorize('operar', $this->route('cita'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'inicio'     => ['required', 'date'],
            'recurso_id' => ['nullable', 'integer'],
            'minutos'    => ['nullable', 'integer', 'min:5', 'max:600'],
        ];
    }
}
