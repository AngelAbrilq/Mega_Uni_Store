<?php

namespace App\Http\Requests\Reportes;

use App\Models\ReportDelivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Enviar una exportación por email o WhatsApp (TBL-ENV-001). */
class EnviarExportacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'channel'   => ['required', Rule::in([ReportDelivery::EMAIL, ReportDelivery::WHATSAPP])],
            'recipient' => ['required', 'string', 'max:150',
                $this->input('channel') === ReportDelivery::EMAIL ? 'email' : 'regex:/^[0-9+()\s-]{7,20}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recipient.required' => 'Escribe el correo o el número de WhatsApp.',
            'recipient.email'    => 'Escribe un correo electrónico válido.',
            'recipient.regex'    => 'Escribe un número de WhatsApp válido.',
        ];
    }
}
