<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use App\Support\Contexto;
use Tests\Concerns\MontaNegocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * SA-PER-001 · Gestionar permisos y asignarlos a roles.
 *   1 · Crear permiso (módulo, acción, tipo).
 *   2 · Asignarlo a un rol · 3 · Quitarlo (se pierde de inmediato).
 *   4 · Ver los permisos de un rol agrupados por módulo y tipo.
 * H-F14 · Los módulos nuevos (cupones, locales, citas, notificaciones…) sí
 *         salen en el formulario de roles.
 */
class PermisosCatalogoTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $angel;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('permisos');
        $this->admin = $this->empleado('Administrador');
        $this->angel = $this->empleado('Superadministrador');
    }

    /* ═══════════════ 1 · Crear ═══════════════ */

    public function test_el_superadministrador_crea_un_permiso_con_nombre_normalizado(): void
    {
        $this->actingAs($this->angel)
            ->post('/sistema/permisos', ['modulo_nuevo' => 'Contabilidad', 'action' => 'Exportar', 'description' => 'Bajar el libro diario'])
            ->assertRedirect('/sistema/permisos')
            ->assertSessionHasNoErrors();

        $p = Permiso::where('name', 'contabilidad.exportar')->firstOrFail();
        $this->assertFalse($p->is_system);
        $this->assertSame('exportacion', $p->tipo());
        $this->assertTrue(Rol::whereNull('empresa_id')->where('name', 'Administrador')->first()->hasPermissionTo('contabilidad.exportar'),
            'Nace en el Administrador para que cada dueño pueda repartirlo.');
    }

    public function test_no_se_duplica_ni_se_crea_en_el_modulo_sistema(): void
    {
        $this->actingAs($this->angel)->from('/sistema/permisos/create')
            ->post('/sistema/permisos', ['module' => 'ventas', 'action' => 'ver'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->angel)->from('/sistema/permisos/create')
            ->post('/sistema/permisos', ['modulo_nuevo' => 'sistema', 'action' => 'todo'])
            ->assertSessionHasErrors('module');
    }

    public function test_un_administrador_no_entra_al_catalogo_de_permisos(): void
    {
        $this->actingAs($this->admin)->get('/sistema/permisos')->assertForbidden();
    }

    /* ═══════════════ Los del sistema se protegen ═══════════════ */

    public function test_un_permiso_del_sistema_no_se_renombra_ni_se_borra(): void
    {
        $p = Permiso::where('name', 'ventas.anular')->firstOrFail();
        $this->assertTrue($p->is_system);

        $this->actingAs($this->angel)
            ->put("/sistema/permisos/{$p->id}", ['module' => 'ventas', 'action' => 'borrar', 'description' => 'Deshacer una venta cobrada'])
            ->assertSessionHasNoErrors();
        $this->assertSame('ventas.anular', $p->fresh()->name);
        $this->assertSame('Deshacer una venta cobrada', $p->fresh()->description);

        $this->actingAs($this->angel)->from('/sistema/permisos')
            ->delete("/sistema/permisos/{$p->id}")->assertSessionHasErrors('permiso');
        $this->assertNotNull($p->fresh());
    }

    /* ═══════════════ 2 y 3 · Asignar y quitar ═══════════════ */

    public function test_asignar_un_permiso_personalizado_a_un_rol_propio_y_borrarlo_se_lo_quita(): void
    {
        // Arrange: el permiso existe y el Administrador lo puede repartir
        $this->actingAs($this->angel)->post('/sistema/permisos', ['modulo_nuevo' => 'domicilios', 'action' => 'despachar']);

        // Act 2: el dueño arma un rol con él
        $this->actingAs($this->admin)
            ->post('/roles', ['name' => 'Domiciliario', 'permisos' => ['domicilios.despachar', 'ventas.ver']])
            ->assertSessionHasNoErrors();
        $rol = $this->trabajando(fn () => Rol::where('name', 'Domiciliario')->firstOrFail());
        $this->assertTrue($rol->hasPermissionTo('domicilios.despachar'));

        // Act 3: se borra del catálogo
        $id = Permiso::where('name', 'domicilios.despachar')->value('id');
        $this->actingAs($this->angel)->delete("/sistema/permisos/{$id}")->assertSessionHasNoErrors();

        // Assert: el rol lo perdió de inmediato
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse($rol->fresh()->permissions->contains('name', 'domicilios.despachar'));
        $this->assertDatabaseMissing('permissions', ['name' => 'domicilios.despachar']);
    }

    /* ═══════════════ 4 · Ver por rol ═══════════════ */

    public function test_se_ven_los_permisos_de_un_rol_agrupados_por_modulo_y_tipo(): void
    {
        $cajero = Rol::whereNull('empresa_id')->where('name', 'Cajero')->firstOrFail();

        $this->actingAs($this->angel)->get("/sistema/permisos?rol={$cajero->id}")
            ->assertOk()
            ->assertSee('Cajero')
            ->assertSee('caja.abrir')
            ->assertSee('Lectura')
            ->assertDontSee('usuarios.eliminar');
    }

    /* ═══════════════ H-F14 ═══════════════ */

    public function test_el_formulario_de_roles_ofrece_los_modulos_nuevos(): void
    {
        $this->actingAs($this->admin)->get('/roles/create')
            ->assertOk()
            ->assertSee('value="cupones.ver"', false)
            ->assertSee('value="tiendas.editar"', false)
            ->assertSee('value="citas.crear"', false)
            ->assertSee('value="notificaciones.enviar"', false)
            ->assertSee('value="reportes.exportar"', false)
            ->assertDontSee('value="sistema.superadmin"', false);
    }
}
