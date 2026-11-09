<?php

namespace App\Http\Requests\Caja;

use App\Models\CashMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ingreso o retiro de efectivo en un turno abierto (AT-CAJ-001).
 * Quién puede hacerlo lo decide CashSessionPolicy::moverEfectivo.
 */
class RegistrarMovimientoCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moverEfectivo', $this->route('cash'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type'   => ['required', Rule::in(CashMovement::TIPOS)],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.in'         => 'El movimiento debe ser un ingreso o un retiro.',
            'amount.required' => 'Indica el valor.',
            'amount.min'      => 'El valor debe ser mayor que cero.',
            'reason.required' => 'Escribe el motivo: queda en el arqueo y en el cierre Z.',
        ];
    }
}
