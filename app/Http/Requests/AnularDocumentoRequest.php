<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anular una venta o una compra: siempre con un motivo que quede en el rastro.
 * El permiso (`ventas.anular` / `compras.eliminar`) lo exige el middleware.
 */
class AnularDocumentoRequest extends FormRequest
{
    /** H-04 · El permiso lo exige la ruta; la Policy exige operar desde el local del documento. */
    public function authorize(): bool
    {
        $documento = $this->route('sale') ?? $this->route('purchase');

        if ($documento) {
            Gate::authorize('anular', $documento);
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Explica por qué se anula el documento.',
            'motivo.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
