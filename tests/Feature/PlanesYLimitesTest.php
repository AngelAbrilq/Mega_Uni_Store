<?php

namespace Tests\Feature;

use App\Exceptions\LimiteDelPlan;
use App\Models\Category;
use App\Models\Empresa;
use App\Models\MrrMovimiento;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tienda;
use App\Models\Unit;
use App\Services\LimiteService;
use App\Services\MrrService;
use App\Support\Contexto;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Planes, límites y bitácora del ingreso.
 *
 * ── Las dos cosas que no se pueden romper ──
 *
 * 1. Que nadie se quede sin poder trabajar por un cambio de facturación.
 *    Una empresa sin plan no tiene topes; una que se baja de plan conserva
 *    todo lo que ya tenía. Cobrar mal es un problema; dejar a una
 *    ferretería sin poder vender es otro mucho peor.
 *
 * 2. Que el MRR que sale de la bitácora sea el mismo que sale de sumar los
 *    clientes. Si se separan, el histórico miente — y el histórico es el
 *    que se usa para decidir.
 */
class PlanesYLimitesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $negocio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->negocio = Empresa::create([
            'nombre' => 'Ferretería El Tornillo',
            'slug'   => 'ferreteria-planes',
            'rubro'  => 'general',
            'estado' => 'activa',
        ]);

        Tienda::create([
            'empresa_id'   => $this->negocio->id,
            'nombre'       => 'Principal',
            'slug'         => 'principal-planes',
            'es_principal' => true,
            'activa'       => true,
        ]);

        Contexto::olvidar();
    }

    /* ═══════════════ 1. Sin plan, sin topes ═══════════════ */

    public function test_una_empresa_sin_plan_no_tiene_ningun_tope(): void
    {
        foreach (Plan::RECURSOS as $recurso) {
            $this->assertNull(
                $this->negocio->limite($recurso),
                "Una empresa sin plan quedó con tope de {$recurso}. Eso la deja sin poder trabajar."
            );

            $this->assertTrue($this->negocio->puedeAgregar($recurso));
        }
    }

    public function test_sin_plan_el_servicio_deja_pasar(): void
    {
        $limites = app(LimiteService::class);

        Contexto::comoEmpresa($this->negocio->id, null, function () use ($limites) {
            $limites->exigir('productos');   // no debe lanzar nada
            $this->assertTrue(true);
        });
    }

    /* ═══════════════ 2. Con plan, el tope se respeta ═══════════════ */

    public function test_el_plan_basico_corta_al_llegar_a_su_tope(): void
    {
        $basico = Plan::where('slug', 'basico')->firstOrFail();

        // Un tope pequeño para no crear quinientos productos en una prueba.
        $basico->update(['max_productos' => 2]);

        $this->negocio->update(['plan_id' => $basico->id]);

        Contexto::comoEmpresa($this->negocio->id, $this->negocio->tiendaPrincipal()?->id, function () {
            $limites = app(LimiteService::class);

            $this->crearProducto('Martillo');
            $this->crearProducto('Destornillador');

            $this->assertSame(2, $this->negocio->fresh()->usado('productos'));

            $this->expectException(LimiteDelPlan::class);

            $limites->exigir('productos');
        });
    }

    /**
     * Bajar de plan no borra nada.
     *
     * Es la regla que protege al cliente: si se baja y le sobran productos,
     * los conserva y los sigue vendiendo. Lo único que no puede es crear
     * uno más.
     */
    public function test_bajar_de_plan_no_le_quita_lo_que_ya_tenia(): void
    {
        $empresa = Plan::where('slug', 'empresa')->firstOrFail();
        $basico  = Plan::where('slug', 'basico')->firstOrFail();
        $basico->update(['max_productos' => 1]);

        $this->negocio->update(['plan_id' => $empresa->id]);

        Contexto::comoEmpresa($this->negocio->id, $this->negocio->tiendaPrincipal()?->id, function () use ($basico) {
            $this->crearProducto('Martillo');
            $this->crearProducto('Destornillador');
            $this->crearProducto('Alicate');

            $this->negocio->update(['plan_id' => $basico->id]);
            $fresca = $this->negocio->fresh();

            $this->assertSame(3, $fresca->usado('productos'), 'Bajar de plan le borró productos al cliente.');
            $this->assertFalse($fresca->puedeAgregar('productos'), 'Debería poder conservar los suyos pero no crear más.');
            $this->assertSame(0, $fresca->disponible('productos'));
        });
    }

    /* ═══════════════ 3. Lo que paga ═══════════════ */

    public function test_el_mrr_del_plan_mensual_es_la_cuota(): void
    {
        $basico = Plan::where('slug', 'basico')->firstOrFail();

        $this->negocio->update(['plan_id' => $basico->id, 'periodo' => 'mensual']);

        $this->assertEqualsWithDelta(29_000, $this->negocio->fresh()->mrr(), 0.01);
    }

    /** El anual se reparte en doce, o el MRR sube y baja con la fecha de cobro. */
    public function test_el_anual_se_reparte_entre_doce(): void
    {
        $basico = Plan::where('slug', 'basico')->firstOrFail();

        $this->negocio->update(['plan_id' => $basico->id, 'periodo' => 'anual']);

        $this->assertEqualsWithDelta(278_400 / 12, $this->negocio->fresh()->mrr(), 0.01);
    }

    /** Un descuento pactado no se pierde porque suba la lista de precios. */
    public function test_el_precio_pactado_manda_sobre_el_del_plan(): void
    {
        $basico = Plan::where('slug', 'basico')->firstOrFail();

        $this->negocio->update([
            'plan_id'        => $basico->id,
            'periodo'        => 'mensual',
            'precio_pactado' => 20_000,
        ]);

        $this->assertEqualsWithDelta(20_000, $this->negocio->fresh()->mrr(), 0.01);

        // Y aunque el plan suba, el cliente sigue en lo suyo.
        $basico->update(['precio_mensual' => 40_000]);

        $this->assertEqualsWithDelta(20_000, $this->negocio->fresh()->mrr(), 0.01);
    }

    public function test_las_sedes_de_mas_se_suman_al_mrr(): void
    {
        $basico = Plan::where('slug', 'basico')->firstOrFail();   // 1 local, extra $12.000

        $this->negocio->update([
            'plan_id'             => $basico->id,
            'tiendas_contratadas' => 3,
        ]);

        // 29.000 del plan + 2 sedes de más × 12.000
        $this->assertEqualsWithDelta(29_000 + 24_000, $this->negocio->fresh()->mrr(), 0.01);
    }

    /* ═══════════════ 4. La bitácora del ingreso ═══════════════ */

    public function test_suscribir_anota_un_cliente_nuevo(): void
    {
        $basico = Plan::where('slug', 'basico')->firstOrFail();

        $mov = app(MrrService::class)->cambiarPlan($this->negocio, $basico, 'mensual', null, 'Primera venta');

        $this->assertSame('nuevo', $mov->tipo);
        $this->assertEqualsWithDelta(0, (float) $mov->mrr_antes, 0.01);
        $this->assertEqualsWithDelta(29_000, (float) $mov->mrr_despues, 0.01);
        $this->assertEqualsWithDelta(29_000, (float) $mov->delta, 0.01);
    }

    public function test_subir_de_plan_es_expansion_y_bajar_es_contraccion(): void
    {
        $mrr    = app(MrrService::class);
        $basico = Plan::where('slug', 'basico')->firstOrFail();
        $pro    = Plan::where('slug', 'pro')->firstOrFail();

        $mrr->cambiarPlan($this->negocio, $basico);

        $sube = $mrr->cambiarPlan($this->negocio->fresh(), $pro);
        $this->assertSame('expansion', $sube->tipo);
        $this->assertEqualsWithDelta(50_000, (float) $sube->delta, 0.01);

        $baja = $mrr->cambiarPlan($this->negocio->fresh(), $basico);
        $this->assertSame('contraccion', $baja->tipo);
        $this->assertEqualsWithDelta(-50_000, (float) $baja->delta, 0.01);
    }

    public function test_dar_de_baja_suspende_pero_no_borra_nada(): void
    {
        $mrr    = app(MrrService::class);
        $basico = Plan::where('slug', 'basico')->firstOrFail();

        $mrr->cambiarPlan($this->negocio, $basico);

        Contexto::comoEmpresa($this->negocio->id, $this->negocio->tiendaPrincipal()?->id, function () {
            $this->crearProducto('Martillo');
        });

        $mov = $mrr->darDeBaja($this->negocio->fresh(), 'Cerró el local');

        $this->assertSame('baja', $mov->tipo);
        $this->assertEqualsWithDelta(-29_000, (float) $mov->delta, 0.01);

        $fresca = $this->negocio->fresh();
        $this->assertSame('suspendida', $fresca->estado);
        $this->assertSame(1, $fresca->usado('productos'), 'Dar de baja no puede borrarle los datos al cliente.');
    }

    /**
     * Volver cuenta aparte de entrar.
     *
     * Traer de vuelta a alguien que ya te conoce cuesta muy distinto que
     * conseguir a uno nuevo; mezclarlos arruina el CAC de los dos.
     */
    public function test_reactivar_se_anota_distinto_de_un_cliente_nuevo(): void
    {
        $mrr    = app(MrrService::class);
        $basico = Plan::where('slug', 'basico')->firstOrFail();

        $mrr->cambiarPlan($this->negocio, $basico);
        $mrr->darDeBaja($this->negocio->fresh());

        $mov = $mrr->reactivar($this->negocio->fresh(), $basico, 'mensual', null, 'Volvió en marzo');

        $this->assertSame('reactivacion', $mov->tipo);
        $this->assertSame('activa', $this->negocio->fresh()->estado);
    }

    /**
     * La prueba que amarra todo: el resumen del mes tiene que explicar
     * exactamente la diferencia entre el MRR de antes y el de ahora.
     */
    public function test_el_resumen_del_mes_explica_el_cambio_del_ingreso(): void
    {
        $mrr    = app(MrrService::class);
        $basico = Plan::where('slug', 'basico')->firstOrFail();
        $pro    = Plan::where('slug', 'pro')->firstOrFail();

        $otra = Empresa::create([
            'nombre' => 'Supermercado La Esquina',
            'slug'   => 'super-planes',
            'rubro'  => 'general',
            'estado' => 'activa',
        ]);

        $mrr->cambiarPlan($this->negocio, $basico);   // +29.000  nuevo
        $mrr->cambiarPlan($otra, $pro);               // +79.000  nuevo
        $mrr->cambiarPlan($this->negocio->fresh(), $pro);  // +50.000 expansión
        $mrr->darDeBaja($otra->fresh());              // −79.000  baja

        $resumen = $mrr->resumenDelMes();

        $this->assertEqualsWithDelta(108_000, $resumen['nuevo'], 0.01);
        $this->assertEqualsWithDelta(50_000, $resumen['expansion'], 0.01);
        $this->assertEqualsWithDelta(-79_000, $resumen['baja'], 0.01);
        $this->assertEqualsWithDelta(79_000, $resumen['neto'], 0.01);

        // Y el neto tiene que ser el MRR de hoy, porque se empezó de cero.
        $this->assertEqualsWithDelta($resumen['neto'], $mrr->mrrActual(), 0.01);
    }

    /** Las demos no cuentan como ingreso. */
    public function test_una_demo_no_suma_al_mrr(): void
    {
        $demo = Plan::where('slug', 'demo')->firstOrFail();

        $this->negocio->update(['plan_id' => $demo->id, 'es_demo' => true]);

        $this->assertEqualsWithDelta(0, app(MrrService::class)->mrrActual(), 0.01);
    }

    /* ═══════════════ Apoyo ═══════════════ */

    private function crearProducto(string $nombre): Product
    {
        return Product::create([
            'name'        => $nombre,
            'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'unit_id'     => Unit::firstOrCreate(['name' => 'Unidad'], ['symbol' => 'und'])->id,
            'price'       => 10_000,
            'cost'        => 6_000,
        ]);
    }
}
