<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * H-05 · Flujo de venta (POS): totales, impuestos, pagos, inventario,
 * kardex, consecutivo y anulación.
 *
 * Datos base: «Cuaderno» a $12.000, costo $6.000, 100 en existencia, IVA 19 %.
 */
class VentasPosTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $cajero;
    private User $admin;
    private Product $cuaderno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('ventas-pos');
        $this->cajero = $this->empleado('Cajero');
        $this->admin  = $this->empleado('Administrador');

        $iva = $this->trabajando(fn () => Tax::create([
            'name' => 'IVA 19 %', 'rate' => 19, 'type' => 'percentage', 'is_active' => true,
        ]));

        $this->cuaderno = $this->productoConExistencia('Cuaderno', 12_000, 6_000, 100, $iva->id);
    }

    /* ═══════════════ Totales e impuestos ═══════════════ */

    public function test_la_venta_calcula_subtotal_iva_total_y_utilidad(): void
    {
        $venta = $this->vender(cantidad: 2, pago: 30_000);

        // 2 × 12.000 = 24.000 · IVA 19 % = 4.560 · total 28.560 · costo 12.000
        $this->assertEqualsWithDelta(24_000, (float) $venta->subtotal, 0.01);
        $this->assertEqualsWithDelta(4_560, (float) $venta->tax_total, 0.01);
        $this->assertEqualsWithDelta(28_560, (float) $venta->total, 0.01);
        $this->assertEqualsWithDelta(12_000, (float) $venta->cost_total, 0.01);
        $this->assertEqualsWithDelta(12_000, (float) $venta->profit_total, 0.01, 'La utilidad se calcula sin IVA.');
        $this->assertSame(Sale::PAGADA, $venta->status);
    }

    public function test_el_iva_se_calcula_sobre_el_renglon_ya_descontado(): void
    {
        $venta = $this->vender(cantidad: 1, pago: 20_000, descuento: 2_000);

        // (12.000 − 2.000) × 19 % = 1.900
        $this->assertEqualsWithDelta(10_000, (float) $venta->subtotal, 0.01);
        $this->assertEqualsWithDelta(1_900, (float) $venta->tax_total, 0.01);
        $this->assertEqualsWithDelta(2_000, (float) $venta->discount_total, 0.01);
    }

    public function test_el_impuesto_fijo_se_cobra_por_unidad(): void
    {
        $bolsa = $this->trabajando(function () {
            $impuesto = Tax::create(['name' => 'Impuesto bolsa', 'rate' => 66, 'type' => 'fixed', 'is_active' => true]);

            return $impuesto->id;
        });
        $producto = $this->productoConExistencia('Bolsa', 100, 20, 50, $bolsa);

        $venta = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $producto->id, 'quantity' => 3]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 1_000]],
            userId: $this->cajero->id,
        ));

        $this->assertEqualsWithDelta(198, (float) $venta->tax_total, 0.01, '66 × 3 unidades.');
        $this->assertEqualsWithDelta(498, (float) $venta->total, 0.01);
    }

    /* ═══════════════ Pagos ═══════════════ */

    public function test_el_vuelto_es_lo_recibido_menos_el_total(): void
    {
        $venta = $this->vender(cantidad: 2, pago: 50_000);

        $this->assertEqualsWithDelta(50_000, (float) $venta->paid_total, 0.01);
        $this->assertEqualsWithDelta(21_440, (float) $venta->change_amount, 0.01);
    }

    public function test_el_pago_mixto_suma_los_medios(): void
    {
        $tarjeta = $this->trabajando(fn () => PaymentMethod::create(['name' => 'Tarjeta', 'is_active' => true]));

        $venta = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->cuaderno->id, 'quantity' => 2]],
            pagos: [
                ['payment_method_id' => $this->efectivo->id, 'amount' => 10_000],
                ['payment_method_id' => $tarjeta->id, 'amount' => 18_560, 'reference' => 'AUT-123'],
            ],
            userId: $this->cajero->id,
        ));

        $this->assertCount(2, $venta->payments);
        $this->assertEqualsWithDelta(0, (float) $venta->change_amount, 0.01);
    }

    public function test_un_pago_insuficiente_no_deja_rastro(): void
    {
        try {
            $this->vender(cantidad: 2, pago: 28_000); // faltan 560
            $this->fail('Se registró una venta sin cubrir el total.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pagos', $e->errors());
        }

        // La transacción revierte TODO: venta, renglones, kardex y existencia.
        $this->trabajando(function () {
            $this->assertSame(0, Sale::count());
            $this->assertSame(0, StockMovement::where('reason', 'venta')->count());
        });
        $this->assertEqualsWithDelta(100, $this->stockDe($this->cuaderno), 0.001);
    }

    /* ═══════════════ Inventario y kardex ═══════════════ */

    public function test_vender_descuenta_la_existencia_y_deja_kardex(): void
    {
        $venta = $this->vender(cantidad: 3, pago: 50_000);

        $this->assertEqualsWithDelta(97, $this->stockDe($this->cuaderno), 0.001);

        $this->trabajando(function () use ($venta) {
            $movimiento = StockMovement::where('reason', 'venta')->sole();

            $this->assertEqualsWithDelta(-3, (float) $movimiento->quantity, 0.001);
            $this->assertEqualsWithDelta(97, (float) $movimiento->balance_after, 0.001);
            $this->assertSame(Sale::class, $movimiento->source_type);
            $this->assertSame($venta->id, (int) $movimiento->source_id);
            $this->assertSame($this->tienda->id, (int) $movimiento->tienda_id);
        });
    }

    public function test_cantidad_cero_o_venta_vacia_se_rechazan(): void
    {
        foreach ([[['product_id' => $this->cuaderno->id, 'quantity' => 0]], []] as $lineas) {
            try {
                $this->trabajando(fn () => app(SaleService::class)->registrar(
                    lineas: $lineas,
                    pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 10_000]],
                    userId: $this->cajero->id,
                ));
                $this->fail('Aceptó una venta sin cantidades válidas.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('items', $e->errors());
            }
        }

        $this->trabajando(fn () => $this->assertSame(0, Sale::count()));
    }

    /* ═══════════════ Consecutivo (RN-07) ═══════════════ */

    public function test_dos_ventas_reciben_numeros_consecutivos(): void
    {
        $primera = $this->vender(cantidad: 1, pago: 20_000);
        $segunda = $this->vender(cantidad: 1, pago: 20_000);

        $numero = fn (Sale $v) => (int) preg_replace('/\D/', '', $v->number);

        $this->assertSame($numero($primera) + 1, $numero($segunda));
    }

    /* ═══════════════ Anulación ═══════════════ */

    public function test_anular_devuelve_la_mercancia_y_no_se_anula_dos_veces(): void
    {
        $venta = $this->vender(cantidad: 4, pago: 60_000);
        $servicio = app(SaleService::class);

        $this->trabajando(fn () => $servicio->anular($venta, $this->admin->id, 'Cliente se arrepintió'));

        $this->assertEqualsWithDelta(100, $this->stockDe($this->cuaderno), 0.001);
        $this->trabajando(function () use ($venta) {
            $this->assertSame(Sale::ANULADA, $venta->fresh()->status);
            $this->assertSame(1, StockMovement::where('reason', 'anulacion')->count());
        });

        // Segundo clic sobre el modelo viejo en memoria (status aún «pagada»).
        $this->expectException(ValidationException::class);
        $this->trabajando(fn () => $servicio->anular($venta, $this->admin->id, 'Doble clic'));
    }

    public function test_una_venta_con_devoluciones_no_se_anula(): void
    {
        // H-F08 · Antes reingresaba las 5 unidades completas: 98 + 5 = 103.
        $venta = $this->vender(cantidad: 5, pago: 80_000);

        $this->trabajando(fn () => app(ReturnService::class)->registrar(
            $venta,
            [['sale_item_id' => $venta->items->first()->id, 'quantity' => 3]],
            $this->admin->id,
            'Producto defectuoso',
        ));
        $this->assertEqualsWithDelta(98, $this->stockDe($this->cuaderno), 0.001);

        try {
            $this->trabajando(fn () => app(SaleService::class)->anular($venta, $this->admin->id, 'Anular todo'));
            $this->fail('Se anuló una venta que ya tenía devoluciones.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('venta', $e->errors());
        }

        $this->assertEqualsWithDelta(98, $this->stockDe($this->cuaderno), 0.001, 'El inventario no debe moverse.');
        $this->trabajando(fn () => $this->assertSame(Sale::PAGADA, $venta->fresh()->status));
    }

    /* ═══════════════ Permisos por HTTP ═══════════════ */

    public function test_el_cajero_vende_desde_el_pos(): void
    {
        $this->actingAs($this->cajero)
            ->post('/pos', [
                'items' => [['product_id' => $this->cuaderno->id, 'quantity' => 1]],
                'pagos' => [['payment_method_id' => $this->efectivo->id, 'amount' => 20_000]],
            ])
            ->assertSessionHasNoErrors();

        $this->trabajando(fn () => $this->assertSame(1, Sale::count()));
    }

    public function test_el_cajero_no_puede_anular(): void
    {
        $venta = $this->vender(cantidad: 1, pago: 20_000);

        $this->actingAs($this->cajero)
            ->post("/sales/{$venta->id}/anular", ['motivo' => 'Intento sin permiso'])
            ->assertForbidden();

        $this->trabajando(fn () => $this->assertSame(Sale::PAGADA, $venta->fresh()->status));
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function vender(float $cantidad, float $pago, float $descuento = 0): Sale
    {
        return $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->cuaderno->id, 'quantity' => $cantidad, 'discount' => $descuento]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => $pago]],
            userId: $this->cajero->id,
        ));
    }
}
