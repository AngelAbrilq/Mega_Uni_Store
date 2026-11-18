<?php

namespace App\Http\Requests\Nomina;

use Illuminate\Foundation\Http\FormRequest;

/** El motivo (opcional) de una nómina de ajuste. Sin esto, un texto largo reventaba en la base. */
class NotaAjusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['notes' => ['nullable', 'string', 'max:200']];
    }

    public function attributes(): array
    {
        return ['notes' => 'motivo'];
    }
}
