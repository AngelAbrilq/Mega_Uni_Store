<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Contexto;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * H-F06 · Nadie reparte más poder del que tiene.
 * H-F07 · El Administrador (dueño) sí configura su propio negocio.
 *
 * Antes: el Administrador de un negocio podía asignar «Superadministrador»
 * (rol del sistema con `sistema.superadmin`) o crear un rol propio con ese
 * permiso, y así entrar al panel de TODAS las empresas.
 */
class EscaladaDePrivilegiosTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private User $admin;
    private User $plataforma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->empresa = Empresa::create([
            'nombre' => 'Ferretería', 'slug' => 'ferreteria-escalada',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Principal',
            'slug' => 'principal-escalada', 'es_principal' => true, 'activa' => true,
        ]);

        $this->admin      = $this->usuario('Administrador', dueno: true);
        $this->plataforma = $this->usuario('Superadministrador');
    }

    /* ═══════════════ H-F06 · Asignar roles ═══════════════ */

    public function test_el_administrador_no_puede_asignar_superadministrador(): void
    {
        $this->actingAs($this->admin)
            ->from('/users/create')
            ->post('/users', $this->nuevoUsuario(['Superadministrador']))
            ->assertSessionHasErrors('roles.0');

        $this->assertDatabaseMissing('users', ['email' => 'colado@ejemplo.com']);
    }

    public function test_el_administrador_si_puede_asignar_roles_inferiores(): void
    {
        $this->actingAs($this->admin)
            ->post('/users', $this->nuevoUsuario(['Cajero']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'colado@ejemplo.com']);
    }

    public function test_el_superadministrador_conserva_la_capacidad_de_asignarlo(): void
    {
        $this->actingAs($this->plataforma)
            ->post('/users', $this->nuevoUsuario(['Superadministrador']))
            ->assertSessionHasNoErrors();
    }

    /* ═══════════════ H-F06 · Roles propios ═══════════════ */

    public function test_no_se_puede_crear_un_rol_propio_con_permiso_de_plataforma(): void
    {
        $this->actingAs($this->admin)
            ->from('/roles/create')
            ->post('/roles', ['name' => 'Puerta trasera', 'permisos' => ['ventas.ver', 'sistema.superadmin']])
            ->assertSessionHasErrors('permisos.1');

        $this->assertFalse(Rol::where('name', 'Puerta trasera')->exists());
    }

    public function test_un_rol_propio_con_permisos_propios_si_se_crea(): void
    {
        $this->actingAs($this->admin)
            ->post('/roles', ['name' => 'Mostrador', 'permisos' => ['ventas.ver', 'ventas.crear']])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Rol::where('name', 'Mostrador')->exists());
    }

    /* ═══════════════ H-F06 · Editar a un superior ═══════════════ */

    public function test_el_administrador_no_edita_ni_borra_a_un_superadministrador(): void
    {
        $this->actingAs($this->admin)->get("/users/{$this->plataforma->id}/edit")->assertForbidden();
        $this->actingAs($this->admin)->delete("/users/{$this->plataforma->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->plataforma->id]);
    }

    /* ═══════════════ H-F07 · Configuración propia ═══════════════ */

    public function test_el_administrador_configura_su_negocio(): void
    {
        $this->actingAs($this->admin)->get('/settings')->assertOk();
    }

    public function test_el_administrador_sigue_sin_ver_el_panel_de_plataforma(): void
    {
        $this->actingAs($this->admin)->get('/sistema')->assertForbidden();
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function usuario(string $rol, bool $dueno = false): User
    {
        $u = User::factory()->create();
        $u->empresas()->sync([$this->empresa->id => ['es_dueno' => $dueno]]);
        $u->tiendas()->sync([$this->empresa->tiendas()->first()->id]);

        Contexto::comoEmpresa($this->empresa->id, null, fn () => $u->syncRoles([$rol]));
        Contexto::olvidar();

        return $u->fresh();
    }

    /** @param array<int, string> $roles */
    private function nuevoUsuario(array $roles): array
    {
        return [
            'name'                  => 'Colado',
            'email'                 => 'colado@ejemplo.com',
            'password'              => 'ClaveLarga123',
            'password_confirmation' => 'ClaveLarga123',
            'roles'                 => $roles,
        ];
    }
}
