<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\GuardarAjustesRequest;
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

    public function update(GuardarAjustesRequest $request)
    {
        $datos = $request->validated();

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
            'venta.puntos_activos'      => $request->boolean('venta_puntos_activos') ? '1' : '0',
            'venta.pesos_por_punto'     => (string) ($datos['venta_pesos_por_punto'] ?? 1000),

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
        // El interruptor de alertas debe notarse ya, no cuando venza la caché.
        \App\Services\AlertService::olvidar();

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
