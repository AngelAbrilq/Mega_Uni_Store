<?php

namespace App\Http\Requests\SmartInventory;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('compras.crear');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // mimes valida por contenido real (finfo), no por la extensión que manda el cliente.
            'imagen'            => ['required', 'file', 'image', 'mimes:' . implode(',', config('smart_inventory.image.mimes')), 'max:' . config('smart_inventory.image.max_kb')],
            'markup_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'imagen.required' => 'Adjunta la foto de la factura.',
            'imagen.image'    => 'El archivo debe ser una imagen.',
            'imagen.mimes'    => 'Formato no soportado. Usa JPG, PNG o WEBP.',
            'imagen.max'      => 'La foto supera el tamaño máximo permitido (8 MB).',
        ];
    }
}
