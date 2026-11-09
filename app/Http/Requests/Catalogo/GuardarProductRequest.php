<?php

namespace App\Http\Requests\Catalogo;

use App\Models\Attribute;
use App\Rules\DelNegocio;
use App\Services\LimiteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de productos (H-03). Reglas acotadas al negocio (H-F10 /
 * H-F11). El permiso de crear o editar lo exige el middleware de la ruta.
 */
class GuardarProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Antes de validar nada: si el plan no da para otro producto, no tiene
     * sentido revisar los datos y después decirle que no. Solo al crear.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post')) {
            app(LimiteService::class)->exigir('productos');
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:200'],
            'sku'         => ['nullable', 'string', 'max:50', DelNegocio::unico('products', 'sku')->ignore($this->route('product')?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'barcode'     => ['nullable', 'string', 'max:50', DelNegocio::unico('products', 'barcode')->ignore($this->route('product')?->id)],
            'imagen'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'category_id' => ['nullable', DelNegocio::existe('categories')],
            'unit_id'     => ['nullable', DelNegocio::existe('units')],
            'tax_id'      => ['nullable', DelNegocio::existe('taxes')],
            'supplier_id' => ['nullable', DelNegocio::existe('suppliers')],
            'price'       => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cost'        => ['required', 'numeric', 'min:0', 'max:99999999.99', 'lte:price'],
            // Numérico y no entero: hay productos que se venden por peso.
            'stock'       => ['required', 'numeric', 'min:0', 'max:999999'],
            'min_stock'   => ['required', 'numeric', 'min:0', 'max:999999'],
            // Entero, y nulo cuando no es un servicio. El máximo son diez
            // horas: más que eso no es una cita, es un proyecto.
            'duracion_minutos' => ['nullable', 'integer', 'min:5', 'max:600'],

            // AT-PRI-001 · Impuestos adicionales al principal.
            'extra_taxes'   => ['sometimes', 'array', 'max:10'],
            'extra_taxes.*' => ['integer', 'distinct', DelNegocio::existe('taxes')],

            // TBL-TPL-001 · Proveedores del producto. Las filas sin proveedor se ignoran.
            'proveedores_presentes'          => ['sometimes', 'boolean'],
            'proveedores'                    => ['sometimes', 'array', 'max:20'],
            'proveedores.*.supplier_id'      => ['nullable', 'integer', 'distinct', DelNegocio::existe('suppliers')],
            'proveedores.*.supplier_sku'     => ['nullable', 'string', 'max:60'],
            'proveedores.*.supplier_price'   => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'proveedores.*.lead_time_days'   => ['nullable', 'integer', 'min:0', 'max:365'],
            'proveedor_preferido'            => ['nullable', 'integer'],

            // TBL-PPA-001 · atributos[id_atributo] = valor
            'atributos'   => ['sometimes', 'array', 'max:30'],
            'atributos.*' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Reglas que dependen de otras filas: el preferido debe estar entre los
     * proveedores elegidos, y cada atributo debe ser del negocio y respetar
     * su tipo (número, sí/no).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $v) {
            $elegidos = collect($this->input('proveedores', []))->pluck('supplier_id')->filter()->map('intval');
            $preferido = $this->input('proveedor_preferido');

            if ($preferido && ! $elegidos->contains((int) $preferido)) {
                $v->errors()->add('proveedor_preferido', 'El proveedor preferido debe estar en la lista de proveedores del producto.');
            }

            $valores = collect($this->input('atributos', []))->filter(fn ($x) => $x !== null && $x !== '');

            if ($valores->isEmpty()) {
                return;
            }

            $atributos = Attribute::whereKey($valores->keys())->get()->keyBy('id');

            foreach ($valores as $id => $valor) {
                $atributo = $atributos->get((int) $id);

                if (! $atributo) {
                    $v->errors()->add("atributos.{$id}", 'Ese atributo no existe en este negocio.');
                } elseif ($atributo->type === 'numero' && ! is_numeric(str_replace(',', '.', (string) $valor))) {
                    $v->errors()->add("atributos.{$id}", "«{$atributo->name}» debe ser un número.");
                } elseif ($atributo->type === 'booleano' && ! in_array(mb_strtolower((string) $valor), ['si', 'sí', 'no'], true)) {
                    $v->errors()->add("atributos.{$id}", "«{$atributo->name}» debe ser Sí o No.");
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'      => 'El producto necesita un nombre.',
            'extra_taxes.*.exists'               => 'Uno de los impuestos adicionales no existe en este negocio.',
            'extra_taxes.*.distinct'             => 'Repetiste un impuesto adicional.',
            'proveedores.*.supplier_id.exists'   => 'Uno de los proveedores no existe en este negocio.',
            'proveedores.*.supplier_id.distinct' => 'Ese proveedor ya está en la lista.',
            'proveedores.*.lead_time_days.max'   => 'El tiempo de entrega no puede pasar de 365 días.',
            'sku.unique'         => 'Ya existe otro producto con ese SKU.',
            'barcode.unique'     => 'Ese código de barras ya está registrado.',
            'imagen.image'       => 'El archivo debe ser una imagen.',
            'imagen.mimes'       => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'         => 'La imagen no puede pesar más de 2 MB.',
            'price.required'     => 'Indica el precio de venta.',
            'cost.required'      => 'Indica cuánto te cuesta el producto.',
            'cost.lte'           => 'El costo no puede ser mayor que el precio de venta.',
            'stock.required'     => 'Indica cuántas unidades hay disponibles.',
            'min_stock.required' => 'Indica a partir de cuántas unidades hay que reponer.',
            'duracion_minutos.min' => 'Un servicio de menos de cinco minutos no se alcanza a agendar.',
            'duracion_minutos.max' => 'Diez horas es el tope de una cita. Si dura más, pártelo en dos.',
        ];
    }
}
