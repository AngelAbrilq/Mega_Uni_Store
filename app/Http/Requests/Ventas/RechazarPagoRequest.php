<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rechazar un pago pendiente: el motivo es obligatorio (RN-32).
 */
class RechazarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('verificar', $this->route('payment'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'min:3', 'max:255']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['rejection_reason.required' => 'Escribe por qué se rechaza el pago (por ejemplo: «la transferencia no llegó»).'];
    }
}
