<?php

namespace App\Http\Requests\Inventario;

use App\Models\Tienda;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Traslado de mercancía entre locales del negocio. (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class RegistrarTrasladoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', DelNegocio::existe('products')],
            'desde'      => ['required', 'integer', Rule::in($this->locales())],
            'hacia'      => ['required', 'integer', Rule::in($this->locales()), 'different:desde'],
            'cantidad'   => ['required', 'numeric', 'gt:0'],
            'notas'      => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Escoge qué producto se va a mover.',
            'desde.required'      => 'Di de qué local sale.',
            'hacia.required'      => 'Di a qué local llega.',
            'hacia.different'     => 'El origen y el destino no pueden ser el mismo local.',
            'hacia.in'            => 'Ese local no es de este negocio.',
            'desde.in'            => 'Ese local no es de este negocio.',
            'cantidad.gt'         => 'La cantidad tiene que ser mayor que cero.',
        ];
    }

    /** @return array<int> Ids de los locales activos del negocio. */
    private function locales(): array
    {
        return Tienda::activasDelNegocio()->pluck('id')->all();
    }
}
