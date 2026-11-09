<?php

namespace App\Http\Requests\Catalogo;

use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CL-TDC-001 · Asociar un cliente a un local. Solo se ofrecen —y solo se
 * aceptan— los locales donde quien lo hace puede trabajar.
 */
class AsociarClienteTiendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clientes.editar');
    }

    public function rules(): array
    {
        $permitidos = $this->user()->tiendasEn((int) Contexto::empresaId())->modelKeys();

        return [
            'tienda_id' => ['required', 'integer', Rule::in($permitidos)],
        ];
    }

    public function messages(): array
    {
        return [
            'tienda_id.required' => 'Escoge el local.',
            'tienda_id.in'       => 'Ese local no es uno de los tuyos.',
        ];
    }
}
