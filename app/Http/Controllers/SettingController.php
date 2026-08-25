<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardaImagen;
use App\Http\Middleware\EstableceIdioma;
use App\Models\Setting;
use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Configuración del sistema.
 *
 * Está organizada en seis pestañas —Negocio, Regional, Recibo, Ventas,
 * Inventario, Apariencia— pero es **un solo formulario**: se guarda todo de
 * una. Partirlo en seis formularios obligaría a seis guardados y la gente
 * termina cambiando algo en una pestaña, saltando a otra y perdiendo lo de
 * la primera.
 *
 * Todos los campos de sí/no se leen con `$request->boolean()` en vez de
 * validarse: una casilla apagada no viaja en el POST, así que si se
 * validara como `required` el formulario nunca dejaría apagar nada.
 */
class SettingController extends Controller implements HasMiddleware
{
    use GuardaImagen;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:configuracion.editar'),
        ];
    }

    public function edit(Request $request)
    {
        return view('settings.edit', [
            'ajustes'  => Setting::todos(),
            'pestana'  => $request->query('t', 'negocio'),
            'listas'   => $this->listas(),
        ]);
    }

    public function update(Request $request)
    {
        $datos = $request->validate(
            array_merge([
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

                /* Inventario */
                'inventario_dias_rotacion' => ['required', 'integer', 'min:7', 'max:365'],
                'inventario_costo_metodo'  => ['required', 'in:promedio,ultimo'],

                /* Apariencia */
                'apariencia_acento'   => ['required', 'in:azul,verde,violeta,ambar,grafito'],
                'apariencia_densidad' => ['required', 'in:comoda,compacta'],
            ], $this->reglasImagen('negocio_logo')),

            array_merge([
                'negocio_nombre.required'          => 'El negocio necesita un nombre.',
                'negocio_correo.email'             => 'Escribe un correo electrónico válido.',
                'regional_moneda_codigo.size'      => 'El código de moneda son tres letras: COP, USD, EUR…',
                'regional_moneda_codigo.alpha'     => 'El código de moneda son tres letras: COP, USD, EUR…',
                'regional_zona_horaria.timezone'   => 'Esa zona horaria no existe.',
                'venta_descuento_max.max'          => 'El descuento máximo no puede pasar de 100%.',
                'inventario_dias_rotacion.min'     => 'Usa al menos 7 días: con menos, el promedio no significa nada.',
            ], $this->mensajesImagen('negocio_logo'))
        );

        /* ── Logo: false = no se tocó, null = lo quitaron, string = nuevo ── */
        $logo = $this->guardarImagen(
            $request,
            'negocio',
            Setting::obtener('negocio.logo') ?: null,
            'negocio_logo'
        );

        $valores = [
            'negocio.nombre'    => $datos['negocio_nombre'],
            'negocio.lema'      => $datos['negocio_lema'] ?? '',
            'negocio.nit'       => $datos['negocio_nit'] ?? '',
            'negocio.direccion' => $datos['negocio_direccion'] ?? '',
            'negocio.telefono'  => $datos['negocio_telefono'] ?? '',
            'negocio.correo'    => $datos['negocio_correo'] ?? '',
            'negocio.ciudad'    => $datos['negocio_ciudad'] ?? '',

            'regional.idioma'           => $datos['regional_idioma'],
            'regional.moneda_codigo'    => mb_strtoupper($datos['regional_moneda_codigo']),
            'regional.moneda_simbolo'   => $datos['regional_moneda_simbolo'] ?? '',
            'regional.simbolo_posicion' => $datos['regional_simbolo_posicion'],
            'regional.decimales'        => (string) $datos['regional_decimales'],
            'regional.sep_miles'        => $datos['regional_sep_miles'],
            'regional.sep_decimal'      => $datos['regional_sep_decimal'],
            'regional.zona_horaria'     => $datos['regional_zona_horaria'],
            'regional.formato_fecha'    => $datos['regional_formato_fecha'],

            'recibo.formato'           => $datos['recibo_formato'],
            'recibo.mensaje'           => $datos['recibo_mensaje'] ?? '',
            'recibo.pie'               => $datos['recibo_pie'] ?? '',
            'recibo.copias'            => (string) $datos['recibo_copias'],
            'recibo.mostrar_logo'      => $request->boolean('recibo_mostrar_logo') ? '1' : '0',
            'recibo.mostrar_qr'        => $request->boolean('recibo_mostrar_qr') ? '1' : '0',
            'recibo.mostrar_impuestos' => $request->boolean('recibo_mostrar_impuestos') ? '1' : '0',
            'recibo.mostrar_cajero'    => $request->boolean('recibo_mostrar_cajero') ? '1' : '0',
            'recibo.mostrar_ahorro'    => $request->boolean('recibo_mostrar_ahorro') ? '1' : '0',
            'recibo.auto'              => $request->boolean('recibo_auto') ? '1' : '0',

            'venta.stock_negativo'      => $request->boolean('venta_stock_negativo') ? '1' : '0',
            'venta.cliente_obligatorio' => $request->boolean('venta_cliente_obligatorio') ? '1' : '0',
            'venta.descuento_max'       => (string) $datos['venta_descuento_max'],
            'venta.redondeo'            => $datos['venta_redondeo'],

            'inventario.alerta_activa' => $request->boolean('inventario_alerta_activa') ? '1' : '0',
            'inventario.dias_rotacion' => (string) $datos['inventario_dias_rotacion'],
            'inventario.costo_metodo'  => $datos['inventario_costo_metodo'],

            'apariencia.acento'      => $datos['apariencia_acento'],
            'apariencia.densidad'    => $datos['apariencia_densidad'],
            'apariencia.animaciones' => $request->boolean('apariencia_animaciones') ? '1' : '0',
        ];

        if ($logo !== false) {
            $valores['negocio.logo'] = $logo ?? '';
        }

        Setting::guardar($valores);

        // Si el idioma por defecto cambió y quien lo cambió no tiene uno
        // propio, se le aplica ya mismo: ver el cambio inmediatamente es
        // parte de entender qué hace el ajuste.
        if (! $request->user()?->locale) {
            $request->session()->put('idioma', $datos['regional_idioma']);
        }

        return redirect()
            ->route('settings.edit', ['t' => $request->input('pestana', 'negocio')])
            ->with('success', __('settings.guardado'));
    }

    /**
     * Las opciones de los desplegables.
     *
     * Las zonas horarias se limitan a América: la lista completa de PHP
     * trae más de 400 y encontrar «Bogota» entre ellas es peor que no tener
     * el campo. Si alguien necesita otra, se agrega aquí.
     *
     * @return array<string, array<string, string>>
     */
    private function listas(): array
    {
        $zonas = [];

        foreach (timezone_identifiers_list() as $z) {
            if (str_starts_with($z, 'America/') || str_starts_with($z, 'Europe/')) {
                $zonas[$z] = str_replace(['America/', 'Europe/', '_'], ['', '', ' '], $z)
                           . (str_starts_with($z, 'Europe/') ? ' (Europa)' : '');
            }
        }

        asort($zonas);

        $ejemplo = now();
        $fechas  = [];

        foreach (Formato::FECHAS as $clave => $patron) {
            $fechas[$clave] = $ejemplo->format($patron);
        }

        return [
            'idiomas' => EstableceIdioma::DISPONIBLES,
            'zonas'   => $zonas,
            'fechas'  => $fechas,
        ];
    }
}
