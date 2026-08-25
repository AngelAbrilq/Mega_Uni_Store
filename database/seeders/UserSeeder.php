<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios de trabajo, uno por rol, para poder demostrar los permisos.
 * Todos usan la misma contraseña: password
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $equipo = [
            ['Angel Nicolás Abril', 'angelnicolasabrilq@gmail.com', 'Superadministrador'],
            ['Administrador',       'admin@megaunistore.test',      'Administrador'],
            ['Sandra Supervisora',  'supervisor@megaunistore.test', 'Supervisor'],
            ['Valentina Vendedora', 'vendedor@megaunistore.test',   'Vendedor'],
            ['Camilo Cajero',       'cajero@megaunistore.test',     'Cajero'],
            ['Bernardo Bodeguero',  'bodega@megaunistore.test',     'Bodeguero'],
            ['Rocío Reportes',      'reportes@megaunistore.test',   'Reportero'],
        ];

        foreach ($equipo as [$nombre, $correo, $rol]) {
            // Asignación directa (no mass assignment): así no depende de
            // que email_verified_at esté en $fillable.
            $usuario = User::firstOrNew(['email' => $correo]);
            $usuario->name              = $nombre;
            $usuario->email             = $correo;
            $usuario->password          = Hash::make('password');
            $usuario->email_verified_at = now();
            $usuario->save();

            $usuario->syncRoles([$rol]);
        }

        /**
         * Red de seguridad: cualquier cuenta creada antes de que existieran
         * los roles se quedaría sin acceso a nada. Se le da Administrador.
         */
        $huerfanos = User::doesntHave('roles')->get();

        foreach ($huerfanos as $usuario) {
            $usuario->assignRole('Administrador');
        }

        $this->command?->info('  Usuarios: ' . count($equipo) . ' (contraseña: password)');

        if ($huerfanos->isNotEmpty()) {
            $this->command?->warn('  ' . $huerfanos->count() . ' cuenta(s) sin rol recibieron Administrador.');
        }
    }
}
