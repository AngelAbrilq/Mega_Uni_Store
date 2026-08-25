<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\Product;
use App\Models\Rol;
use App\Models\StockMovement;
use App\Models\Tienda;
use App\Models\User;
use App\Services\DemoService;
use App\Support\Contexto;
use App\Support\Rubros;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La prueba de 2 h 30 que se arma sola.
 *
 * ── Lo que estas pruebas cuidan ──
 *
 * Que el interesado entre y ENCUENTRE ALGO. Una demostración que abre
 * vacía no es una demostración: es un formulario en blanco, y el que lo ve
 * cierra la pestaña. Por eso aquí no basta con comprobar que la empresa se
 * creó — se cuenta que tenga productos, con existencia, con kardex, y del
 * rubro que pidió.
 *
 * Y que no se lleve por delante el aislamiento: la demostración es una
 * empresa más, y no puede ver ni una fila de las demás.
 */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);

        Contexto::olvidar();
    }

    /* ═══════════════ 1. Se arma completa ═══════════════ */

    public function test_la_pantalla_publica_abre_sin_sesion(): void
    {
        $this->get('/probar')
            ->assertOk()
            ->assertSee('Ferretería')
            ->assertSee('Peluquería o barbería');
    }

    public function test_crear_una_prueba_deja_al_interesado_adentro(): void
    {
        $respuesta = $this->post('/probar', $this->formulario());

        $respuesta->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $empresa = Empresa::where('slug', 'like', 'ferreteria-el-tornillo%')->first();

        $this->assertNotNull($empresa, 'No se creó la empresa de la prueba.');
        $this->assertTrue($empresa->es_demo);
        $this->assertSame('activa', $empresa->estado);
        $this->assertTrue($empresa->estaActiva());
    }

    /** 2 h 30, ni más ni menos. */
    public function test_la_prueba_dura_lo_que_dice(): void
    {
        $this->post('/probar', $this->formulario());

        $empresa = Empresa::where('es_demo', true)->firstOrFail();

        $this->assertEqualsWithDelta(
            DemoService::MINUTOS,
            $empresa->minutosRestantes(),
            2,
            'La prueba no quedó con las 2 h 30.'
        );
    }

    /**
     * La prueba que de verdad importa: que haya con qué vender.
     */
    public function test_la_prueba_abre_con_catalogo_de_su_rubro(): void
    {
        $this->post('/probar', $this->formulario());

        $empresa   = Empresa::where('es_demo', true)->firstOrFail();
        $tienda    = $empresa->tiendaPrincipal();
        $esperados = count(Rubros::uno('ferreteria')['productos']);

        Contexto::comoEmpresa($empresa->id, $tienda->id, function () use ($esperados) {
            $this->assertSame($esperados, Product::count(), 'El catálogo del rubro no se sembró completo.');

            $this->assertSame(
                $esperados,
                Existencia::count(),
                'Hay productos sin existencia: no se podrían vender.'
            );

            $this->assertGreaterThan(0, StockMovement::count(),
                'La existencia inicial se puso a mano, sin kardex: el inventario arrancaría sin explicación.');

            $this->assertGreaterThan(0, Category::count());
            $this->assertGreaterThan(0, Customer::count(), 'Sin cliente de mostrador no se puede cobrar rápido.');
        });
    }

    /** Cada producto sembrado tiene existencia de verdad, no un cero. */
    public function test_los_productos_traen_existencia_cargada(): void
    {
        $this->post('/probar', $this->formulario());

        $empresa = Empresa::where('es_demo', true)->firstOrFail();
        $tienda  = $empresa->tiendaPrincipal();

        Contexto::comoEmpresa($empresa->id, $tienda->id, function () {
            $conStock = Existencia::where('stock', '>', 0)->count();

            $this->assertGreaterThan(10, $conStock,
                'Casi ningún producto quedó con existencia: la demostración no permitiría vender.');
        });
    }

    /** El rubro decide los cargos. */
    public function test_la_peluqueria_arranca_con_su_estilista(): void
    {
        $this->post('/probar', $this->formulario([
            'negocio' => 'Peluquería Estilo',
            'email'   => 'estilo@ejemplo.com',
            'rubro'   => 'peluqueria',
        ]));

        $empresa = Empresa::where('es_demo', true)->firstOrFail();

        $suyos = Rol::where('empresa_id', $empresa->id)->pluck('name')->all();

        $this->assertContains('Estilista', $suyos);
        $this->assertNotContains('Bodeguero de patio', $suyos,
            'A la peluquería le sembraron los cargos de la ferretería.');
    }

    /** Y esos cargos no se le escapan a nadie más. */
    public function test_los_cargos_de_la_demo_no_los_ve_otro_negocio(): void
    {
        $this->post('/probar', $this->formulario([
            'negocio' => 'Peluquería Estilo',
            'email'   => 'estilo@ejemplo.com',
            'rubro'   => 'peluqueria',
        ]));

        $otra = Empresa::create([
            'nombre' => 'Ferretería ajena', 'slug' => 'ajena',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        $visibles = Contexto::comoEmpresa($otra->id, null, fn () => Rol::visibles()->pluck('name')->all());

        $this->assertNotContains('Estilista', $visibles);
    }

    /* ═══════════════ 2. El aislamiento se respeta ═══════════════ */

    public function test_la_demo_no_ve_los_datos_de_otro_negocio(): void
    {
        // Un negocio de verdad, con un producto suyo.
        $otra = Empresa::create([
            'nombre' => 'Supermercado La Esquina', 'slug' => 'super-demo',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        $tiendaOtra = Tienda::create([
            'empresa_id' => $otra->id, 'nombre' => 'Principal',
            'slug' => 'principal-otra', 'es_principal' => true, 'activa' => true,
        ]);

        Contexto::comoEmpresa($otra->id, $tiendaOtra->id, function () {
            Product::create(['name' => 'Producto ajeno', 'price' => 1000, 'cost' => 500]);
        });

        Contexto::olvidar();

        $this->post('/probar', $this->formulario());

        $demo = Empresa::where('es_demo', true)->firstOrFail();

        Contexto::comoEmpresa($demo->id, $demo->tiendaPrincipal()->id, function () {
            $this->assertSame(
                0,
                Product::where('name', 'Producto ajeno')->count(),
                'La demostración está viendo el catálogo de un cliente de verdad.'
            );
        });
    }

    /* ═══════════════ 3. Se acaba ═══════════════ */

    public function test_una_prueba_vencida_no_deja_entrar(): void
    {
        $this->post('/probar', $this->formulario());

        $empresa = Empresa::where('es_demo', true)->firstOrFail();
        $empresa->update(['expira_en' => now()->subMinute()]);

        $usuario = User::where('email', 'william@ejemplo.com')->firstOrFail();

        $this->actingAs($usuario)->get('/dashboard')->assertForbidden();
    }

    /* ═══════════════ 4. La limpieza ═══════════════ */

    public function test_el_comando_no_borra_nada_sin_que_se_lo_pidan(): void
    {
        $this->post('/probar', $this->formulario());

        Empresa::where('es_demo', true)->update(['expira_en' => now()->subDays(5)]);

        $this->artisan('mus:limpiar-demos')->assertSuccessful();

        $this->assertSame(1, Empresa::where('es_demo', true)->count(),
            'El comando borró sin que se lo pidieran con --hacerlo.');
    }

    public function test_con_hacerlo_si_borra_la_prueba_entera(): void
    {
        $this->post('/probar', $this->formulario());

        $empresa = Empresa::where('es_demo', true)->firstOrFail();
        $empresa->update(['expira_en' => now()->subDays(5)]);

        $this->artisan('mus:limpiar-demos --hacerlo')->assertSuccessful();

        $this->assertSame(0, Empresa::where('es_demo', true)->count());

        $this->assertSame(
            0,
            Product::sinFiltroEmpresa()->where('empresa_id', $empresa->id)->count(),
            'Se borró la empresa pero su catálogo se quedó ocupando la base.'
        );

        $this->assertSame(
            0,
            User::where('email', 'william@ejemplo.com')->count(),
            'Quedó una cuenta huérfana, sin ningún negocio al que entrar.'
        );
    }

    /** Una prueba que todavía no vence no se toca. */
    public function test_la_limpieza_no_toca_una_prueba_reciente(): void
    {
        $this->post('/probar', $this->formulario());

        $this->artisan('mus:limpiar-demos --hacerlo')->assertSuccessful();

        $this->assertSame(1, Empresa::where('es_demo', true)->count(),
            'Se borró una demostración que todavía estaba viva.');
    }

    /* ═══════════════ Apoyo ═══════════════ */

    /** @return array<string, string> */
    private function formulario(array $cambios = []): array
    {
        return array_merge([
            'negocio'               => 'Ferretería El Tornillo',
            'nombre'                => 'William Toles',
            'email'                 => 'william@ejemplo.com',
            'telefono'              => '3000000000',
            'rubro'                 => 'ferreteria',
            'password'              => 'ClaveLarga123',
            'password_confirmation' => 'ClaveLarga123',
        ], $cambios);
    }
}
