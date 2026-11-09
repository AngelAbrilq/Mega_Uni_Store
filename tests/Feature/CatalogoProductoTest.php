<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Empresa;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * AT-PRI-001 · Varios impuestos por producto.
 * TBL-TPL-001 · Producto–proveedor con precio, código y días de entrega.
 * TBL-PPA-001 · Atributos por producto (y TBL-ATR-001 deja de ser huérfano).
 */
class CatalogoProductoTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private Tax $iva;
    private Tax $bolsa;
    private Supplier $mayorista;
    private Supplier $distribuidor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('catalogo');
        $this->admin = $this->empleado('Administrador');

        [$this->iva, $this->bolsa, $this->mayorista, $this->distribuidor] = $this->trabajando(fn () => [
            Tax::create(['name' => 'IVA 19 %', 'rate' => 19, 'type' => 'percentage', 'is_active' => true]),
            Tax::create(['name' => 'Bolsa', 'rate' => 50, 'type' => 'fixed', 'is_active' => true]),
            Supplier::create(['name' => 'Mayorista Sur', 'is_active' => true]),
            Supplier::create(['name' => 'Distribuidora Norte', 'is_active' => true]),
        ]);
    }

    /* ═══════════════ AT-PRI-001 ═══════════════ */

    public function test_la_venta_suma_el_impuesto_principal_y_los_adicionales(): void
    {
        // Arrange: 10.000 con IVA 19 % + bolsa $50 por unidad
        $producto = $this->productoConExistencia('Jabón', 10_000, 6_000, 20, $this->iva->id);
        $this->trabajando(fn () => $producto->extraTaxes()->sync([$this->bolsa->id]));

        // Act: 2 unidades
        $venta = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $producto->id, 'quantity' => 2]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 30_000]],
            userId: $this->admin->id,
        ));

        // Assert: 20.000 × 19 % = 3.800 + 2 × 50 = 100 → 3.900
        $item = $venta->items->first();
        $this->assertEqualsWithDelta(3_900, (float) $item->tax_amount, 0.01);
        $this->assertEqualsWithDelta(19, (float) $item->tax_rate, 0.01);
        $this->assertSame('IVA 19 % + Bolsa', $item->tax_name);
        $this->assertEqualsWithDelta(23_900, (float) $venta->total, 0.01);
    }

    public function test_el_formulario_guarda_los_adicionales_sin_repetir_el_principal(): void
    {
        $this->actingAs($this->admin)
            ->post('/products', $this->producto([
                'tax_id' => $this->iva->id, 'extra_taxes' => [$this->iva->id, $this->bolsa->id],
            ]))
            ->assertSessionHasNoErrors();

        $producto = $this->trabajando(fn () => Product::where('name', 'Cuaderno')->firstOrFail());
        $this->assertSame([$this->bolsa->id], $this->trabajando(fn () => $producto->extraTaxes()->pluck('taxes.id')->all()));
    }

    /* ═══════════════ TBL-TPL-001 ═══════════════ */

    public function test_guarda_varios_proveedores_y_el_preferido_queda_como_proveedor_del_producto(): void
    {
        $this->actingAs($this->admin)
            ->post('/products', $this->producto([
                'proveedores' => [
                    ['supplier_id' => $this->mayorista->id, 'supplier_price' => 1_800, 'lead_time_days' => 3, 'supplier_sku' => 'MS-77'],
                    ['supplier_id' => $this->distribuidor->id, 'supplier_price' => 1_650, 'lead_time_days' => 8],
                    ['supplier_id' => ''],
                ],
                'proveedor_preferido' => $this->distribuidor->id,
            ]))
            ->assertSessionHasNoErrors();

        $producto = $this->trabajando(fn () => Product::with('suppliers')->where('name', 'Cuaderno')->firstOrFail());

        $this->assertSame($this->distribuidor->id, $producto->supplier_id);
        $this->assertCount(2, $producto->suppliers);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $producto->id, 'supplier_id' => $this->mayorista->id,
            'supplier_price' => 1_800, 'lead_time_days' => 3, 'supplier_sku' => 'MS-77', 'is_preferred' => false,
        ]);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $producto->id, 'supplier_id' => $this->distribuidor->id, 'is_preferred' => true,
        ]);

        $this->actingAs($this->admin)->get("/products/{$producto->id}")
            ->assertOk()->assertSee('Distribuidora Norte')->assertSee('Entrega en 8 días');
    }

    public function test_con_un_solo_proveedor_ese_es_el_preferido(): void
    {
        $this->actingAs($this->admin)
            ->post('/products', $this->producto([
                'proveedores' => [['supplier_id' => $this->mayorista->id, 'supplier_price' => 1_800]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_supplier', ['supplier_id' => $this->mayorista->id, 'is_preferred' => true]);
    }

    public function test_el_preferido_debe_estar_entre_los_proveedores_elegidos(): void
    {
        $this->actingAs($this->admin)
            ->from('/products/create')
            ->post('/products', $this->producto([
                'proveedores' => [['supplier_id' => $this->mayorista->id]],
                'proveedor_preferido' => $this->distribuidor->id,
            ]))
            ->assertSessionHasErrors('proveedor_preferido');
    }

    public function test_rechaza_un_proveedor_de_otro_negocio(): void
    {
        $vecino = Empresa::create(['nombre' => 'Vecino', 'slug' => 'vecino-cat', 'rubro' => 'general', 'estado' => 'activa']);
        $ajeno  = Supplier::sinFiltroEmpresa()->create(['empresa_id' => $vecino->id, 'name' => 'Ajeno', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->from('/products/create')
            ->post('/products', $this->producto(['proveedores' => [['supplier_id' => $ajeno->id]]]))
            ->assertSessionHasErrors('proveedores.0.supplier_id');
    }

    public function test_editar_sin_la_seccion_de_proveedores_no_borra_los_existentes(): void
    {
        // Arrange
        $this->actingAs($this->admin)->post('/products', $this->producto([
            'proveedores' => [['supplier_id' => $this->mayorista->id, 'supplier_price' => 1_800]],
        ]));
        $producto = $this->trabajando(fn () => Product::where('name', 'Cuaderno')->firstOrFail());

        // Act: una llamada que no trae el formulario completo (sin `proveedores_presentes`)
        $datos = $this->producto(['name' => 'Cuaderno rayado']);
        unset($datos['proveedores_presentes']);
        $this->actingAs($this->admin)->put("/products/{$producto->id}", $datos)->assertSessionHasNoErrors();

        // Assert
        $this->assertDatabaseHas('product_supplier', ['product_id' => $producto->id, 'supplier_id' => $this->mayorista->id]);
    }

    public function test_la_compra_sugiere_el_precio_pactado_con_el_proveedor(): void
    {
        $this->actingAs($this->admin)->post('/products', $this->producto([
            'proveedores' => [['supplier_id' => $this->mayorista->id, 'supplier_price' => 1_750]],
        ]));

        $this->actingAs($this->admin)->get('/purchases/create')
            ->assertOk()
            ->assertSee('data-precios=\'{"' . $this->mayorista->id . '":1750}\'', false);
    }

    /* ═══════════════ TBL-PPA-001 ═══════════════ */

    public function test_guarda_atributos_y_valida_su_tipo(): void
    {
        [$marca, $peso] = $this->trabajando(fn () => [
            Attribute::create(['name' => 'Marca', 'type' => 'texto', 'is_active' => true]),
            Attribute::create(['name' => 'Peso (g)', 'type' => 'numero', 'is_active' => true]),
        ]);

        $this->actingAs($this->admin)
            ->from('/products/create')
            ->post('/products', $this->producto(['atributos' => [$marca->id => 'Colgate', $peso->id => 'mucho']]))
            ->assertSessionHasErrors("atributos.{$peso->id}");

        $this->actingAs($this->admin)
            ->post('/products', $this->producto(['atributos' => [$marca->id => 'Colgate', $peso->id => '500']]))
            ->assertSessionHasNoErrors();

        $producto = $this->trabajando(fn () => Product::where('name', 'Cuaderno')->firstOrFail());
        $this->assertDatabaseHas('attribute_product', ['product_id' => $producto->id, 'attribute_id' => $marca->id, 'value' => 'Colgate']);

        // Vaciar un atributo lo quita del producto.
        $this->actingAs($this->admin)
            ->put("/products/{$producto->id}", $this->producto(['atributos' => [$marca->id => '', $peso->id => '500']]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('attribute_product', ['product_id' => $producto->id, 'attribute_id' => $marca->id]);

        $this->actingAs($this->admin)->get("/products/{$producto->id}/edit")
            ->assertOk()->assertSee('Atributos')->assertSee('Impuestos adicionales')->assertSee('Proveedores');
    }

    /* ═══════════════ Ayudas ═══════════════ */

    /** @param array<string, mixed> $cambios */
    private function producto(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Cuaderno', 'cost' => 1_500, 'price' => 3_000, 'stock' => 10, 'min_stock' => 2,
            'is_active' => '1', 'proveedores_presentes' => '1',
        ], $cambios);
    }
}
