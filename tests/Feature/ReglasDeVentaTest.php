<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Tienda;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\Contexto;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Reglas de venta que antes existían solo en pantalla (Análisis de Brecha, Fase 1).
 *
 * H-F01 · El POS cobraba cualquier `unit_price` que mandara el navegador.
 * H-F02 · Seis parámetros de Configuración se guardaban y nadie los leía.
 *
 * Estructura AAA: Arrange (setUp + ajustes) · Act (vender/comprar) · Assert.
 */
class ReglasDeVentaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Tienda $tienda;
    private User $cajero;
    private User $admin; // tiene ventas.crear y ventas.cambiar_precio
    private Product $producto;
    private PaymentMethod $efectivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->empresa = Empresa::create([
            'nombre' => 'Reglas de venta', 'slug' => 'reglas-venta',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        $this->tienda = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Principal',
            'slug' => 'principal-reglas', 'es_principal' => true, 'activa' => true,
        ]);

        $this->cajero     = $this->empleado('Cajero');
        $this->admin = $this->empleado('Administrador');

        $this->trabajando(function () {
            $this->efectivo = PaymentMethod::create(['name' => 'Efectivo', 'is_active' => true]);

            $this->producto = Product::create([
                'name' => 'Cuaderno', 'price' => 10_000, 'cost' => 6_000, 'is_active' => true,
            ]);

            // El local vende a 12.000: el precio de referencia es el del local, no el del catálogo.
            Existencia::create([
                'tienda_id' => $this->tienda->id, 'product_id' => $this->producto->id,
                'stock' => 100, 'min_stock' => 0, 'precio' => 12_000, 'activo' => true,
            ]);
        });
    }

    /* ═══════════════ H-F01 · Precio ═══════════════ */

    public function test_sin_permiso_no_se_puede_cobrar_a_otro_precio(): void
    {
        $this->actingAs($this->cajero)
            ->from('/pos')
            ->post('/pos', $this->ventaWeb(unitPrice: 1))
            ->assertSessionHasErrors('items');

        $this->trabajando(fn () => $this->assertSame(0, Sale::count(), 'Se registró una venta a precio manipulado.'));
    }

    public function test_sin_unit_price_se_cobra_el_precio_del_local(): void
    {
        $this->trabajando(function () {
            $venta = $this->vender([['product_id' => $this->producto->id, 'quantity' => 1]]);

            $this->assertEqualsWithDelta(12_000, (float) $venta->items()->first()->unit_price, 0.01,
                'Se cobró el precio del catálogo y no el del local que muestra el POS.');
        });
    }

    public function test_con_permiso_el_cambio_de_precio_queda_auditado(): void
    {
        $this->actingAs($this->admin)
            ->post('/pos', $this->ventaWeb(unitPrice: 11_000))
            ->assertSessionHasNoErrors();

        $this->trabajando(function () {
            $this->assertSame(1, Sale::count());
            $this->assertTrue(
                AuditLog::where('event', 'precio_modificado')->exists(),
                'El cambio de precio no dejó rastro en la auditoría.'
            );
        });
    }

    /* ═══════════════ H-F02 · Configuración ═══════════════ */

    public function test_el_descuento_no_puede_superar_el_tope(): void
    {
        $this->trabajando(function () {
            Setting::guardar(['venta.descuento_max' => '10']);

            $this->expectException(ValidationException::class);

            // 12.000 × 1 con 2.400 de descuento = 20 % > 10 %.
            $this->vender([['product_id' => $this->producto->id, 'quantity' => 1, 'discount' => 2_400]]);
        });
    }

    public function test_el_descuento_dentro_del_tope_pasa(): void
    {
        $this->trabajando(function () {
            Setting::guardar(['venta.descuento_max' => '10']);

            $venta = $this->vender([['product_id' => $this->producto->id, 'quantity' => 1, 'discount' => 1_200]]);

            $this->assertEqualsWithDelta(1_200, (float) $venta->discount_total, 0.01);
        });
    }

    public function test_stock_negativo_solo_si_la_configuracion_lo_permite(): void
    {
        $this->trabajando(function () {
            // Apagado (por defecto): 150 > 100 en existencia → rechazo.
            try {
                $this->vender([['product_id' => $this->producto->id, 'quantity' => 150]], pago: 2_000_000);
                $this->fail('Vendió más de lo que había con stock negativo apagado.');
            } catch (ValidationException) {
                // esperado
            }

            Setting::guardar(['venta.stock_negativo' => '1']);

            $this->vender([['product_id' => $this->producto->id, 'quantity' => 150]], pago: 2_000_000);

            $this->assertEqualsWithDelta(-50, (float) $this->producto->fresh()->stock, 0.001);
        });
    }

    public function test_cliente_obligatorio_bloquea_la_venta_sin_cliente(): void
    {
        $this->trabajando(fn () => Setting::guardar(['venta.cliente_obligatorio' => '1']));

        $this->actingAs($this->cajero)
            ->from('/pos')
            ->post('/pos', $this->ventaWeb())
            ->assertSessionHasErrors('customer_id');
    }

    public function test_cliente_obligatorio_deja_pasar_con_cliente(): void
    {
        $clienteId = 0;

        $this->trabajando(function () use (&$clienteId) {
            Setting::guardar(['venta.cliente_obligatorio' => '1']);
            $clienteId = Customer::create(['first_name' => 'Ana', 'last_name' => 'Ruiz'])->id;
        });

        $this->actingAs($this->cajero)
            ->post('/pos', $this->ventaWeb() + ['customer_id' => $clienteId])
            ->assertSessionHasNoErrors();
    }

    public function test_metodo_de_costeo_ultimo_usa_el_costo_de_la_compra(): void
    {
        $this->trabajando(function () {
            Setting::guardar(['inventario.costo_metodo' => 'ultimo']);

            $this->comprar(cantidad: 10, costo: 9_000);

            $this->assertEqualsWithDelta(9_000, (float) $this->producto->fresh()->cost, 0.01);
        });
    }

    public function test_metodo_de_costeo_promedio_pondera(): void
    {
        $this->trabajando(function () {
            $this->comprar(cantidad: 10, costo: 9_000);

            // (100 × 6.000 + 10 × 9.000) / 110 = 6.272,73
            $this->assertEqualsWithDelta(6_272.73, (float) $this->producto->fresh()->cost, 0.5);
        });
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function empleado(string $rol): User
    {
        $u = User::factory()->create();
        $u->empresas()->sync([$this->empresa->id => ['es_dueno' => false]]);
        $u->tiendas()->sync([$this->tienda->id]);
        $this->trabajando(fn () => $u->syncRoles([$rol]));

        return $u;
    }

    /** @return array<string, mixed> */
    private function ventaWeb(?float $unitPrice = null): array
    {
        $linea = ['product_id' => $this->producto->id, 'quantity' => 1];

        if ($unitPrice !== null) {
            $linea['unit_price'] = $unitPrice;
        }

        return [
            'items' => [$linea],
            'pagos' => [['payment_method_id' => $this->efectivo->id, 'amount' => 20_000]],
        ];
    }

    /** @param array<int, array<string, mixed>> $lineas */
    private function vender(array $lineas, float $pago = 20_000): Sale
    {
        return app(SaleService::class)->registrar(
            lineas: $lineas,
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => $pago]],
            userId: $this->cajero->id,
        );
    }

    private function comprar(float $cantidad, float $costo): void
    {
        $proveedor = Supplier::create(['name' => 'Papelería Sur', 'is_active' => true]);

        $servicio = app(PurchaseService::class);
        $compra   = $servicio->registrar($proveedor->id, [[
            'product_id' => $this->producto->id, 'quantity' => $cantidad, 'unit_cost' => $costo,
        ]], $this->admin->id);

        $servicio->recibir($compra, $this->admin->id);
    }

    private function trabajando(callable $fn): void
    {
        Contexto::comoEmpresa($this->empresa->id, $this->tienda->id, $fn);

        Contexto::olvidar();
    }
}
