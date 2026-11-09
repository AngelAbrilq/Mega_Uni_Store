<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\SaleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * H-05 · Devoluciones parciales: la venta original no se toca, se crea un
 * documento que la referencia y devuelve la parte proporcional de lo cobrado.
 *
 * Venta base: 4 «Mouse» a $50.000 con IVA 19 % y $20.000 de descuento.
 *   subtotal 180.000 · IVA 34.200 · total 214.200
 */
class DevolucionesTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $cajero;
    private Product $mouse;
    private Sale $venta;
    private ReturnService $devoluciones;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('devoluciones');
        $this->cajero = $this->empleado('Cajero');

        $iva = $this->trabajando(fn () => Tax::create([
            'name' => 'IVA 19 %', 'rate' => 19, 'type' => 'percentage', 'is_active' => true,
        ]));
        $this->mouse = $this->productoConExistencia('Mouse', 50_000, 30_000, 10, $iva->id);

        $this->venta = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->mouse->id, 'quantity' => 4, 'discount' => 20_000]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 214_200]],
            userId: $this->cajero->id,
        ));

        $this->devoluciones = app(ReturnService::class);
    }

    public function test_la_venta_base_cuadra(): void
    {
        $this->assertEqualsWithDelta(214_200, (float) $this->venta->total, 0.01);
        $this->assertEqualsWithDelta(6, $this->stockDe($this->mouse), 0.001);
    }

    /* ═══════════════ Montos ═══════════════ */

    public function test_devuelve_la_parte_proporcional_de_lo_cobrado(): void
    {
        $devolucion = $this->devolver(1);

        // 1/4 de 180.000 = 45.000 (el descuento se reparte) · IVA 8.550 · total 53.550
        $this->assertEqualsWithDelta(45_000, (float) $devolucion->subtotal, 0.01);
        $this->assertEqualsWithDelta(8_550, (float) $devolucion->tax_total, 0.01);
        $this->assertEqualsWithDelta(53_550, (float) $devolucion->total, 0.01);
    }

    public function test_devolver_todo_en_partes_suma_exactamente_lo_cobrado(): void
    {
        $this->devolver(1);
        $this->devolver(3);

        $this->trabajando(fn () => $this->assertEqualsWithDelta(
            (float) $this->venta->total,
            (float) SaleReturn::sum('total'),
            0.01,
            'Lo devuelto en varias partes no puede pasar de lo cobrado.',
        ));
    }

    /* ═══════════════ Inventario ═══════════════ */

    public function test_la_mercancia_vuelve_a_bodega_con_kardex(): void
    {
        $devolucion = $this->devolver(2);

        $this->assertEqualsWithDelta(8, $this->stockDe($this->mouse), 0.001);
        $this->trabajando(function () use ($devolucion) {
            $movimiento = StockMovement::where('reason', 'devolucion')->sole();

            $this->assertEqualsWithDelta(2, (float) $movimiento->quantity, 0.001);
            $this->assertSame(SaleReturn::class, $movimiento->source_type);
            $this->assertSame($devolucion->id, (int) $movimiento->source_id);
            $this->assertEqualsWithDelta(2, (float) $this->venta->items()->first()->returned_quantity, 0.001);
        });
    }

    public function test_la_mercancia_danada_no_vuelve_a_bodega(): void
    {
        $devolucion = $this->devolver(2, reingresar: false);

        $this->assertFalse((bool) $devolucion->restock);
        $this->assertEqualsWithDelta(6, $this->stockDe($this->mouse), 0.001);
        $this->assertEqualsWithDelta(107_100, (float) $devolucion->total, 0.01, 'El dinero sí se devuelve.');
    }

    /* ═══════════════ Reglas ═══════════════ */

    public function test_no_se_devuelve_mas_de_lo_vendido_ni_sumando_devoluciones(): void
    {
        $this->devolver(3);

        try {
            $this->devolver(2); // solo queda 1
            $this->fail('Se devolvió más de lo vendido.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('solo quedan 1', $e->errors()['items'][0]);
        }

        $this->assertEqualsWithDelta(9, $this->stockDe($this->mouse), 0.001);
        $this->trabajando(fn () => $this->assertSame(1, SaleReturn::count()));
    }

    public function test_sin_cantidades_no_hay_devolucion(): void
    {
        $this->expectException(ValidationException::class);

        $this->devolver(0);
    }

    public function test_no_se_devuelve_sobre_una_venta_anulada(): void
    {
        $this->trabajando(fn () => app(SaleService::class)->anular($this->venta, $this->cajero->id, 'Error de digitación'));

        // El modelo en memoria sigue «pagada»: la regla se valida contra la base (H-F09).
        $this->expectException(ValidationException::class);
        $this->devolver(1);
    }

    public function test_no_se_devuelve_un_renglon_de_otra_venta(): void
    {
        $otra = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->mouse->id, 'quantity' => 1]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 60_000]],
            userId: $this->cajero->id,
        ));

        $this->expectException(ModelNotFoundException::class);

        $this->trabajando(fn () => $this->devoluciones->registrar(
            $this->venta,
            [['sale_item_id' => $otra->items->first()->id, 'quantity' => 1]],
            $this->cajero->id,
            'Renglón ajeno',
        ));
    }

    /* ═══════════════ HTTP ═══════════════ */

    public function test_la_devolucion_desde_la_pantalla_exige_motivo(): void
    {
        $this->actingAs($this->cajero)
            ->from("/sales/{$this->venta->id}/devolver")
            ->post("/sales/{$this->venta->id}/devolver", [
                'motivo' => 'no',
                'items'  => [['sale_item_id' => $this->venta->items->first()->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('motivo');

        $this->trabajando(fn () => $this->assertSame(0, SaleReturn::count()));
    }

    public function test_el_cajero_registra_la_devolucion_desde_la_pantalla(): void
    {
        $this->actingAs($this->cajero)
            ->post("/sales/{$this->venta->id}/devolver", [
                'motivo'  => 'Producto defectuoso',
                'restock' => '1',
                'items'   => [['sale_item_id' => $this->venta->items->first()->id, 'quantity' => 1]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // RN-30: el cajero no aprueba; queda pendiente y el stock no se mueve todavía.
        $this->trabajando(fn () => $this->assertSame(SaleReturn::PENDIENTE, SaleReturn::latest('id')->value('status')));
        $this->assertEqualsWithDelta(6, $this->stockDe($this->mouse), 0.001);
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function devolver(float $cantidad, bool $reingresar = true): SaleReturn
    {
        return $this->trabajando(fn () => $this->devoluciones->registrar(
            $this->venta,
            [['sale_item_id' => $this->venta->items->first()->id, 'quantity' => $cantidad]],
            $this->cajero->id,
            'Producto defectuoso',
            $reingresar,
        ));
    }
}
