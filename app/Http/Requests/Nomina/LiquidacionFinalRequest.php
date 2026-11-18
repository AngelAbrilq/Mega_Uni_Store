<?php

namespace App\Http\Requests\Nomina;

use App\Support\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Liquidación final de un empleado (NR-NOM-007).
 *
 * `employees` se aísla por local (no tiene empresa_id): el empleado debe ser
 * de un local del negocio activo. Se aceptan los retirados (borrado lógico),
 * que es justo a quienes se liquida.
 */
class LiquidacionFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereIn('tienda_id', Contexto::tiendaIds() ?? [])],
            'fecha'       => ['required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return ['employee_id' => 'empleado', 'fecha' => 'fecha de retiro'];
    }
}
