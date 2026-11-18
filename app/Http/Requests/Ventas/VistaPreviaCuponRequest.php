<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Vista previa de un cupón en el POS (VD-CUP-001). No lleva ids: el cupón se
 * busca por código dentro del negocio activo en CouponService.
 */
class VistaPreviaCuponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:40'],
            'base'   => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
