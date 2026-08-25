<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::guardar([
            'negocio.nombre'    => 'MEGA UNI STORE',
            'negocio.lema'      => 'Tienda universitaria',
            'negocio.nit'       => '901.234.567-8',
            'negocio.direccion' => 'Calle 52 # 13-65, bloque C',
            'negocio.ciudad'    => 'Bogotá D.C.',
            'negocio.telefono'  => '601 555 0180',
            'negocio.correo'    => 'tienda@megaunistore.test',
            'recibo.mensaje'    => '¡Gracias por tu compra!',
            'recibo.pie'        => 'Cambios dentro de los 5 días siguientes presentando este recibo.',
            'venta.stock_negativo' => '0',
        ], 'negocio');

        $this->command?->info('  Configuración del negocio lista');
    }
}
