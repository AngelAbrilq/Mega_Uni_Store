<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\Rol as Role;

/**
 * Crea o repara la cuenta de superadministrador.
 *
 *     php artisan mus:admin
 *     php artisan mus:admin correo@ejemplo.com --password=miClave
 *
 * Sirve cuando la base se vació, cuando el seeder falló a mitad de camino
 * o cuando alguien se quedó por fuera del panel.
 */
class CrearAdmin extends Command
{
    protected $signature = 'mus:admin
                            {email? : Correo de la cuenta}
                            {--password= : Contraseña (por defecto: password)}
                            {--name= : Nombre para mostrar}';

    protected $description = 'Crea o repara la cuenta de superadministrador';

    public function handle(): int
    {
        if (! Schema::hasTable('users')) {
            $this->error('La tabla users no existe. Corre primero: php artisan migrate');

            return self::FAILURE;
        }

        if (! Schema::hasTable('roles')) {
            $this->error('Las tablas de permisos no existen. Corre primero: php artisan migrate');

            return self::FAILURE;
        }

        /* ─── 1. Roles y permisos ─── */
        if (! Role::where('name', 'Superadministrador')->exists()) {
            $this->line('Creando roles y permisos…');
            $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        }

        if (! Role::where('name', 'Superadministrador')->exists()) {
            $this->error('No se pudo crear el rol Superadministrador. Revisa RolePermissionSeeder.');

            return self::FAILURE;
        }

        /* ─── 2. La cuenta ─── */
        $correo = $this->argument('email') ?: 'angelnicolasabrilq@gmail.com';
        $clave  = $this->option('password') ?: 'password';
        $nombre = $this->option('name') ?: 'Angel Nicolás Abril';

        $usuario = User::firstOrNew(['email' => $correo]);
        $nuevo   = ! $usuario->exists;

        $usuario->name              = $usuario->name ?: $nombre;
        $usuario->email             = $correo;
        $usuario->password          = Hash::make($clave);
        $usuario->email_verified_at = now();
        $usuario->save();

        $usuario->syncRoles(['Superadministrador']);

        /* ─── 3. Que tenga por dónde entrar ───
           Un superadministrador sin negocio asignado recibe un 403 en la
           puerta, por más permisos que tenga: el guardián de contexto pide
           empresa antes de mirar el rol. Y este comando se corre justo
           cuando alguien se quedó por fuera, así que también repara eso. */
        $negocios = 0;

        if (Schema::hasTable('empresa_usuario') && $usuario->empresas()->count() === 0) {
            foreach (\App\Models\Empresa::query()->pluck('id') as $empresaId) {
                $usuario->empresas()->attach($empresaId, ['es_dueno' => true]);
                $negocios++;
            }
        }

        /* ─── 4. Cuentas huérfanas ─── */
        $huerfanos = User::doesntHave('roles')->get();

        foreach ($huerfanos as $u) {
            $u->assignRole('Administrador');
        }

        /* ─── 5. Resumen ─── */
        $this->newLine();
        $this->info($nuevo ? '  Cuenta creada' : '  Cuenta actualizada');
        $this->newLine();
        $this->line('  Correo:     ' . $correo);
        $this->line('  Contraseña: ' . $clave);
        $this->line('  Rol:        Superadministrador (' . $usuario->getAllPermissions()->count() . ' permisos)');
        $this->newLine();

        if ($negocios > 0) {
            $this->line('  Negocios:   ' . $negocios . ' asignado(s)');
            $this->newLine();
        }

        if ($huerfanos->isNotEmpty()) {
            $this->warn('  ' . $huerfanos->count() . ' cuenta(s) sin rol recibieron Administrador.');
            $this->newLine();
        }

        $this->line('  Entra en http://localhost:8000/login');
        $this->newLine();

        return self::SUCCESS;
    }
}
