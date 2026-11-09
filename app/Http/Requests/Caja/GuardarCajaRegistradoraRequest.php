<?php

namespace App\Http\Requests\Caja;

use App\Models\CashRegister;
use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una caja registradora del local activo (AT-CAJ-001).
 */
class GuardarCajaRegistradoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function objetivo(): ?CashRegister
    {
        return $this->route('cash_register');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tiendaId = $this->objetivo()?->tienda_id ?? Contexto::tiendaId();

        return [
            'name'        => ['required', 'string', 'max:60',
                Rule::unique('cash_registers', 'name')->where('tienda_id', $tiendaId)->ignore($this->objetivo()?->id)],
            'code'        => ['nullable', 'string', 'max:10',
                Rule::unique('cash_registers', 'code')->where('tienda_id', $tiendaId)->ignore($this->objetivo()?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a la caja, por ejemplo «Principal» o «Rápida».',
            'name.unique'   => 'Este local ya tiene una caja con ese nombre.',
            'code.unique'   => 'Este local ya tiene una caja con ese código.',
        ];
    }
}
