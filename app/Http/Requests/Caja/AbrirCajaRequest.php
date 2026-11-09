<?php

namespace App\Http\Requests\Caja;

use App\Models\CashRegister;
use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Abrir un turno de caja. (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class AbrirCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'opening_amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            // AT-CAJ-001: si el local tiene cajas activas, el turno se abre en una de ellas.
            'cash_register_id' => [
                Rule::requiredIf(fn () => CashRegister::deTienda(null)->activas()->exists()),
                'nullable', 'integer',
                Rule::exists('cash_registers', 'id')
                    ->where('tienda_id', Contexto::tiendaId())
                    ->where('is_active', true),
            ],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'opening_amount.required'   => 'Indica con cuánta base inicia la caja.',
            'cash_register_id.required' => 'Elige en qué caja vas a trabajar.',
            'cash_register_id.exists'   => 'Esa caja no está activa en este local.',
        ];
    }
}
