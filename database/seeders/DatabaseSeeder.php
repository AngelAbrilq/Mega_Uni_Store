<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use Illuminate\Database\Seeder;

/**
 * Punto de entrada de `php artisan db:seed`.
 *
 * El orden importa: los permisos existen antes que los usuarios, y los
 * datos maestros (unidades, impuestos, categorías, proveedores) existen
 * antes que los productos que los referencian.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * Antes esto usaba `WithoutModelEvents` para no llenar la bitácora
         * con mil filas de «producto creado» durante la siembra. El problema
         * es que ese rasgo apaga TODOS los eventos del modelo, incluidos los
         * que rellenan la empresa y la tienda — y un producto sembrado sin
         * dueño no se puede guardar.
         *
         * Ahora se silencia solo la auditoría, que es lo que molestaba.
         */
        AuditLog::silenciar();

        $this->command?->line('');
        $this->command?->info('Sembrando MEGA UNI STORE…');

        $this->call([
            PlanSeeder::class,             // 0. el catálogo de planes
            EmpresaSeeder::class,          // 1. la empresa y su primera tienda
            SettingSeeder::class,          // 2. parámetros del negocio
            RolePermissionSeeder::class,   // 3. permisos y roles
            UserSeeder::class,             // 4. equipo de trabajo
            CatalogSeeder::class,          // 5. unidades, impuestos, medios de pago, atributos, categorías
            PeopleSeeder::class,           // 6. proveedores y clientes
            ProductSeeder::class,          // 7. catálogo de productos
            DemoSeeder::class,             // 8. movimiento de demostración
        ]);

        // Los usuarios se crean en el paso 3, cuando la empresa ya existe
        // pero todavía no tiene a quién enlazar. Se cuadra al final.
        EmpresaSeeder::enlazarUsuarios();

        AuditLog::escuchar();

        $this->command?->line('');
        $this->command?->info('Listo. Entra con angelnicolasabrilq@gmail.com / password');
        $this->command?->line('');
    }
}
