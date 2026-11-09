<?php

namespace App\Support;

use App\Models\Permiso;
use Illuminate\Support\Collection;

/**
 * Cómo se nombran, agrupan y ordenan los permisos en pantalla (SA-PER-001).
 *
 * Antes vivía como constantes privadas de RolController y se quedó atrás:
 * los módulos que llegaron después (locales, citas, cupones, agenda,
 * notificaciones) no salían en el formulario de roles, así que un rol
 * propio no podía recibirlos (H-F14). Ahora la lista sale de la BASE y lo
 * que no tenga grupo cae en «Otros»: un permiso nuevo nunca queda oculto.
 */
final class CatalogoPermisos
{
    /** El orden es el de lectura: primero lo de vender, al final lo de administrar. */
    public const GRUPOS = [
        'Vender'         => ['ventas', 'caja', 'clientes', 'cupones'],
        'Catálogo'       => ['productos', 'categorias', 'unidades', 'atributos', 'impuestos'],
        'Inventario'     => ['inventario', 'compras', 'proveedores'],
        'Agenda'         => ['citas', 'agenda'],
        'Información'    => ['panel', 'reportes', 'notificaciones'],
        'Equipo'         => ['empleados', 'rrhh', 'nomina'],
        'Administración' => ['usuarios', 'roles', 'tiendas', 'auditoria', 'medios_pago', 'configuracion'],
    ];

    public const MODULOS = [
        'ventas'         => 'Ventas',
        'caja'           => 'Caja',
        'clientes'       => 'Clientes',
        'cupones'        => 'Cupones',
        'productos'      => 'Productos',
        'categorias'     => 'Categorías',
        'unidades'       => 'Unidades',
        'atributos'      => 'Atributos',
        'impuestos'      => 'Impuestos',
        'inventario'     => 'Inventario',
        'compras'        => 'Compras',
        'proveedores'    => 'Proveedores',
        'citas'          => 'Citas',
        'agenda'         => 'Agenda',
        'panel'          => 'Panel',
        'reportes'       => 'Reportes',
        'notificaciones' => 'Notificaciones',
        'usuarios'       => 'Usuarios',
        'roles'          => 'Roles',
        'tiendas'        => 'Locales',
        'auditoria'      => 'Auditoría',
        'medios_pago'    => 'Medios de pago',
        'configuracion'  => 'Configuración',
        'empleados'      => 'Empleados',
        'rrhh'           => 'Áreas, cargos y horarios',
        'nomina'         => 'Nómina',
        'sistema'        => 'Plataforma',
    ];

    /** De menos a más: mirar, tocar, deshacer. */
    public const ORDEN = [
        'ver', 'crear', 'editar', 'eliminar', 'exportar', 'enviar',
        'anular', 'solicitar_anulacion', 'devolver', 'aprobar_devolucion', 'verificar_pagos', 'cambiar_precio',
        'abrir', 'cerrar', 'cerrar_ajena', 'movimientos', 'gestionar_cajas',
        'ajustar', 'recibir', 'recursos', 'gestionar',
        'aprobar', 'novedades', 'aprobar_novedades', 'consolidado',
    ];

    public const ACCIONES = [
        'ver'                => 'Ver',
        'crear'              => 'Crear',
        'editar'             => 'Editar',
        'eliminar'           => 'Eliminar',
        'exportar'           => 'Exportar y enviar',
        'enviar'             => 'Enviar avisos',
        'anular'             => 'Anular',
        'solicitar_anulacion' => 'Pedir anulación',
        'devolver'           => 'Recibir devoluciones',
        'aprobar_devolucion' => 'Aprobar devoluciones',
        'verificar_pagos'    => 'Verificar pagos',
        'cambiar_precio'     => 'Cambiar el precio al cobrar',
        'abrir'              => 'Abrir',
        'cerrar'             => 'Cerrar (arqueo)',
        'cerrar_ajena'       => 'Cerrar turnos de otros',
        'movimientos'        => 'Ingresos y retiros',
        'gestionar_cajas'    => 'Gestionar cajas',
        'ajustar'            => 'Ajustar a mano',
        'recibir'            => 'Recibir mercancía',
        'recursos'           => 'Recursos y horarios',
        'gestionar'          => 'Gestionar',
        'configurar'         => 'Configurar',
        'aprobar'            => 'Aprobar y pagar',
        'novedades'          => 'Registrar novedades',
        'aprobar_novedades'  => 'Aprobar novedades',
        'consolidado'        => 'Consolidado multitienda',
        'superadmin'         => 'Panel de plataforma',
    ];

    /** Tipo de acción que pide la HU: lectura, escritura o exportación. */
    public const TIPOS = ['lectura' => 'Lectura', 'escritura' => 'Escritura', 'exportacion' => 'Exportación'];

    public static function modulo(string $clave): string
    {
        return self::MODULOS[$clave] ?? ucfirst(str_replace('_', ' ', $clave));
    }

    public static function accion(string $clave): string
    {
        return self::ACCIONES[$clave] ?? ucfirst(str_replace('_', ' ', $clave));
    }

    public static function tipoDe(string $accion): string
    {
        return match ($accion) {
            'ver'      => 'lectura',
            'exportar' => 'exportacion',
            default    => 'escritura',
        };
    }

    /**
     * Los permisos agrupados como se leen: [grupo => [módulo => [nombre => etiqueta]]].
     * Lo que no está en ningún grupo cae en «Otros». `sistema.*` nunca sale.
     *
     * @param Collection<int, Permiso>|null $permisos
     * @return array<string, array<string, array<string, string>>>
     */
    public static function agrupados(?Collection $permisos = null): array
    {
        $permisos ??= Permiso::query()->orderBy('name')->get();
        $porModulo = $permisos
            ->reject(fn (Permiso $p) => $p->modulo() === 'sistema')
            ->groupBy(fn (Permiso $p) => $p->modulo());

        $conGrupo = collect(self::GRUPOS)->flatten()->all();
        $grupos   = self::GRUPOS + ['Otros' => $porModulo->keys()->diff($conGrupo)->sort()->values()->all()];

        $salida = [];

        foreach ($grupos as $titulo => $modulos) {
            foreach ($modulos as $modulo) {
                $delModulo = $porModulo->get($modulo);

                if (! $delModulo || $delModulo->isEmpty()) {
                    continue;
                }

                $salida[$titulo][self::modulo($modulo)] = $delModulo
                    ->sortBy(function (Permiso $p) {
                        $posicion = array_search($p->accion(), self::ORDEN, true);

                        return $posicion === false ? 99 : $posicion;
                    })
                    ->mapWithKeys(fn (Permiso $p) => [$p->name => $p->etiqueta()])
                    ->all();
            }
        }

        return $salida;
    }
}
