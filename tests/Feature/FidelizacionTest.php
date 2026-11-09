<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerTienda;
use App\Models\LoyaltyTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Tienda;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * CL-PUN-001 · Acumular puntos por local (1 punto por cada $1.000).
 * CL-TDC-001 · Asociar el cliente a un local, sin duplicados.
 * RN-38      · Puntos solo con cliente identificado; se devuelven al anular
 *              y en proporción a una devolución aprobada.
 */
class FidelizacionTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private User $cajero;
    private Product $morral;
    private Customer $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('fidelidad');
        $this->admin  = $this->empleado('Administrador');
        $this->cajero = $this->empleado('Cajero');
        $this->morral = $this->productoConExistencia('Morral', 50_000, 30_000, 40);
        $this->ana    = $this->trabajando(fn () => Customer::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']));
    }

    /* ═══════════════ CL-PUN-001 · Acumular ═══════════════ */

    public function test_una_compra_de_50000_suma_50_puntos_en_el_local(): void
    {
        // Act
        $venta = $this->vender(1, $this->ana->id);

        // Assert
        $cuenta = $this->cuenta();
        $this->assertSame(50, $cuenta->points_balance);
        $this->assertSame(1, $cuenta->purchases_count);
        $this->assertEquals(50_000, (float) $cuenta->total_spent);
        $this->assertDatabaseHas('loyalty_transactions', [
            'sale_id' => $venta->id, 'type' => LoyaltyTransaction::GANADOS, 'points' => 50, 'balance_after' => 50,
        ]);
    }

    public function test_la_fraccion_de_mil_no_suma_punto(): void
    {
        $this->trabajando(fn () => Setting::guardar(['venta.pesos_por_punto' => '3000'], 'venta'));

        $this->vender(1, $this->ana->id); // 50.000 / 3.000 = 16,6 → 16

        $this->assertSame(16, $this->cuenta()->points_balance);
    }

    public function test_una_venta_sin_cliente_no_acumula_puntos(): void
    {
        $this->vender(2, null);

        $this->assertDatabaseCount('customer_tienda', 0);
        $this->assertDatabaseCount('loyalty_transactions', 0);
    }

    public function test_con_los_puntos_apagados_suma_compras_y_gastado_pero_no_puntos(): void
    {
        $this->trabajando(fn () => Setting::guardar(['venta.puntos_activos' => '0'], 'venta'));

        $this->vender(1, $this->ana->id);

        $cuenta = $this->cuenta();
        $this->assertSame(0, $cuenta->points_balance);
        $this->assertSame(1, $cuenta->purchases_count);
        $this->assertDatabaseCount('loyalty_transactions', 0);
    }

    public function test_cada_local_lleva_su_propia_cuenta(): void
    {
        // Arrange: un segundo local del mismo negocio
        $norte = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Norte', 'slug' => 'norte-fidelidad', 'activa' => true,
        ]);
        $this->vender(1, $this->ana->id);

        // Act: compra en Norte
        $this->cajero->tiendas()->attach($norte->id);
        Contexto::comoEmpresa($this->empresa->id, $norte->id, function () use ($norte) {
            \App\Models\Existencia::create([
                'tienda_id' => $norte->id, 'product_id' => $this->morral->id,
                'stock' => 10, 'min_stock' => 0, 'precio' => 50_000, 'activo' => true,
            ]);
        });
        $this->vender(2, $this->ana->id, $norte->id);

        // Assert
        $this->assertSame(50, $this->cuenta()->points_balance);
        $this->assertSame(100, $this->cuenta($norte->id)->points_balance);
        $this->assertSame(2, CustomerTienda::withoutGlobalScopes()->where('customer_id', $this->ana->id)->count());
    }

    /* ═══════════════ RN-38 · Reversas ═══════════════ */

    public function test_anular_la_venta_resta_sus_puntos_y_la_compra(): void
    {
        $venta = $this->vender(1, $this->ana->id);

        $this->trabajando(fn () => app(SaleService::class)->anular($venta, $this->admin->id, 'Error de digitación'));

        $cuenta = $this->cuenta();
        $this->assertSame(0, $cuenta->points_balance);
        $this->assertSame(0, $cuenta->purchases_count);
        $this->assertEquals(0, (float) $cuenta->total_spent);
        $this->assertDatabaseHas('loyalty_transactions', ['sale_id' => $venta->id, 'type' => LoyaltyTransaction::REVERSA, 'points' => -50]);
    }

    public function test_una_devolucion_resta_la_parte_proporcional_de_los_puntos(): void
    {
        // Arrange: 4 morrales = $200.000 = 200 puntos
        $venta = $this->vender(4, $this->ana->id);

        // Act: devuelve 1 de 4
        $this->trabajando(fn () => app(ReturnService::class)->registrar(
            $venta,
            [['sale_item_id' => $venta->items->first()->id, 'quantity' => 1]],
            $this->admin->id,
            'No le gustó el color',
        ));

        // Assert
        $this->assertSame(150, $this->cuenta()->points_balance);

        // Y al devolver los 3 restantes el saldo vuelve a cero, nunca negativo
        $this->trabajando(fn () => app(ReturnService::class)->registrar(
            $venta->fresh(),
            [['sale_item_id' => $venta->items->first()->id, 'quantity' => 3]],
            $this->admin->id,
            'Devuelve el resto',
        ));
        $this->assertSame(0, $this->cuenta()->points_balance);
        $this->assertEquals(0, (float) $this->cuenta()->total_spent);
    }

    /* ═══════════════ CL-TDC-001 · Asociar ═══════════════ */

    public function test_asociar_desde_la_ficha_no_duplica_y_empieza_en_cero(): void
    {
        $this->actingAs($this->admin)
            ->post("/customers/{$this->ana->id}/locales", ['tienda_id' => $this->tienda->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post("/customers/{$this->ana->id}/locales", ['tienda_id' => $this->tienda->id]);

        $this->assertSame(1, CustomerTienda::withoutGlobalScopes()->where('customer_id', $this->ana->id)->count());
        $this->assertSame(0, $this->cuenta()->points_balance);
    }

    public function test_desasociar_conserva_los_puntos_y_reactivar_los_devuelve(): void
    {
        $this->vender(1, $this->ana->id);

        $this->actingAs($this->admin)->delete("/customers/{$this->ana->id}/locales/{$this->tienda->id}");
        $this->assertFalse($this->cuenta()->is_active);
        $this->assertSame(50, $this->cuenta()->points_balance);

        $this->actingAs($this->admin)->post("/customers/{$this->ana->id}/locales", ['tienda_id' => $this->tienda->id]);
        $this->assertTrue($this->cuenta()->is_active);
    }

    public function test_no_se_asocia_a_un_local_ajeno_ni_sin_permiso(): void
    {
        $otro = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Bodega', 'slug' => 'bodega-fid', 'activa' => true,
        ]);
        $this->admin->tiendas()->sync([$this->tienda->id]); // solo trabaja en Principal

        $this->actingAs($this->admin)->from("/customers/{$this->ana->id}")
            ->post("/customers/{$this->ana->id}/locales", ['tienda_id' => $otro->id])
            ->assertSessionHasErrors('tienda_id');

        $this->actingAs($this->cajero)
            ->post("/customers/{$this->ana->id}/locales", ['tienda_id' => $this->tienda->id])
            ->assertForbidden();
    }

    public function test_la_ficha_muestra_los_puntos_por_local_y_los_movimientos(): void
    {
        $venta = $this->vender(1, $this->ana->id);

        $this->actingAs($this->admin)->get("/customers/{$this->ana->id}")
            ->assertOk()
            ->assertSee('Fidelidad por local')
            ->assertSee('Principal')
            ->assertSee('Compra ' . $venta->number);
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function vender(int $cantidad, ?int $clienteId, ?int $tiendaId = null): Sale
    {
        return Contexto::comoEmpresa($this->empresa->id, $tiendaId ?? $this->tienda->id, fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->morral->id, 'quantity' => $cantidad]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 50_000 * $cantidad]],
            userId: $this->cajero->id,
            customerId: $clienteId,
        ));
    }

    private function cuenta(?int $tiendaId = null): CustomerTienda
    {
        return CustomerTienda::withoutGlobalScopes()
            ->where(['customer_id' => $this->ana->id, 'tienda_id' => $tiendaId ?? $this->tienda->id])
            ->firstOrFail();
    }
}
