<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * AT-CUP-001 · Crear cupones.
 * VD-CUP-001 · Aplicarlos en el POS.
 * TBL-VCU-001 · Registrar qué cupón se usó y cuánto descontó.
 * RN-35      · Validación en el servidor, reparto antes del IVA, usos y liberación al anular.
 */
class CuponesTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private User $cajero;
    private Product $cuaderno;
    private Product $lapiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('cupones');
        $this->admin  = $this->empleado('Administrador');
        $this->cajero = $this->empleado('Cajero');

        $iva = $this->trabajando(fn () => Tax::create(['name' => 'IVA', 'rate' => 19, 'type' => 'percentage', 'is_active' => true]));
        $this->cuaderno = $this->productoConExistencia('Cuaderno', 30_000, 15_000, 50, $iva->id);
        $this->lapiz    = $this->productoConExistencia('Lápiz', 10_000, 4_000, 50, $iva->id);
    }

    /* ═══════════════ AT-CUP-001 · Gestión ═══════════════ */

    public function test_el_administrador_crea_un_cupon_y_el_codigo_queda_en_mayusculas(): void
    {
        $this->actingAs($this->admin)
            ->post('/coupons', ['code' => ' bienvenida10 ', 'type' => 'percentage', 'value' => 10, 'max_uses' => 100, 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['empresa_id' => $this->empresa->id, 'code' => 'BIENVENIDA10', 'used_count' => 0]);
    }

    public function test_no_se_repite_el_codigo_ni_pasa_un_porcentaje_de_100(): void
    {
        $this->cupon(['code' => 'DOBLE']);

        $this->actingAs($this->admin)->from('/coupons/create')
            ->post('/coupons', ['code' => 'doble', 'type' => 'percentage', 'value' => 150])
            ->assertSessionHasErrors(['code', 'value']);
    }

    public function test_el_cajero_no_administra_cupones(): void
    {
        $this->actingAs($this->cajero)->get('/coupons/create')->assertForbidden();
    }

    /* ═══════════════ RN-35 · Cálculo ═══════════════ */

    public function test_el_porcentaje_se_reparte_antes_del_iva_y_queda_registrado(): void
    {
        // Arrange: 30.000 + 10.000 = 40.000 de base · cupón 10 % = 4.000
        $this->cupon(['code' => 'BIENVENIDA10', 'value' => 10]);

        // Act
        $venta = $this->vender('BIENVENIDA10');

        // Assert: base 36.000 · IVA 19 % = 6.840 · total 42.840
        $this->assertEqualsWithDelta(4_000, (float) $venta->discount_total, 0.01);
        $this->assertEqualsWithDelta(36_000, (float) $venta->subtotal, 0.01);
        $this->assertEqualsWithDelta(6_840, (float) $venta->tax_total, 0.01);
        $this->assertEqualsWithDelta(42_840, (float) $venta->total, 0.01);
        $this->assertEqualsWithDelta(3_000, (float) $venta->items->firstWhere('product_id', $this->cuaderno->id)->discount, 0.01);
        $this->assertDatabaseHas('sale_coupons', ['sale_id' => $venta->id, 'code' => 'BIENVENIDA10', 'discount_amount' => 4_000]);
        $this->assertSame(1, $this->trabajando(fn () => Coupon::where('code', 'BIENVENIDA10')->value('used_count')));
    }

    public function test_el_valor_fijo_y_el_tope_del_porcentaje(): void
    {
        $this->cupon(['code' => 'FIJO5000', 'type' => 'fixed', 'value' => 5_000]);
        $this->cupon(['code' => 'MITAD', 'value' => 50, 'max_discount' => 8_000]);

        $this->assertEqualsWithDelta(5_000, (float) $this->vender('FIJO5000')->discount_total, 0.01);
        $this->assertEqualsWithDelta(8_000, (float) $this->vender('mitad')->discount_total, 0.01, 'El 50 % de 40.000 se queda en el tope.');
    }

    /* ═══════════════ RN-35 · Rechazos ═══════════════ */

    public function test_sin_la_compra_minima_no_se_vende_ni_se_gasta_el_uso(): void
    {
        $this->cupon(['code' => 'GRANDE', 'value' => 10, 'min_purchase' => 100_000]);
        $ventasAntes = Sale::sinFiltroEmpresa()->count();

        $this->assertCupónRechazado('GRANDE', 'compra mínima');

        $this->assertSame($ventasAntes, Sale::sinFiltroEmpresa()->count());
        $this->assertSame(0, $this->trabajando(fn () => Coupon::where('code', 'GRANDE')->value('used_count')));
    }

    public function test_un_cupon_vencido_agotado_o_inactivo_se_rechaza(): void
    {
        $this->cupon(['code' => 'VIEJO', 'ends_at' => now()->subDay()]);
        $this->cupon(['code' => 'UNAVEZ', 'max_uses' => 1, 'used_count' => 1]);
        $this->cupon(['code' => 'APAGADO', 'is_active' => false]);

        $this->assertCupónRechazado('VIEJO', 'venció');
        $this->assertCupónRechazado('UNAVEZ', 'ya se usó');
        $this->assertCupónRechazado('APAGADO', 'no está activo');
        $this->assertCupónRechazado('NOEXISTE', 'no existe');
    }

    public function test_anular_la_venta_devuelve_el_uso_del_cupon(): void
    {
        $this->cupon(['code' => 'UNAVEZ', 'max_uses' => 1]);
        $venta = $this->vender('UNAVEZ');

        $this->trabajando(fn () => app(SaleService::class)->anular($venta, $this->admin->id, 'Error de digitación'));

        $this->assertSame(0, $this->trabajando(fn () => Coupon::where('code', 'UNAVEZ')->value('used_count')));
    }

    /* ═══════════════ VD-CUP-001 · POS ═══════════════ */

    public function test_la_vista_previa_del_pos_calcula_sin_gastar_usos(): void
    {
        $this->cupon(['code' => 'BIENVENIDA10', 'value' => 10]);

        $this->actingAs($this->cajero)->getJson('/pos/cupon?codigo=bienvenida10&base=40000')
            ->assertOk()->assertJson(['ok' => true, 'codigo' => 'BIENVENIDA10', 'descuento' => 4000]);
        $this->actingAs($this->cajero)->getJson('/pos/cupon?codigo=NADA&base=40000')
            ->assertStatus(422)->assertJson(['ok' => false]);

        $this->assertSame(0, $this->trabajando(fn () => Coupon::where('code', 'BIENVENIDA10')->value('used_count')));
    }

    public function test_el_pos_cobra_con_el_cupon_y_la_venta_lo_muestra(): void
    {
        $this->cupon(['code' => 'FIJO5000', 'type' => 'fixed', 'value' => 5_000]);

        $this->actingAs($this->cajero)->post('/pos', [
            'items' => [['product_id' => $this->lapiz->id, 'quantity' => 1]],
            'pagos' => [['payment_method_id' => $this->efectivo->id, 'amount' => 10_000]],
            'cupon' => 'fijo5000',
        ])->assertSessionHasNoErrors();

        $venta = $this->trabajando(fn () => Sale::latest('id')->firstOrFail());
        $this->assertEqualsWithDelta(5_950, (float) $venta->total, 0.01, '(10.000 − 5.000) × 1,19');

        $this->actingAs($this->cajero)->get("/sales/{$venta->id}")->assertOk()->assertSee('Cupón FIJO5000');
        $this->actingAs($this->admin)->get('/coupons')->assertOk()->assertSee('FIJO5000');
        $this->actingAs($this->admin)->get('/coupons/' . $venta->coupons->first()->coupon_id)->assertOk()->assertSee($venta->number);
        $this->actingAs($this->cajero)->get('/pos')->assertOk()->assertSee('posCuponCodigo', false);
    }

    /* ═══════════════ Ayudas ═══════════════ */

    /** @param array<string, mixed> $datos */
    private function cupon(array $datos): Coupon
    {
        return $this->trabajando(fn () => Coupon::create($datos + ['type' => 'percentage', 'value' => 10, 'is_active' => true]));
    }

    private function vender(string $cupon): Sale
    {
        return $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [
                ['product_id' => $this->cuaderno->id, 'quantity' => 1],
                ['product_id' => $this->lapiz->id, 'quantity' => 1],
            ],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 60_000]],
            userId: $this->cajero->id,
            codigoCupon: $cupon,
        ));
    }

    private function assertCupónRechazado(string $codigo, string $motivo): void
    {
        try {
            $this->vender($codigo);
            $this->fail("El cupón {$codigo} debió rechazarse.");
        } catch (ValidationException $e) {
            $this->assertStringContainsString($motivo, $e->errors()['cupon'][0]);
        }
    }
}
