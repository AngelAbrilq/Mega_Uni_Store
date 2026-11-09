<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Support\Facades\Gate;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /sales/{sale}/devolver · Devolución parcial.
 *
 * El renglón debe pertenecer a ESTA venta (antes bastaba con que existiera
 * en cualquier venta y el servicio respondía 404).
 */
class RegistrarDevolucionRequest extends FormRequest
{
    /** El permiso `ventas.devolver` lo exige el middleware de la ruta. */
    /** H-04 · Se devuelve desde el local de la venta (SalePolicy). */
    public function authorize(): bool
    {
        Gate::authorize('devolver', $this->route('sale'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Sale $venta */
        $venta = $this->route('sale');

        return [
            'motivo'               => ['required', 'string', 'min:5', 'max:255'],
            'restock'              => ['nullable', 'boolean'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', Rule::exists('sale_items', 'id')->where('sale_id', $venta->id)],
            'items.*.quantity'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required'             => 'Explica por qué se devuelve la mercancía.',
            'motivo.min'                  => 'El motivo debe tener al menos 5 caracteres.',
            'items.required'              => 'Indica qué productos se devuelven.',
            'items.*.sale_item_id.exists' => 'Uno de los renglones no pertenece a esta venta.',
        ];
    }
}
