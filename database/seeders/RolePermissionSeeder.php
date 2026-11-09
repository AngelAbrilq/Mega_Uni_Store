<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso as Permission;
use App\Models\Rol as Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos del sistema (spatie/laravel-permission).
 *
 * Hasta ahora los paquetes estaban instalados y las tablas migradas, pero
 * no había un solo rol creado: cualquier usuario autenticado podía entrar
 * a todos los módulos. Este seeder cierra ese hueco.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Módulos con el patrón completo ver / crear / editar / eliminar.
     *
     * @var array<string, string>
     */
    private array $modulos = [
        'productos'    => 'Productos',
        'categorias'   => 'Categorías',
        'clientes'     => 'Clientes',
        'proveedores'  => 'Proveedores',
        'unidades'     => 'Unidades de medida',
        'impuestos'    => 'Impuestos',
        'medios_pago'  => 'Medios de pago',
        'atributos'    => 'Atributos',
        'usuarios'     => 'Usuarios',
        'tiendas'      => 'Locales',
        'ventas'       => 'Ventas',
        'compras'      => 'Compras',
        'citas'        => 'Citas de la agenda',
        'cupones'      => 'Cupones de descuento', // AT-CUP-001
        'empleados'    => 'Empleados',            // AT-EMP-001 (RN-44)
    ];

    /**
     * Permisos que no siguen el patrón de cuatro acciones.
     *
     * @var array<int, string>
     */
    private array $sueltos = [
        'panel.ver',            // entrar al dashboard
        'reportes.ver',         // informes y gráficas
        'reportes.exportar',    // exportar a PDF/Excel y enviar por email/WhatsApp (RP-EXP-001)
        'notificaciones.enviar',// enviar avisos a los usuarios del negocio (SA-NOT-001)
        'ventas.anular',        // deshacer una venta ya cobrada
        'ventas.solicitar_anulacion', // pedir la anulación para que un supervisor la apruebe (VD-VTA-002, RN-43)
        'ventas.devolver',      // recibir una devolución parcial
        'ventas.aprobar_devolucion', // aprobar o rechazar devoluciones pendientes (RN-30)
        'ventas.verificar_pagos', // aprobar o rechazar pagos pendientes de verificación (RN-32)
        'ventas.cambiar_precio',// cobrar a un precio distinto al del local (H-F01)
        'caja.ver',             // consultar turnos de caja
        'caja.abrir',
        'caja.cerrar',          // hacer el arqueo
        'caja.cerrar_ajena',    // arquear el turno que abrió OTRA persona (H-04)
        'caja.movimientos',     // ingresos y retiros de efectivo en el turno (AT-CAJ-001)
        'caja.gestionar_cajas', // crear y editar las cajas registradoras del local (AT-CAJ-001)
        'inventario.ver',       // consultar el kardex
        'inventario.ajustar',   // mover existencias a mano
        'compras.recibir',      // dar entrada a la mercancía
        'auditoria.ver',        // quién cambió qué
        'roles.gestionar',      // asignar roles a usuarios
        'configuracion.editar', // parámetros del sistema
        // El panel de superadministrador. Solo lo tiene ese rol, y por eso
        // se excluye a mano de todos los demás más abajo: es el único
        // permiso que no es «de un negocio» sino «de todos los negocios».
        'agenda.recursos',      // quién atiende, sus horarios y bloqueos
        'rrhh.configurar',      // áreas, cargos y horarios del local (AT-ARE/CGO/HOR-001)
        // Nómina (RN-45). Fuera de $modulos a propósito: los salarios son
        // reservados, así que Supervisor y Reportero no heredan «nomina.ver».
        'nomina.ver',               // nóminas, desprendibles y reportes del local
        'nomina.gestionar',         // contratos, conceptos, liquidar, prestaciones (NR-NOM-001/002/003/007)
        'nomina.aprobar',           // aprobar y registrar el pago de la nómina (NR-NOM-003 E6)
        'nomina.novedades',         // registrar horas extra y ausencias (NR-NOM-005/006)
        'nomina.aprobar_novedades', // aprobar o rechazar horas extra y vacaciones
        'nomina.consolidado',       // consolidado de todas las tiendas (NR-NOM-009, Superadministrador)
        'sistema.superadmin',
    ];

    /**
     * Todos los permisos que el sistema define, en una lista.
     *
     * Es público y estático para que la prueba pueda contarlos sin
     * copiar el número a mano. Antes la prueba decía «56» y el día que se
     * agregó uno se puso roja sin que nada estuviera mal — y una prueba
     * que se pone roja por tener razón enseña a ignorar los rojos.
     *
     * @return array<int, string>
     */
    public static function permisos(): array
    {
        $seeder = new self();
        $todos  = [];

        foreach (array_keys($seeder->modulos) as $modulo) {
            foreach (['ver', 'crear', 'editar', 'eliminar'] as $accion) {
                $todos[] = "{$modulo}.{$accion}";
            }
        }

        return array_values(array_unique(array_merge($todos, $seeder->sueltos)));
    }

    public function run(): void
    {
        // Limpia la caché de permisos antes y después de tocar la tabla.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        /* ─────────── 1. Permisos ─────────── */
        $todos = [];

        foreach (array_keys($this->modulos) as $modulo) {
            foreach (['ver', 'crear', 'editar', 'eliminar'] as $accion) {
                $todos[] = "{$modulo}.{$accion}";
            }
        }

        $todos = array_merge($todos, $this->sueltos);

        foreach ($todos as $nombre) {
            // SA-PER-001: los del seeder son «del sistema» (el código los usa).
            [$modulo, $accion] = explode('.', $nombre, 2);
            Permission::findOrCreate($nombre, $guard)
                ->forceFill(['module' => $modulo, 'action' => $accion, 'is_system' => true])
                ->save();
        }

        /* ─────────── 2. Roles ─────────── */

        // Solo lectura de todos los módulos.
        $soloVer = array_map(
            fn (string $m) => "{$m}.ver",
            array_keys($this->modulos)
        );

        $definicion = [
            'Superadministrador' => $todos,

            // H-F07: el dueño configura su propio negocio (moneda, recibo, reglas de venta).
            // Solo el panel de plataforma queda reservado al Superadministrador.
            'Administrador' => array_values(array_diff($todos, [
                // Y muchísimo menos ve los negocios de los demás clientes.
                'sistema.superadmin',
                // NR-NOM-009: el consolidado multitienda es del Superadministrador.
                'nomina.consolidado',
            ])),

            'Supervisor' => array_merge(
                $soloVer,
                [
                    'panel.ver', 'reportes.ver', 'reportes.exportar', 'auditoria.ver',
                    'citas.crear', 'citas.editar', 'agenda.recursos',
                    'inventario.ver', 'inventario.ajustar',
                    'caja.ver', 'caja.cerrar_ajena', 'caja.movimientos', 'ventas.anular', 'ventas.devolver', 'ventas.cambiar_precio',
                    'ventas.aprobar_devolucion', 'ventas.verificar_pagos',
                    'nomina.novedades', // NR-NOM-005 E1: «El Admin o Supervisor registra horas extras»
                ],
                $this->permisosDe(['productos', 'categorias'], ['crear', 'editar'])
            ),

            'Cajero' => [
                'panel.ver',
                'citas.ver', 'citas.crear', 'citas.editar',
                'ventas.ver', 'ventas.crear', 'ventas.devolver', 'ventas.solicitar_anulacion',
                'caja.ver', 'caja.abrir', 'caja.cerrar', 'caja.movimientos',
                'productos.ver', 'categorias.ver', 'medios_pago.ver', 'impuestos.ver',
                'clientes.ver', 'clientes.crear',
            ],

            'Vendedor' => array_merge(
                [
                    'panel.ver',
                    'citas.ver', 'citas.crear', 'citas.editar',
                    'ventas.ver', 'ventas.crear', 'ventas.devolver', 'ventas.solicitar_anulacion',
                    'caja.ver', 'caja.abrir', 'caja.cerrar', 'caja.movimientos',
                    'productos.ver', 'categorias.ver', 'medios_pago.ver', 'impuestos.ver',
                    'inventario.ver',
                ],
                $this->permisosDe(['clientes'])
            ),

            'Bodeguero' => array_merge(
                [
                    'panel.ver',
                    'inventario.ver', 'inventario.ajustar',
                    'proveedores.ver', 'categorias.ver', 'unidades.ver',
                    'productos.ver', 'productos.crear', 'productos.editar',
                    'compras.recibir',
                    // BD-DEV-001: recibe la mercancía devuelta; queda pendiente
                    // hasta que un supervisor la apruebe (RN-30).
                    'ventas.ver', 'ventas.devolver',
                ],
                $this->permisosDe(['compras'], ['ver', 'crear', 'editar'])
            ),

            'Reportero' => array_merge($soloVer, [
                'panel.ver', 'reportes.ver', 'reportes.exportar', 'inventario.ver', 'caja.ver',
            ]),
        ];

        /**
         * Los siete son del sistema: `empresa_id` en nulo.
         *
         * Se usa `firstOrCreate` de Eloquent y no `findOrCreate` de spatie
         * porque aquel busca sin salirse del negocio activo. Si este seeder
         * se corriera parado dentro de una empresa que ya tiene un rol
         * suyo llamado «Cajero», `findOrCreate` lo encontraría y le
         * escribiría encima los permisos del Cajero del sistema — se
         * llevaría por delante la configuración de ese cliente.
         *
         * Diciendo `empresa_id => null` no hay ambigüedad posible.
         */
        // Por si el seeder corre antes de la migración que agrega la
        // columna: se busca igual, solo que sin ella.
        $ambito = \Illuminate\Support\Facades\Schema::hasColumn('roles', 'empresa_id')
            ? ['empresa_id' => null]
            : [];

        foreach ($definicion as $nombre => $permisos) {
            $rol = Role::firstOrCreate(['name' => $nombre, 'guard_name' => $guard] + $ambito);

            $rol->syncPermissions(array_values(array_unique($permisos)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            '  Roles: %d · Permisos: %d',
            count($definicion),
            count($todos)
        ));
    }

    /**
     * Devuelve "modulo.accion" para cada combinación pedida.
     *
     * @param  array<int, string>  $modulos
     * @param  array<int, string>  $acciones
     * @return array<int, string>
     */
    private function permisosDe(array $modulos, array $acciones = ['ver', 'crear', 'editar', 'eliminar']): array
    {
        $salida = [];

        foreach ($modulos as $m) {
            foreach ($acciones as $a) {
                $salida[] = "{$m}.{$a}";
            }
        }

        return $salida;
    }
}
