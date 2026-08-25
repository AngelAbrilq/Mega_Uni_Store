<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comprueba que el control de acceso funciona de verdad: que los roles
 * existen, que un vendedor no entra donde no debe y que el
 * superadministrador pasa por encima de todo.
 */
class RolesYPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_el_seeder_crea_los_roles_y_permisos(): void
    {
        $this->assertSame(7, Role::count());
        /**
         * Se cuentan contra la lista que el seeder declara, no contra un
         * número escrito a mano.
         *
         * El número escrito a mano se pone rojo cada vez que se agrega un
         * permiso legítimo — y una prueba que falla por tener razón enseña
         * a ignorar los rojos. Así, la prueba sigue siendo un canario: si
         * un permiso se creara por fuera del seeder, o si el seeder no
         * alcanzara a crear los suyos, la cuenta no daría.
         */
        $declarados = RolePermissionSeeder::permisos();

        $this->assertSame(count($declarados), Permission::count());

        foreach ($declarados as $permiso) {
            $this->assertTrue(
                Permission::where('name', $permiso)->exists(),
                "El seeder declara «{$permiso}» pero no lo creó."
            );
        }

        // Y los que no pueden faltar por nombre, porque hay código que los
        // exige literalmente.
        foreach (['sistema.superadmin', 'roles.gestionar', 'configuracion.editar'] as $clave) {
            $this->assertContains($clave, $declarados);
        }

        foreach (['Superadministrador', 'Administrador', 'Supervisor', 'Cajero', 'Vendedor', 'Bodeguero', 'Reportero'] as $rol) {
            $this->assertTrue(Role::where('name', $rol)->exists(), "Falta el rol {$rol}");
        }
    }

    public function test_un_vendedor_no_puede_abrir_el_modulo_de_usuarios(): void
    {
        $vendedor = User::factory()->create();
        $vendedor->assignRole('Vendedor');

        $this->actingAs($vendedor)->get('/users')->assertForbidden();
    }

    public function test_un_vendedor_si_puede_abrir_clientes(): void
    {
        $vendedor = User::factory()->create();
        $vendedor->assignRole('Vendedor');

        $this->actingAs($vendedor)->get('/customers')->assertOk();
    }

    public function test_el_superadministrador_entra_a_todo(): void
    {
        $jefe = User::factory()->create();
        $jefe->assignRole('Superadministrador');

        foreach (['/dashboard', '/products', '/categories', '/users', '/taxes'] as $ruta) {
            $this->actingAs($jefe)->get($ruta)->assertOk();
        }
    }

    public function test_un_usuario_sin_rol_no_entra_al_panel(): void
    {
        $nadie = User::factory()->create();

        $this->actingAs($nadie)->get('/dashboard')->assertForbidden();
    }
}
