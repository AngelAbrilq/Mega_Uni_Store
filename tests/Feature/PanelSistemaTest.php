<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\MrrMovimiento;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Contexto;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel de superadministrador, y «entrar a mirar».
 *
 * ── Lo que se está probando de verdad ──
 *
 * Que la puerta de «entrar al negocio de un cliente» tenga DOS cerraduras y
 * no una. La variable de sesión sola no puede alcanzar: si alcanzara,
 * cualquier cliente que supiera escribir `mus.mirando_empresa` en su sesión
 * estaría dentro del negocio del vecino — y eso es exactamente el fallo que
 * todo el paso 4 existe para hacer imposible.
 *
 * La prueba `test_sin_el_permiso_la_variable_de_sesion_no_sirve_de_nada` es
 * la que decide si esta fase está bien hecha.
 */
class PanelSistemaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $mia;
    private Empresa $ajena;
    private User $angel;
    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->mia   = $this->negocio('MEGA UNI STORE', 'mus');
        $this->ajena = $this->negocio('Ferretería El Tornillo', 'tornillo');

        $this->angel   = $this->persona('angel@ejemplo.com', $this->mia, 'Superadministrador');
        $this->cliente = $this->persona('william@ejemplo.com', $this->ajena, 'Administrador');

        // Algo que solo existe en el negocio ajeno, para reconocerlo.
        Contexto::comoEmpresa($this->ajena->id, $this->ajena->tiendaPrincipal()->id, function () {
            Product::create(['name' => 'Martillo del Tornillo', 'price' => 28000, 'cost' => 18000]);
        });

        Contexto::olvidar();
    }

    /* ═══════════════ 1. Quién entra al panel ═══════════════ */

    public function test_el_superadministrador_ve_el_panel(): void
    {
        $this->actingAs($this->angel)->get('/sistema')
            ->assertOk()
            ->assertSee('Ferretería El Tornillo');
    }

    public function test_un_cliente_no_ve_el_panel(): void
    {
        $this->actingAs($this->cliente)->get('/sistema')->assertForbidden();
    }

    public function test_un_cliente_no_abre_la_ficha_de_otro_negocio(): void
    {
        $this->actingAs($this->cliente)
            ->get('/sistema/empresas/' . $this->mia->id)
            ->assertForbidden();
    }

    /* ═══════════════ 2. Entrar a mirar ═══════════════ */

    public function test_entrar_cambia_el_negocio_que_se_esta_mirando(): void
    {
        $this->actingAs($this->angel)
            ->post('/sistema/empresas/' . $this->ajena->id . '/entrar')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('mus.mirando_empresa', $this->ajena->id);

        // Y desde ahí, el catálogo que se ve es el del cliente.
        $this->actingAs($this->angel)->get('/products')
            ->assertOk()
            ->assertSee('Martillo del Tornillo');
    }

    /**
     * La prueba que decide si esta fase está bien hecha.
     *
     * Se le pone a mano la variable de sesión a un usuario que NO tiene el
     * permiso. Si con eso entrara, cualquier cliente podría hacerlo igual.
     */
    public function test_sin_el_permiso_la_variable_de_sesion_no_sirve_de_nada(): void
    {
        $this->actingAs($this->cliente)
            ->withSession(['mus.mirando_empresa' => $this->mia->id])
            ->get('/products')
            ->assertOk()
            ->assertDontSee('Producto de MEGA');

        // Y sigue parado en el suyo, no en el que pidió.
        $this->actingAs($this->cliente)
            ->withSession(['mus.mirando_empresa' => $this->mia->id])
            ->get('/products')
            ->assertSee('Martillo del Tornillo');
    }

    public function test_salir_devuelve_a_lo_propio(): void
    {
        $this->actingAs($this->angel)
            ->withSession(['mus.mirando_empresa' => $this->ajena->id])
            ->post('/sistema/salir')
            ->assertRedirect('/sistema')
            ->assertSessionMissing('mus.mirando_empresa');
    }

    /**
     * Entrar a una cuenta suspendida tiene que funcionar.
     *
     * Es justamente cuando hace falta: el cliente llama porque no puede
     * entrar, y hay que ver qué pasó adentro.
     */
    public function test_se_puede_entrar_a_una_cuenta_suspendida(): void
    {
        $this->ajena->update(['estado' => 'suspendida']);

        $this->actingAs($this->angel)
            ->withSession(['mus.mirando_empresa' => $this->ajena->id])
            ->get('/dashboard')
            ->assertOk();
    }

    /* ═══════════════ 3. Acciones comerciales ═══════════════ */

    public function test_suscribir_deja_su_linea_en_el_historial(): void
    {
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $this->actingAs($this->angel)
            ->from('/sistema/empresas/' . $this->ajena->id)
            ->post('/sistema/empresas/' . $this->ajena->id . '/suscribir', [
                'plan_id' => $pro->id,
                'periodo' => 'mensual',
                'motivo'  => 'Cerró la venta',
            ])
            ->assertRedirect('/sistema/empresas/' . $this->ajena->id);

        $this->assertSame($pro->id, $this->ajena->fresh()->plan_id);

        $mov = MrrMovimiento::where('empresa_id', $this->ajena->id)->latest('id')->first();

        $this->assertNotNull($mov, 'Se cambió el plan sin anotarlo: la bitácora del ingreso queda con un hueco.');
        $this->assertSame('nuevo', $mov->tipo);
        $this->assertEqualsWithDelta(79_000, (float) $mov->delta, 0.01);
    }

    public function test_suspender_no_le_borra_los_datos_al_cliente(): void
    {
        $basico = Plan::where('slug', 'basico')->firstOrFail();
        $this->ajena->update(['plan_id' => $basico->id]);

        $this->actingAs($this->angel)
            ->from('/sistema/empresas/' . $this->ajena->id)
            ->post('/sistema/empresas/' . $this->ajena->id . '/suspender', ['motivo' => 'No pagó']);

        $this->assertSame('suspendida', $this->ajena->fresh()->estado);

        $this->assertSame(
            1,
            Product::sinFiltroEmpresa()->where('empresa_id', $this->ajena->id)->count(),
            'Suspender le borró el catálogo al cliente.'
        );
    }

    public function test_reactivar_se_anota_como_reactivacion(): void
    {
        $this->ajena->update(['estado' => 'suspendida']);
        $basico = Plan::where('slug', 'basico')->firstOrFail();

        $this->actingAs($this->angel)
            ->from('/sistema/empresas/' . $this->ajena->id)
            ->post('/sistema/empresas/' . $this->ajena->id . '/reactivar', [
                'plan_id' => $basico->id,
                'periodo' => 'mensual',
            ]);

        $this->assertSame('activa', $this->ajena->fresh()->estado);

        $mov = MrrMovimiento::where('empresa_id', $this->ajena->id)->latest('id')->firstOrFail();

        $this->assertSame('reactivacion', $mov->tipo);
    }

    /** Extender una prueba que ya venció cuenta desde ahora, no desde ayer. */
    public function test_extender_una_prueba_vencida_la_revive(): void
    {
        $this->ajena->update([
            'es_demo'   => true,
            'expira_en' => now()->subDays(2),
        ]);

        $this->actingAs($this->angel)
            ->from('/sistema/empresas/' . $this->ajena->id)
            ->post('/sistema/empresas/' . $this->ajena->id . '/extender', ['minutos' => 60]);

        $fresca = $this->ajena->fresh();

        $this->assertFalse($fresca->haVencido(), 'La extensión se le sumó a una fecha pasada: sigue vencida.');
        $this->assertEqualsWithDelta(60, $fresca->minutosRestantes(), 2);
    }

    public function test_un_cliente_no_puede_cambiarse_el_plan_a_si_mismo(): void
    {
        $empresa = Plan::where('slug', 'empresa')->firstOrFail();

        $this->actingAs($this->cliente)
            ->post('/sistema/empresas/' . $this->ajena->id . '/suscribir', [
                'plan_id' => $empresa->id,
                'periodo' => 'mensual',
            ])
            ->assertForbidden();

        $this->assertNull($this->ajena->fresh()->plan_id);
    }

    /* ═══════════════ Montaje ═══════════════ */

    private function negocio(string $nombre, string $slug): Empresa
    {
        $empresa = Empresa::create([
            'nombre' => $nombre, 'slug' => $slug,
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

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
