<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ReglasDeImagen;
use App\Support\Formato;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Configuración del negocio (Configuración › todas las pestañas) (H-03)
 */
class GuardarAjustesRequest extends FormRequest
{
    use ReglasDeImagen;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
                /* Negocio */
                'negocio_nombre'    => ['required', 'string', 'max:120'],
                'negocio_lema'      => ['nullable', 'string', 'max:120'],
                'negocio_nit'       => ['nullable', 'string', 'max:30'],
                'negocio_direccion' => ['nullable', 'string', 'max:150'],
                'negocio_telefono'  => ['nullable', 'string', 'max:30'],
                'negocio_correo'    => ['nullable', 'email', 'max:120'],
                'negocio_ciudad'    => ['nullable', 'string', 'max:80'],

                /* Regional */
                'regional_idioma'           => ['required', 'in:es,en'],
                'regional_moneda_codigo'    => ['required', 'string', 'size:3', 'alpha'],
                'regional_moneda_simbolo'   => ['nullable', 'string', 'max:5'],
                'regional_simbolo_posicion' => ['required', 'in:antes,despues'],
                'regional_decimales'        => ['required', 'integer', 'min:0', 'max:4'],
                'regional_sep_miles'        => ['required', 'in:punto,coma,espacio,ninguno'],
                'regional_sep_decimal'      => ['required', 'in:punto,coma,espacio,ninguno'],
                'regional_zona_horaria'     => ['required', 'timezone'],
                'regional_formato_fecha'    => ['required', 'in:' . implode(',', array_keys(Formato::FECHAS))],

                /* Recibo */
                'recibo_formato' => ['required', 'in:80,58,a4'],
                'recibo_mensaje' => ['nullable', 'string', 'max:150'],
                'recibo_pie'     => ['nullable', 'string', 'max:200'],
                'recibo_copias'  => ['required', 'integer', 'min:1', 'max:5'],

                /* Ventas */
                'venta_descuento_max' => ['required', 'numeric', 'min:0', 'max:100'],
                'venta_redondeo'      => ['required', 'in:0,50,100'],
                'venta_pesos_por_punto' => ['nullable', 'integer', 'min:1', 'max:1000000'],

                /* Inventario */
                'inventario_dias_rotacion' => ['required', 'integer', 'min:7', 'max:365'],
                'inventario_costo_metodo'  => ['required', 'in:promedio,ultimo'],

                /* Apariencia */
                'apariencia_acento'   => ['required', 'in:azul,verde,violeta,ambar,grafito'],
                'apariencia_densidad' => ['required', 'in:comoda,compacta'],
            ], $this->reglasImagen('negocio_logo'));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge([
                'negocio_nombre.required'          => 'El negocio necesita un nombre.',
                'negocio_correo.email'             => 'Escribe un correo electrónico válido.',
                'regional_moneda_codigo.size'      => 'El código de moneda son tres letras: COP, USD, EUR…',
                'regional_moneda_codigo.alpha'     => 'El código de moneda son tres letras: COP, USD, EUR…',
                'regional_zona_horaria.timezone'   => 'Esa zona horaria no existe.',
                'venta_descuento_max.max'          => 'El descuento máximo no puede pasar de 100%.',
                'inventario_dias_rotacion.min'     => 'Usa al menos 7 días: con menos, el promedio no significa nada.',
            ], $this->mensajesImagen('negocio_logo'));
    }
}
