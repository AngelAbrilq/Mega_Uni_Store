<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tienda;
use App\Models\Traslado;
use App\Models\User;
use App\Services\StockService;
use App\Support\Contexto;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mover mercancía de un local a otro.
 *
 * ── Lo que estas pruebas cuidan ──
 *
 * Que el inventario TOTAL del negocio no cambie. Un traslado no crea ni
 * destruye mercancía: la cambia de sitio. Si al final del traslado el
 * producto tiene una unidad más o una menos en total, hay un error de
 * inventario — y los errores de inventario no se notan el día que pasan,
 * se notan meses después cuando alguien cuenta.
 *
 * Y que no se pueda mover lo que no hay: sin esa comprobación, el local de
 * origen queda en negativo y el sistema empieza a vender existencias
 * imaginarias.
 */
class TrasladosTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Tienda $centro;
    private Tienda $norte;
    private Product $producto;
    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->empresa = Empresa::create([
            'nombre' => 'Ferretería El Tornillo', 'slug' => 'tornillo-tras',
            'rubro'  => 'ferreteria', 'estado' => 'activa',
        ]);

        $this->centro = $this->local('Centro', true);
        $this->norte  = $this->local('Norte', false);

        $this->usuario = User::factory()->create();
        $this->usuario->empresas()->sync([$this->empresa->id => ['es_dueno' => true]]);

        Contexto::comoEmpresa($this->empresa->id, $this->centro->id, function () {
            $this->usuario->syncRoles(['Administrador']);

            $this->producto = Product::create([
                'name' => 'Martillo de uña 16 oz', 'price' => 28_000, 'cost' => 18_000,
            ]);

            $this->existencia($this->centro, 20);
            $this->existencia($this->norte, 5);
        });

        Contexto::olvidar();
    }

    /* ═══════════════ 1. La mercancía cambia de sitio ═══════════════ */

    public function test_trasladar_mueve_las_unidades_y_no_cambia_el_total(): void
    {
        $this->trabajando(function () {
            $antes = $this->totalDelProducto();

            app(StockService::class)->trasladar(
                $this->producto, 8, $this->centro->id, $this->norte->id, $this->usuario->id
            );

            $this->assertEqualsWithDelta(12, $this->stockEn($this->centro), 0.001);
            $this->assertEqualsWithDelta(13, $this->stockEn($this->norte), 0.001);

            $this->assertEqualsWithDelta(
                $antes,
                $this->totalDelProducto(),
                0.001,
                'El traslado creó o destruyó mercancía. Un traslado solo la cambia de sitio.'
            );
        });
    }

    public function test_el_traslado_queda_como_documento_con_numero(): void
    {
        $this->trabajando(function () {
            $traslado = app(StockService::class)->trasladar(
                $this->producto, 3, $this->centro->id, $this->norte->id, $this->usuario->id, 'Va con Jorge'
            );

            $this->assertNotEmpty($traslado->numero);
            $this->assertStringStartsWith('T-', $traslado->numero);
            $this->assertSame('Va con Jorge', $traslado->notas);
            $this->assertEqualsWithDelta(18_000, (float) $traslado->costo_unitario, 0.01);
            $this->assertEqualsWithDelta(54_000, $traslado->valor(), 0.01);
        });
    }

    /**
     * Las dos mitades quedan amarradas.
     *
     * Es la razón de que exista la tabla: sin el documento, la salida y la
     * entrada son dos líneas sueltas del kardex y nadie puede probar que
     * eran el mismo movimiento.
     */
    public function test_los_dos_movimientos_apuntan_al_traslado(): void
    {
        $this->trabajando(function () {
            $traslado = app(StockService::class)->trasladar(
                $this->producto, 4, $this->centro->id, $this->norte->id, $this->usuario->id
            );

            $movimientos = $traslado->movimientos()->get();

            $this->assertCount(2, $movimientos, 'Un traslado tiene que dejar exactamente dos líneas de kardex.');

            $salida  = $movimientos->firstWhere('reason', 'traslado_salida');
            $entrada = $movimientos->firstWhere('reason', 'traslado_entrada');

            $this->assertNotNull($salida);
            $this->assertNotNull($entrada);

            $this->assertSame($this->centro->id, $salida->tienda_id);
            $this->assertSame($this->norte->id, $entrada->tienda_id);

            $this->assertEqualsWithDelta(-4, (float) $salida->quantity, 0.001);
            $this->assertEqualsWithDelta(4, (float) $entrada->quantity, 0.001);
        });
    }

    /* ═══════════════ 2. Lo que no se puede ═══════════════ */

    public function test_no_se_puede_trasladar_al_mismo_local(): void
    {
        $this->trabajando(function () {
            $this->expectException(\InvalidArgumentException::class);

            app(StockService::class)->trasladar(
                $this->producto, 2, $this->centro->id, $this->centro->id, $this->usuario->id
            );
        });
    }

    /** Y por la puerta web, con el mensaje que dice cuánto hay de verdad. */
    public function test_no_se_puede_mover_mas_de_lo_que_hay(): void
    {
        $this->actingAs($this->usuario)
            ->from('/traslados/nuevo')
            ->post('/traslados', [
                'product_id' => $this->producto->id,
                'desde'      => $this->centro->id,
                'hacia'      => $this->norte->id,
                'cantidad'   => 50,
            ])
            ->assertSessionHasErrors('cantidad');

        $this->trabajando(function () {
            $this->assertEqualsWithDelta(20, $this->stockEn($this->centro), 0.001,
                'Se rechazó el traslado pero el inventario quedó tocado.');

            $this->assertSame(0, Traslado::count(), 'Quedó un documento de un traslado que no ocurrió.');
        });
    }

    /**
     * Un local de otra empresa no está ni en la lista.
     *
     * Es el aislamiento del paso 4 aplicado al caso más peligroso: mandar
     * mercancía al negocio de otro cliente.
     */
    public function test_no_se_puede_trasladar_al_local_de_otro_negocio(): void
    {
        $otra = Empresa::create([
            'nombre' => 'Supermercado ajeno', 'slug' => 'ajeno-tras',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        $ajena = Tienda::create([
            'empresa_id' => $otra->id, 'nombre' => 'Principal',
            'slug' => 'principal-ajeno', 'es_principal' => true, 'activa' => true,
        ]);

        $this->actingAs($this->usuario)
            ->from('/traslados/nuevo')
            ->post('/traslados', [
                'product_id' => $this->producto->id,
                'desde'      => $this->centro->id,
                'hacia'      => $ajena->id,
                'cantidad'   => 2,
            ])
            ->assertSessionHasErrors('hacia');
    }

    /* ═══════════════ 3. Por la puerta web ═══════════════ */

    public function test_registrar_un_traslado_desde_la_pantalla(): void
    {
        $this->actingAs($this->usuario)
            ->post('/traslados', [
                'product_id' => $this->producto->id,
                'desde'      => $this->centro->id,
                'hacia'      => $this->norte->id,
                'cantidad'   => 6,
                'notas'      => 'Reposición del norte',
            ])
            ->assertRedirect('/traslados');

        $this->trabajando(function () {
            $this->assertSame(1, Traslado::count());
            $this->assertEqualsWithDelta(14, $this->stockEn($this->centro), 0.001);
            $this->assertEqualsWithDelta(11, $this->stockEn($this->norte), 0.001);
        });
    }

    public function test_el_listado_muestra_el_traslado(): void
    {
        $this->trabajando(fn () => app(StockService::class)->trasladar(
            $this->producto, 2, $this->centro->id, $this->norte->id, $this->usuario->id
        ));

        $this->actingAs($this->usuario)->get('/traslados')
            ->assertOk()
            ->assertSee('Martillo de uña 16 oz', false)
            ->assertSee('Centro')
            ->assertSee('Norte');
    }

    /** El traslado de otra empresa no aparece en el listado de esta. */
    public function test_el_listado_no_muestra_traslados_ajenos(): void
    {
        $this->trabajando(fn () => app(StockService::class)->trasladar(
            $this->producto, 2, $this->centro->id, $this->norte->id, $this->usuario->id
        ));

        $otra = Empresa::create([
            'nombre' => 'Ajena', 'slug' => 'ajena-listado',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        Tienda::create([
            'empresa_id' => $otra->id, 'nombre' => 'Principal',
            'slug' => 'principal-ajena-l', 'es_principal' => true, 'activa' => true,
        ]);

        $vistos = Contexto::comoEmpresa($otra->id, null, fn () => Traslado::count());

        $this->assertSame(0, $vistos, 'Un negocio está viendo los traslados de otro.');
    }

    /* ═══════════════ Apoyo ═══════════════ */

    private function local(string $nombre, bool $principal): Tienda
    {
        return Tienda::create([
            'empresa_id'   => $this->empresa->id,
            'nombre'       => $nombre,
            'slug'         => \Illuminate\Support\Str::slug($nombre) . '-tras',
            'es_principal' => $principal,
            'activa'       => true,
        ]);
    }

    private function existencia(Tienda $tienda, float $stock): void
    {
        Existencia::create([
            'tienda_id'  => $tienda->id,
            'product_id' => $this->producto->id,
            'stock'      => $stock,
            'min_stock'  => 2,
            'activo'     => true,
        ]);

        // El total del producto se recalcula a partir de las existencias.
        $this->producto->refresh();
    }

    private function stockEn(Tienda $tienda): float
    {
        return (float) Existencia::deTienda($tienda->id)
            ->where('product_id', $this->producto->id)
            ->value('stock');
    }

    /** Todo lo que hay de ese producto, sumando los locales. */
    private function totalDelProducto(): float
    {
        return (float) Existencia::query()
            ->where('product_id', $this->producto->id)
            ->sum('stock');
    }

    private function trabajando(callable $fn): void
    {
        Contexto::comoEmpresa($this->empresa->id, $this->centro->id, $fn);

        Contexto::olvidar();
    }
}
