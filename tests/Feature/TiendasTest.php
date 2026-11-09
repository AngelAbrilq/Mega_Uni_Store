<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Contexto;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Abrir la segunda sede.
 *
 * ── Qué se cuida aquí ──
 *
 * Tres cosas, y las tres son de las que no se ven hasta que duelen:
 *
 *  1. Que quien abre el local quede adentro. Un local al que su propio
 *     dueño no puede entrar se ve en el selector y no funciona, y nadie
 *     entiende por qué.
 *
 *  2. Que el aislamiento se respete AUNQUE la tienda no lo traiga puesto.
 *     `Tienda` no lleva filtro global —el panel de superadministrador
 *     necesita verlas todas—, así que si el controlador se olvida de
 *     comprobar la empresa, cambiar el número de la URL edita el local de
 *     otro cliente. Es el fallo más caro que podría tener esta pantalla.
 *
 *  3. Que el tope del plan corte de verdad. Un límite que solo esconde el
 *     botón no es un límite: es una sugerencia.
 */
class TiendasTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Tienda $principal;
    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->empresa   = $this->negocio('Peluquería Doña Marta', 'marta');
        $this->principal = $this->local($this->empresa, 'Principal', true);

        $this->dueno = User::factory()->create();
        $this->dueno->empresas()->sync([$this->empresa->id => ['es_dueno' => true]]);
        $this->dueno->tiendas()->sync([$this->principal->id]);

        Contexto::comoEmpresa($this->empresa->id, $this->principal->id, function () {
            $this->dueno->syncRoles(['Administrador']);
        });

        Contexto::olvidar();
    }

    /* ═══════════════ 1. Abrir ═══════════════ */

    public function test_abrir_un_local_lo_deja_dentro_del_negocio(): void
    {
        $this->actingAs($this->dueno)
            ->post('/tiendas', [
                'nombre' => 'Sede Norte',
                'codigo' => 'N',
                'ciudad' => 'Bogotá',
            ])
            ->assertRedirect('/tiendas');

        $nueva = Tienda::where('empresa_id', $this->empresa->id)->where('nombre', 'Sede Norte')->first();

        $this->assertNotNull($nueva, 'No se creó el local.');
        $this->assertSame('N', $nueva->codigo);
        $this->assertFalse($nueva->es_principal, 'Un local nuevo no puede nacer siendo la sede principal.');
    }

    public function test_quien_abre_el_local_queda_adentro(): void
    {
        $this->actingAs($this->dueno)->post('/tiendas', ['nombre' => 'Sede Sur']);

        $nueva = Tienda::where('nombre', 'Sede Sur')->firstOrFail();

        $this->assertTrue(
            $this->dueno->fresh()->tiendas->contains($nueva->id),
            'Se abrió un local al que el propio dueño no puede entrar.'
        );

        $this->assertTrue(
            $this->dueno->fresh()->tiendas->contains($this->principal->id),
            'Al enlazarlo al local nuevo se le quitaron los que ya tenía.'
        );
    }

    /* ═══════════════ 2. Qué se vende ahí ═══════════════ */

    public function test_copiar_el_catalogo_deja_los_productos_disponibles_en_cero(): void
    {
        $this->conProductos(3);

        $this->actingAs($this->dueno)->post('/tiendas', [
            'nombre'          => 'Sede Norte',
            'copiar_catalogo' => 1,
        ]);

        $nueva = Tienda::where('nombre', 'Sede Norte')->firstOrFail();

        $filas = Existencia::withoutGlobalScopes()->where('tienda_id', $nueva->id)->get();

        $this->assertCount(3, $filas, 'No quedó disponible todo el catálogo del negocio.');

        $this->assertEqualsWithDelta(
            0.0,
            (float) $filas->sum('stock'),
            0.001,
            'El local nuevo nació con mercancía que nadie movió. La existencia entra por traslado o por compra.'
        );
    }

    public function test_sin_copiar_el_catalogo_el_local_abre_en_blanco(): void
    {
        $this->conProductos(3);

        $this->actingAs($this->dueno)->post('/tiendas', [
            'nombre' => 'Sede Sur',
            // sin `copiar_catalogo`
        ]);

        $nueva = Tienda::where('nombre', 'Sede Sur')->firstOrFail();

        $this->assertSame(
            0,
            Existencia::withoutGlobalScopes()->where('tienda_id', $nueva->id)->count()
        );
    }

    /* ═══════════════ 3. El tope del plan ═══════════════ */

    /**
     * El tope del plan no es un 403, y está bien que no lo sea.
     *
     * Esta prueba empezó esperando `assertForbidden()` —yo la escribí así—
     * y falló contra el código, que devuelve una redirección con el
     * mensaje. Revisando `LimiteDelPlan::render()`, el que estaba
     * equivocado era yo: un 403 dice «no tienes permiso», y aquí el
     * usuario SÍ tiene permiso de abrir locales. Lo que no tiene es cupo,
     * que se arregla pagando y no pidiendo acceso. Por eso vuelve al
     * formulario con lo que había escrito y una explicación.
     *
     * Se deja anotado para que el próximo que lea esto no «corrija» el
     * controlador para que devuelva 403.
     */
    public function test_el_plan_basico_no_deja_abrir_el_segundo_local(): void
    {
        // El Básico incluye un solo local, y ya tiene el principal.
        $this->empresa->update(['plan_id' => Plan::where('slug', 'basico')->value('id')]);

        $this->actingAs($this->dueno)
            ->from('/tiendas/create')
            ->post('/tiendas', ['nombre' => 'Sede Norte'])
            ->assertRedirect('/tiendas/create')
            ->assertSessionHas('error', fn (string $aviso) => str_contains($aviso, 'Básico'));

        $this->assertSame(
            1,
            Tienda::where('empresa_id', $this->empresa->id)->count(),
            'El tope del plan escondió el botón pero no cortó la petición.'
        );
    }

    /**
     * Y por la otra puerta, la que un día va a usar la app de Flutter.
     *
     * 402 «Payment Required» es literalmente el caso, y es la única
     * respuesta del sistema que un cliente de API puede distinguir de un
     * «te falta permiso» sin leer el texto del mensaje.
     */
    public function test_el_tope_del_plan_tambien_corta_por_json(): void
    {
        $this->empresa->update(['plan_id' => Plan::where('slug', 'basico')->value('id')]);

        $this->actingAs($this->dueno)
            ->postJson('/tiendas', ['nombre' => 'Sede Norte'])
            ->assertStatus(402);

        $this->assertSame(1, Tienda::where('empresa_id', $this->empresa->id)->count());
    }

    public function test_el_plan_pro_si_deja(): void
    {
        $this->empresa->update(['plan_id' => Plan::where('slug', 'pro')->value('id')]);

        $this->actingAs($this->dueno)
            ->post('/tiendas', ['nombre' => 'Sede Norte'])
            ->assertRedirect('/tiendas');

        $this->assertSame(2, Tienda::where('empresa_id', $this->empresa->id)->count());
    }

    /* ═══════════════ 4. Aislamiento ═══════════════ */

    public function test_no_se_puede_abrir_la_ficha_del_local_de_otro_negocio(): void
    {
        $otra  = $this->negocio('Ferretería ajena', 'ajena');
        $ajeno = $this->local($otra, 'Principal ajena', true);

        $this->actingAs($this->dueno)->get('/tiendas/' . $ajeno->id)->assertNotFound();
        $this->actingAs($this->dueno)->get('/tiendas/' . $ajeno->id . '/edit')->assertNotFound();
    }

    public function test_no_se_puede_editar_el_local_de_otro_negocio(): void
    {
        $otra  = $this->negocio('Ferretería ajena', 'ajena2');
        $ajeno = $this->local($otra, 'Principal ajena', true);

        $this->actingAs($this->dueno)
            ->put('/tiendas/' . $ajeno->id, ['nombre' => 'Me lo robé'])
            ->assertNotFound();

        $this->assertSame('Principal ajena', $ajeno->fresh()->nombre);
    }

    public function test_el_listado_solo_muestra_los_locales_propios(): void
    {
        $otra = $this->negocio('Ferretería ajena', 'ajena3');
        $this->local($otra, 'Bodega del vecino', true);

        $this->actingAs($this->dueno)->get('/tiendas')
            ->assertOk()
            ->assertSee('Principal')
            ->assertDontSee('Bodega del vecino');
    }

    /* ═══════════════ 5. Cerrar, y cuándo no ═══════════════ */

    public function test_la_sede_principal_no_se_cierra(): void
    {
        $this->actingAs($this->dueno)
            ->from('/tiendas')
            ->delete('/tiendas/' . $this->principal->id)
            ->assertRedirect('/tiendas');

        $this->assertNotNull(Tienda::find($this->principal->id), 'Se cerró la sede principal.');
    }

    public function test_un_local_con_mercancia_no_se_cierra(): void
    {
        $this->conProductos(1);

        $this->actingAs($this->dueno)->post('/tiendas', [
            'nombre'          => 'Sede Norte',
            'copiar_catalogo' => 1,
        ]);

        $nueva = Tienda::where('nombre', 'Sede Norte')->firstOrFail();

        Existencia::withoutGlobalScopes()
            ->where('tienda_id', $nueva->id)
            ->update(['stock' => 5]);

        $this->actingAs($this->dueno)
            ->from('/tiendas')
            ->delete('/tiendas/' . $nueva->id);

        $this->assertNotNull(
            Tienda::find($nueva->id),
            'Se cerró un local que todavía tenía mercancía adentro.'
        );
    }

    public function test_un_local_vacio_si_se_cierra(): void
    {
        $this->actingAs($this->dueno)->post('/tiendas', ['nombre' => 'Sede Sur']);

        $nueva = Tienda::where('nombre', 'Sede Sur')->firstOrFail();

        $this->actingAs($this->dueno)
            ->delete('/tiendas/' . $nueva->id)
            ->assertRedirect('/tiendas');

        $this->assertNull(Tienda::find($nueva->id));
        $this->assertNotNull(Tienda::withTrashed()->find($nueva->id), 'Se borró de verdad en vez de cerrarse.');
    }

    /* ═══════════════ 6. Nombrar principal ═══════════════ */

    public function test_hacer_principal_a_otra_sede_le_quita_la_marca_a_la_anterior(): void
    {
        $this->actingAs($this->dueno)->post('/tiendas', ['nombre' => 'Sede Norte']);

        $nueva = Tienda::where('nombre', 'Sede Norte')->firstOrFail();

        $this->actingAs($this->dueno)
            ->from('/tiendas/' . $nueva->id)
            ->post('/tiendas/' . $nueva->id . '/principal');

        $this->assertTrue($nueva->fresh()->es_principal);
        $this->assertFalse(
            $this->principal->fresh()->es_principal,
            'Quedaron dos sedes principales: la identidad del negocio ya no sabe de dónde heredar.'
        );
    }

    /* ═══════════════ 7. Permisos ═══════════════ */

    public function test_un_vendedor_no_abre_locales(): void
    {
        $vendedor = User::factory()->create();
        $vendedor->empresas()->sync([$this->empresa->id => ['es_dueno' => false]]);
        $vendedor->tiendas()->sync([$this->principal->id]);

        Contexto::comoEmpresa($this->empresa->id, $this->principal->id, fn () => $vendedor->syncRoles(['Vendedor']));
        Contexto::olvidar();

        $this->actingAs($vendedor)->get('/tiendas/create')->assertForbidden();
        $this->actingAs($vendedor)->post('/tiendas', ['nombre' => 'Sede pirata'])->assertForbidden();
    }

    /* ═══════════════ Montaje ═══════════════ */

    private function negocio(string $nombre, string $slug): Empresa
    {
        return Empresa::create([
            'nombre' => $nombre, 'slug' => $slug,
            'rubro'  => 'general', 'estado' => 'activa',
        ]);
    }

    private function local(Empresa $empresa, string $nombre, bool $principal): Tienda
    {
        return Tienda::create([
            'empresa_id'   => $empresa->id,
            'nombre'       => $nombre,
            'slug'         => Str::slug($nombre) . '-' . $empresa->slug,
            'es_principal' => $principal,
            'activa'       => true,
        ]);
    }

    private function conProductos(int $cuantos): void
    {
        Contexto::comoEmpresa($this->empresa->id, $this->principal->id, function () use ($cuantos) {
            for ($i = 1; $i <= $cuantos; $i++) {
                Product::create(['name' => "Producto {$i}", 'price' => 10000, 'cost' => 6000]);
            }
        });

        Contexto::olvidar();
    }
}
