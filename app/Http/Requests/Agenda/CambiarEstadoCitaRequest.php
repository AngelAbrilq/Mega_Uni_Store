<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Support\Facades\Gate;
use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambiar el estado de una cita. (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class CambiarEstadoCitaRequest extends FormRequest
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
            'estado' => ['required', Rule::in(array_keys(Cita::ESTADOS))],
            'notas'  => ['nullable', 'string', 'max:500'],
        ];
    }
}
