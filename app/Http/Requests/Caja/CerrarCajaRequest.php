<?php

namespace App\Http\Requests\Caja;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cerrar un turno de caja con el efectivo contado. (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class CerrarCajaRequest extends FormRequest
{
    /** H-04 · Su propio turno, o el de otro con `caja.cerrar_ajena` (CashSessionPolicy). */
    public function authorize(): bool
    {
        Gate::authorize('cerrar', $this->route('cash'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counted_amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'counted_amount.required' => 'Escribe cuánto efectivo hay realmente en la caja.',
        ];
    }
}
