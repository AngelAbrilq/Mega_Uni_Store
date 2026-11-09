<?php

namespace App\Http\Requests\Agenda;

use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Agendar una cita. (H-03)
 * El permiso lo exige el middleware de la ruta; las reglas de negocio
 * (choques de agenda, existencias, turnos) viven en el servicio.
 */
class AgendarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recurso_id'  => ['required', 'integer'],
            'inicio'      => ['required', 'date'],
            'customer_id' => ['nullable', 'integer', DelNegocio::existe('customers')],
            'product_id'  => ['nullable', 'integer', DelNegocio::existe('products')],
            'titulo'      => ['nullable', 'string', 'max:150'],
            'minutos'     => ['nullable', 'integer', 'min:5', 'max:600'],
            'precio'      => ['nullable', 'numeric', 'min:0'],
            'notas'       => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recurso_id.required' => '¿Quién la atiende?',
            'inicio.required'     => '¿A qué hora?',
            'minutos.min'         => 'Una cita de menos de cinco minutos no es una cita.',
        ];
    }
}
