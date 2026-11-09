<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Empresa;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\Tienda;
use App\Models\User;
use App\Services\SaleService;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * H-03 · Form Requests + H-F10 / H-F11 · Validación acotada al negocio.
 *
 * Negocio A (el del usuario) y negocio B (el vecino). Nada de B puede
 * colarse en un documento de A, y A puede repetir nombres que B ya usa.
 */
class ValidacionPorNegocioTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private Product $producto;

    /** @var array{cliente:int, proveedor:int, producto:int, categoria:int} */
    private array $delVecino;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('negocio-a');
        $this->admin    = $this->empleado('Administrador');
        $this->producto = $this->productoConExistencia('Lápiz', 1_000, 400, 50);

        $this->delVecino = $this->enElVecino(fn () => [
            'cliente'   => Customer::create(['first_name' => 'Cliente', 'last_name' => 'Ajeno'])->id,
            'proveedor' => Supplier::create(['name' => 'Proveedor ajeno', 'is_active' => true])->id,
            'producto'  => Product::create(['name' => 'Producto ajeno', 'price' => 1, 'cost' => 1, 'is_active' => true])->id,
            'categoria' => Category::create(['name' => 'Bebidas'])->id,
        ]);
    }

    /* ═══════════════ H-F10 · exists por negocio ═══════════════ */

    public function test_la_venta_no_acepta_un_cliente_del_vecino(): void
    {
        $this->actingAs($this->admin)
            ->from('/pos')
            ->post('/pos', $this->venta() + ['customer_id' => $this->delVecino['cliente']])
            ->assertSessionHasErrors('customer_id');

        $this->trabajando(fn () => $this->assertSame(0, Sale::count()));
    }

    public function test_la_venta_no_acepta_un_producto_del_vecino(): void
    {
        $venta = $this->venta();
        $venta['items'][0]['product_id'] = $this->delVecino['producto'];

        $this->actingAs($this->admin)
            ->from('/pos')
            ->post('/pos', $venta)
            ->assertSessionHasErrors('items.0.product_id');
    }

    public function test_la_compra_no_acepta_un_proveedor_del_vecino(): void
    {
        $this->actingAs($this->admin)
            ->from('/purchases/create')
            ->post('/purchases', [
                'supplier_id' => $this->delVecino['proveedor'],
                'items'       => [['product_id' => $this->producto->id, 'quantity' => 1, 'unit_cost' => 400]],
            ])
            ->assertSessionHasErrors('supplier_id');

        $this->trabajando(fn () => $this->assertSame(0, Purchase::count()));
    }

    public function test_la_devolucion_no_acepta_un_renglon_de_otra_venta(): void
    {
        [$una, $otra] = $this->trabajando(fn () => [$this->vender(), $this->vender()]);

        $this->actingAs($this->admin)
            ->from("/sales/{$una->id}/devolver")
            ->post("/sales/{$una->id}/devolver", [
                'motivo' => 'Renglón equivocado',
                'items'  => [['sale_item_id' => $otra->items->first()->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items.0.sale_item_id');

        $this->trabajando(fn () => $this->assertSame(0, SaleReturn::count()));
    }

    public function test_anular_una_venta_sin_motivo_se_rechaza(): void
    {
        $venta = $this->trabajando(fn () => $this->vender());

        $this->actingAs($this->admin)
            ->from("/sales/{$venta->id}")
            ->post("/sales/{$venta->id}/anular", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->trabajando(fn () => $this->assertSame(Sale::PAGADA, $venta->fresh()->status));
    }

    public function test_la_agenda_no_acepta_un_cliente_del_vecino(): void
    {
        $this->actingAs($this->admin)
            ->from('/agenda')
            ->post('/agenda', [
                'recurso_id'  => 1,
                'inicio'      => now()->addDay()->setTime(9, 0)->toDateTimeString(),
                'customer_id' => $this->delVecino['cliente'],
            ])
            ->assertSessionHasErrors('customer_id');
    }

    public function test_el_traslado_no_acepta_un_producto_del_vecino(): void
    {
        $this->actingAs($this->admin)
            ->from('/traslados/create')
            ->post('/traslados', [
                'product_id' => $this->delVecino['producto'],
                'desde'      => $this->tienda->id,
                'hacia'      => $this->tienda->id,
                'cantidad'   => 1,
            ])
            ->assertSessionHasErrors('product_id');
    }

    public function test_un_recurso_no_se_asigna_a_un_usuario_de_otro_negocio(): void
    {
        $ajeno = User::factory()->create(); // no pertenece al negocio A

        $this->actingAs($this->admin)
            ->from('/recursos/create')
            ->post('/recursos', [
                'nombre' => 'Silla 1', 'tipo' => array_key_first(\App\Models\Recurso::TIPOS),
                'color'  => '#336699', 'user_id' => $ajeno->id,
            ])
            ->assertSessionHasErrors('user_id');
    }

    /* ═══════════════ H-F11 · unique por negocio ═══════════════ */

    public function test_un_nombre_que_usa_el_vecino_si_se_puede_usar(): void
    {
        $this->actingAs($this->admin)
            ->post('/categories', ['name' => 'Bebidas'])
            ->assertSessionHasNoErrors();

        $this->trabajando(fn () => $this->assertSame(1, Category::where('name', 'Bebidas')->count()));
    }

    public function test_dentro_del_mismo_negocio_el_nombre_sigue_siendo_unico(): void
    {
        $this->trabajando(fn () => Category::create(['name' => 'Papelería']));

        $this->actingAs($this->admin)
            ->from('/categories/create')
            ->post('/categories', ['name' => 'Papelería'])
            ->assertSessionHasErrors('name');
    }

    /* ═══════════════ Ayudas ═══════════════ */

    /** @return array<string, mixed> */
    private function venta(): array
    {
        return [
            'items' => [['product_id' => $this->producto->id, 'quantity' => 1]],
            'pagos' => [['payment_method_id' => $this->efectivo->id, 'amount' => 1_000]],
        ];
    }

    private function vender(): Sale
    {
        return app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->producto->id, 'quantity' => 1]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 1_000]],
            userId: $this->admin->id,
        );
    }

    private function enElVecino(callable $fn): mixed
    {
        $empresa = Empresa::create(['nombre' => 'Vecino', 'slug' => 'negocio-b', 'rubro' => 'general', 'estado' => 'activa']);
        $tienda  = Tienda::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Principal B', 'slug' => 'principal-b',
            'es_principal' => true, 'activa' => true,
        ]);

        try {
            return Contexto::comoEmpresa($empresa->id, $tienda->id, $fn);
        } finally {
            Contexto::olvidar();
        }
    }
}
