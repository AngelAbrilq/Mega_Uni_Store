<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
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
        'ventas'       => 'Ventas',
        'compras'      => 'Compras',
        'citas'        => 'Citas de la agenda',
    ];

    /**
     * Permisos que no siguen el patrón de cuatro acciones.
     *
     * @var array<int, string>
     */
    private array $sueltos = [
        'panel.ver',            // entrar al dashboard
        'reportes.ver',         // informes y gráficas
        'ventas.anular',        // deshacer una venta ya cobrada
        'ventas.devolver',      // recibir una devolución parcial
        'caja.ver',             // consultar turnos de caja
        'caja.abrir',
        'caja.cerrar',          // hacer el arqueo
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
            Permission::findOrCreate($nombre, $guard);
        }

        /* ─────────── 2. Roles ─────────── */

        // Solo lectura de todos los módulos.
        $soloVer = array_map(
            fn (string $m) => "{$m}.ver",
            array_keys($this->modulos)
        );

        $definicion = [
            'Superadministrador' => $todos,

            'Administrador' => array_values(array_diff($todos, [
                // El administrador de tienda no toca los parámetros globales.
                'configuracion.editar',
                // Y muchísimo menos ve los negocios de los demás clientes.
                'sistema.superadmin',
            ])),

            'Supervisor' => array_merge(
                $soloVer,
                [
                    'panel.ver', 'reportes.ver', 'auditoria.ver',
                    'citas.crear', 'citas.editar', 'agenda.recursos',
                    'inventario.ver', 'inventario.ajustar',
                    'caja.ver', 'ventas.anular', 'ventas.devolver',
                ],
                $this->permisosDe(['productos', 'categorias'], ['crear', 'editar'])
            ),

            'Cajero' => [
                'panel.ver',
                'citas.ver', 'citas.crear', 'citas.editar',
                'ventas.ver', 'ventas.crear', 'ventas.devolver',
                'caja.ver', 'caja.abrir', 'caja.cerrar',
                'productos.ver', 'categorias.ver', 'medios_pago.ver', 'impuestos.ver',
                'clientes.ver', 'clientes.crear',
            ],

            'Vendedor' => array_merge(
                [
                    'panel.ver',
                    'citas.ver', 'citas.crear', 'citas.editar',
                    'ventas.ver', 'ventas.crear', 'ventas.devolver',
                    'caja.ver', 'caja.abrir', 'caja.cerrar',
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
                ],
                $this->permisosDe(['compras'], ['ver', 'crear', 'editar'])
            ),

            'Reportero' => array_merge($soloVer, [
                'panel.ver', 'reportes.ver', 'inventario.ver', 'caja.ver',
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
