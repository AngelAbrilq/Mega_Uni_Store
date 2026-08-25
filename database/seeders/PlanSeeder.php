<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Los planes de MEGA UNI STORE.
 *
 * ── Los precios ──
 *
 * $29.000 / $79.000 / $299.000 al mes, en pesos. Son los que cuadran con
 * el comercio de barrio colombiano, que es a quien se le vende: una
 * ferretería o una peluquería no compara contra software gringo, compara
 * contra el cuaderno y contra lo que le cobra el contador.
 *
 * El anual es el mensual menos 20%. Se guarda como número y no como
 * porcentaje: el día que se decida que el descuento es otro, o que solo
 * aplica a un plan, no hay que tocar código.
 *
 * ── Por qué los límites son los que son ──
 *
 * Básico limita productos (500) porque una tienda de barrio no llega, y
 * limita usuarios (3) porque ahí está la línea entre «el dueño y su
 * ayudante» y «un negocio con turnos». Pro abre las dos cosas y agrega la
 * segunda sede — que es el momento en que un cliente empieza a valer de
 * verdad. Empresa no limita nada: a esa altura, el límite le costaría al
 * vendedor más de lo que le ahorra al servidor.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            [
                'slug'                => 'demo',
                'nombre'              => 'Demostración',
                'descripcion'         => 'La prueba de 2 h 30 con datos de ejemplo.',
                'precio_mensual'      => 0,
                'precio_anual'        => 0,
                'precio_tienda_extra' => 0,
                'max_tiendas'         => 2,
                'max_usuarios'        => 3,
                'max_productos'       => 200,
                'max_ventas_mes'      => 100,
                'funciones'           => ['pos', 'inventario', 'reportes'],
                'dias_prueba'         => 0,
                'es_demo'             => true,
                'visible'             => false,
                'orden'               => 0,
            ],
            [
                'slug'                => 'basico',
                'nombre'              => 'Básico',
                'descripcion'         => 'Para un local, con lo necesario para vender y controlar.',
                'precio_mensual'      => 29_000,
                'precio_anual'        => 278_400,      // 29.000 × 12 − 20%
                'precio_tienda_extra' => 12_000,
                'max_tiendas'         => 1,
                'max_usuarios'        => 3,
                'max_productos'       => 500,
                'max_ventas_mes'      => 1_000,
                'funciones'           => ['pos', 'inventario', 'caja', 'reportes'],
                'dias_prueba'         => 15,
                'es_demo'             => false,
                'visible'             => true,
                'orden'               => 1,
            ],
            [
                'slug'                => 'pro',
                'nombre'              => 'Pro',
                'descripcion'         => 'Para el que ya tiene dos sedes y necesita verlas juntas.',
                'precio_mensual'      => 79_000,
                'precio_anual'        => 758_400,      // 79.000 × 12 − 20%
                'precio_tienda_extra' => 12_000,
                'max_tiendas'         => 3,
                'max_usuarios'        => 10,
                'max_productos'       => 5_000,
                'max_ventas_mes'      => 10_000,
                'funciones'           => [
                    'pos', 'inventario', 'caja', 'reportes',
                    'compras', 'traslados', 'consolidado', 'auditoria',
                ],
                'dias_prueba'         => 15,
                'es_demo'             => false,
                'visible'             => true,
                'orden'               => 2,
            ],
            [
                'slug'                => 'empresa',
                'nombre'              => 'Empresa',
                'descripcion'         => 'Sin topes, con acompañamiento.',
                'precio_mensual'      => 299_000,
                'precio_anual'        => 2_870_400,    // 299.000 × 12 − 20%
                'precio_tienda_extra' => 0,            // incluidas
                'max_tiendas'         => null,
                'max_usuarios'        => null,
                'max_productos'       => null,
                'max_ventas_mes'      => null,
                'funciones'           => [
                    'pos', 'inventario', 'caja', 'reportes',
                    'compras', 'traslados', 'consolidado', 'auditoria',
                    'agenda', 'portal', 'soporte_prioritario',
                ],
                'dias_prueba'         => 15,
                'es_demo'             => false,
                'visible'             => true,
                'orden'               => 3,
            ],
        ];

        foreach ($planes as $datos) {
            // `updateOrCreate` por slug: correr el seeder dos veces actualiza
            // los precios en vez de duplicar los planes. Y si mañana suben,
            // se cambian aquí y se vuelve a sembrar.
            Plan::updateOrCreate(['slug' => $datos['slug']], $datos);
        }

        $this->command?->info('  Planes: ' . count($planes) . ' (los existentes se actualizaron)');
    }
}
