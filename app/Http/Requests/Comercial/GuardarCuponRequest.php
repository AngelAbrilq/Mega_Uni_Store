<?php

namespace App\Http\Requests\Comercial;

use App\Models\Coupon;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de cupones (AT-CUP-001 · RN-35).
 */
class GuardarCuponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** El código se compara ya normalizado (mayúsculas, sin espacios). */
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Coupon::normalizar((string) $this->input('code'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $porcentaje = $this->input('type') === Coupon::PORCENTAJE;

        return [
            'code'         => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/',
                DelNegocio::unico('coupons', 'code')->ignore($this->route('coupon')?->id)],
            'description'  => ['nullable', 'string', 'max:200'],
            'type'         => ['required', Rule::in(array_keys(Coupon::TIPOS))],
            'value'        => ['required', 'numeric', 'gt:0', $porcentaje ? 'max:100' : 'max:99999999'],
            'max_discount' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'min_purchase' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'max_uses'     => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required'           => 'Escribe el código del cupón, por ejemplo BIENVENIDA10.',
            'code.regex'              => 'El código solo admite letras, números, guion y guion bajo.',
            'code.unique'             => 'Ya existe un cupón con ese código (puede estar eliminado).',
            'value.max'               => 'Un porcentaje no puede pasar de 100.',
            'value.gt'                => 'El valor del descuento debe ser mayor que cero.',
            'ends_at.after_or_equal'  => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
