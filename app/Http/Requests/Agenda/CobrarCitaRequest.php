<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Support\Facades\Gate;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cobrar una cita atendida. (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class CobrarCitaRequest extends FormRequest
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
            'payment_method_id' => ['nullable', 'integer', DelNegocio::existe('payment_methods')],
        ];
    }
}
