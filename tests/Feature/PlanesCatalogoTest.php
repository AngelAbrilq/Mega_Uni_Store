<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Tienda;
use App\Models\User;
use App\Services\MrrService;
use App\Support\Contexto;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SA-PLN-001 · Gestionar los planes de suscripción (RN-39).
 *   1 · Crear plan.
 *   2 · Editar precio: los suscritos conservan el que pagaban.
 *   3 · Desactivar: no se vende, quien lo tiene sigue igual.
 *   4 · 0 = sin límite.
 */
class PlanesCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private User $angel;
    private User $cliente;
    private Empresa $negocio;
    private Plan $pro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);

        $mia = $this->empresa('MEGA UNI STORE', 'mus');
        $this->negocio = $this->empresa('Papelería Lápiz', 'lapiz');

        $this->angel   = $this->persona('angel@ejemplo.com', $mia, 'Superadministrador');
        $this->cliente = $this->persona('dueno@ejemplo.com', $this->negocio, 'Administrador');
        $this->pro     = Plan::where('slug', 'pro')->firstOrFail();

        Contexto::olvidar();
    }

    /* ═══════════════ 1 · Crear ═══════════════ */

    public function test_el_superadministrador_crea_un_plan_con_sus_limites_y_funciones(): void
    {
        $this->actingAs($this->angel)
            ->post('/sistema/planes', [
                'nombre' => 'Starter', 'precio_mensual' => 29000,
                'max_tiendas' => 1, 'max_usuarios' => 2,
                'funciones' => ['pos', 'caja'], 'visible' => '1', 'is_active' => '1',
            ])
            ->assertRedirect('/sistema/planes')
            ->assertSessionHasNoErrors();

        $plan = Plan::where('slug', 'starter')->firstOrFail();
        $this->assertTrue($plan->is_active);
        $this->assertEquals(348_000, (float) $plan->precio_anual, 'Sin anual se toma 12 veces el mensual.');
        $this->assertSame(1, $plan->limite('tiendas'));
        $this->assertSame(['pos', 'caja'], $plan->funciones);
    }

    public function test_no_se_repite_el_identificador_ni_se_inventan_funciones(): void
    {
        $this->actingAs($this->angel)->from('/sistema/planes/create')
            ->post('/sistema/planes', ['nombre' => 'Pro', 'precio_mensual' => 1, 'funciones' => ['teletransporte']])
            ->assertSessionHasErrors(['slug', 'funciones.0']);
    }

    public function test_un_cliente_no_entra_al_catalogo_de_planes(): void
    {
        $this->actingAs($this->cliente)->get('/sistema/planes')->assertForbidden();
        $this->actingAs($this->cliente)->post('/sistema/planes', ['nombre' => 'Gratis', 'precio_mensual' => 0])->assertForbidden();
    }

    /* ═══════════════ 4 · Sin límite ═══════════════ */

    public function test_cero_o_vacio_en_un_limite_significa_sin_limite(): void
    {
        $this->actingAs($this->angel)
            ->post('/sistema/planes', [
                'nombre' => 'Enterprise', 'precio_mensual' => 499000,
                'max_tiendas' => '0', 'max_usuarios' => '0', 'max_productos' => '',
            ])->assertSessionHasNoErrors();

        $plan = Plan::where('slug', 'enterprise')->firstOrFail();
        $this->assertNull($plan->max_tiendas, 'El 0 se guarda como NULL: el resto del sistema entiende NULL como «sin tope».');
        $this->assertFalse($plan->limita('usuarios'));
        $this->assertSame('Sin límite', $plan->limiteTexto('productos'));
    }

    /* ═══════════════ 2 · Editar precio ═══════════════ */

    public function test_subir_el_precio_no_cambia_lo_que_paga_quien_ya_esta_suscrito(): void
    {
        // Arrange: el negocio paga el Pro a precio de lista
        app(MrrService::class)->cambiarPlan($this->negocio, $this->pro, 'mensual');
        $mrrAntes = app(MrrService::class)->mrrActual();

        // Act
        $this->actingAs($this->angel)
            ->put("/sistema/planes/{$this->pro->id}", $this->datosDe($this->pro, ['precio_mensual' => 99000]))
            ->assertSessionHasNoErrors();

        // Assert
        $this->assertEquals(99_000, (float) $this->pro->fresh()->precio_mensual);
        $this->assertEquals(79_000, (float) $this->negocio->fresh()->precio_pactado);
        $this->assertEqualsWithDelta($mrrAntes, app(MrrService::class)->mrrActual(), 0.01);
    }

    public function test_el_precio_pactado_de_antes_no_se_pisa(): void
    {
        app(MrrService::class)->cambiarPlan($this->negocio, $this->pro, 'mensual', 60_000);

        $this->actingAs($this->angel)
            ->put("/sistema/planes/{$this->pro->id}", $this->datosDe($this->pro, ['precio_mensual' => 99000]));

        $this->assertEquals(60_000, (float) $this->negocio->fresh()->precio_pactado);
    }

    /* ═══════════════ 3 · Desactivar ═══════════════ */

    public function test_desactivar_lo_saca_de_la_venta_sin_tocar_a_quien_lo_tiene(): void
    {
        app(MrrService::class)->cambiarPlan($this->negocio, $this->pro, 'mensual');
        $otro = $this->empresa('Ferretería', 'ferre');

        $this->actingAs($this->angel)->post("/sistema/planes/{$this->pro->id}/estado", ['activo' => '0']);

        $this->assertFalse($this->pro->fresh()->is_active);
        $this->assertSame($this->pro->id, $this->negocio->fresh()->plan_id);
        $this->assertFalse(Plan::vendibles()->whereKey($this->pro->id)->exists());

        // A otro negocio ya no se le puede asignar…
        $this->actingAs($this->angel)->from("/sistema/empresas/{$otro->id}")
            ->post("/sistema/empresas/{$otro->id}/suscribir", ['plan_id' => $this->pro->id, 'periodo' => 'mensual'])
            ->assertSessionHasErrors('plan_id');

        // …pero quien ya lo tiene puede seguir en él (p. ej. cambiar a anual).
        $this->actingAs($this->angel)->from("/sistema/empresas/{$this->negocio->id}")
            ->post("/sistema/empresas/{$this->negocio->id}/suscribir", ['plan_id' => $this->pro->id, 'periodo' => 'anual'])
            ->assertSessionHasNoErrors();
    }

    public function test_el_plan_de_la_demostracion_no_se_desactiva(): void
    {
        $demo = Plan::where('es_demo', true)->firstOrFail();

        $this->actingAs($this->angel)->from('/sistema/planes')
            ->post("/sistema/planes/{$demo->id}/estado", ['activo' => '0'])
            ->assertSessionHasErrors('plan');

        $this->assertTrue($demo->fresh()->is_active);
    }

    public function test_un_plan_con_negocios_no_se_borra_y_uno_sin_historia_si(): void
    {
        app(MrrService::class)->cambiarPlan($this->negocio, $this->pro, 'mensual');
        $nuevo = Plan::create(['nombre' => 'Temporal', 'slug' => 'temporal', 'precio_mensual' => 1000, 'precio_anual' => 12000]);

        $this->actingAs($this->angel)->from('/sistema/planes')
            ->delete("/sistema/planes/{$this->pro->id}")->assertSessionHasErrors('plan');
        $this->actingAs($this->angel)->delete("/sistema/planes/{$nuevo->id}")->assertSessionHasNoErrors();

        $this->assertNotNull($this->pro->fresh());
        $this->assertNull($nuevo->fresh());
    }

    public function test_el_catalogo_muestra_los_planes_y_su_estado(): void
    {
        $this->pro->forceFill(['is_active' => false])->save();

        $this->actingAs($this->angel)->get('/sistema/planes')
            ->assertOk()
            ->assertSee('Pro')
            ->assertSee('Inactivo')
            ->assertSee('Demostración');
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function datosDe(Plan $plan, array $cambios): array
    {
        return $cambios + [
            'nombre' => $plan->nombre, 'slug' => $plan->slug,
            'precio_mensual' => $plan->precio_mensual, 'precio_anual' => $plan->precio_anual,
            'funciones' => $plan->funciones, 'visible' => '1', 'is_active' => '1',
        ];
    }

    private function empresa(string $nombre, string $slug): Empresa
    {
        $empresa = Empresa::create(['nombre' => $nombre, 'slug' => $slug, 'rubro' => 'general', 'estado' => 'activa']);
        Tienda::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Principal',
            'slug' => 'principal-' . $slug, 'es_principal' => true, 'activa' => true,
        ]);

        return $empresa;
    }

    private function persona(string $correo, Empresa $empresa, string $rol): User
    {
        $usuario = User::factory()->create(['email' => $correo]);
        $usuario->empresas()->sync([$empresa->id => ['es_dueno' => true]]);
        Contexto::comoEmpresa($empresa->id, null, fn () => $usuario->syncRoles([$rol]));

        return $usuario->fresh();
    }
}
