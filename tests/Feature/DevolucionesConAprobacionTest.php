<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * RN-30 · Devoluciones con aprobación (AT-DEV-001, BD-DEV-001).
 *
 * Quien no tiene `ventas.aprobar_devolucion` deja la devolución PENDIENTE:
 * reserva la cantidad, no mueve inventario. Un supervisor la aprueba
 * (la mercancía vuelve) o la rechaza (la cantidad se libera).
 *
 * Venta base: 4 «Teclado» a $40.000 sin impuesto. Stock 10 → 6.
 */
class DevolucionesConAprobacionTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $cajero;
    private User $supervisor;
    private Product $teclado;
    private Sale $venta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('dev-aprobacion');
        $this->cajero     = $this->empleado('Cajero');
        $this->supervisor = $this->empleado('Supervisor');
        $this->teclado    = $this->productoConExistencia('Teclado', 40_000, 25_000, 10);

        $this->venta = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->teclado->id, 'quantity' => 4]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 160_000]],
            userId: $this->cajero->id,
        ));
    }

    /* ═══════════════ Registrar ═══════════════ */

    public function test_la_devolucion_del_cajero_queda_pendiente_y_no_mueve_inventario(): void
    {
        $this->devolverComo($this->cajero, 1)->assertSessionHasNoErrors();

        $devolucion = $this->ultima();
        $this->assertSame(SaleReturn::PENDIENTE, $devolucion->status);
        $this->assertNull($devolucion->reviewed_by);
        $this->assertEqualsWithDelta(6, $this->stockDe($this->teclado), 0.001, 'Pendiente: el stock no cambia.');
        $this->assertEqualsWithDelta(1, $this->reservado(), 0.001, 'La cantidad sí queda reservada.');
    }

    public function test_la_devolucion_del_supervisor_se_aplica_de_una_vez(): void
    {
        $this->devolverComo($this->supervisor, 1)->assertSessionHasNoErrors();

        $devolucion = $this->ultima();
        $this->assertSame(SaleReturn::APROBADA, $devolucion->status);
        $this->assertSame($this->supervisor->id, $devolucion->reviewed_by);
        $this->assertEqualsWithDelta(7, $this->stockDe($this->teclado), 0.001);
    }

    public function test_lo_pendiente_no_se_puede_volver_a_pedir(): void
    {
        $this->devolverComo($this->cajero, 4);

        $this->devolverComo($this->cajero, 1)->assertSessionHasErrors('items');
    }

    public function test_el_bodeguero_puede_registrar_devoluciones_que_quedan_pendientes(): void
    {
        $this->devolverComo($this->empleado('Bodeguero'), 1)->assertSessionHasNoErrors();

        $this->assertSame(SaleReturn::PENDIENTE, $this->ultima()->status);
    }

    /* ═══════════════ Aprobar ═══════════════ */

    public function test_al_aprobarla_la_mercancia_vuelve_al_inventario(): void
    {
        $this->devolverComo($this->cajero, 2);
        $devolucion = $this->ultima();

        $this->actingAs($this->supervisor)
            ->post("/returns/{$devolucion->id}/aprobar")
            ->assertSessionHasNoErrors();

        $devolucion = $this->ultima();
        $this->assertSame(SaleReturn::APROBADA, $devolucion->status);
        $this->assertSame($this->supervisor->id, $devolucion->reviewed_by);
        $this->assertEqualsWithDelta(8, $this->stockDe($this->teclado), 0.001);
        $this->assertEqualsWithDelta(80_000, $this->trabajando(fn () => $this->venta->fresh()->total_devuelto), 0.01);
    }

    public function test_el_cajero_no_puede_aprobar(): void
    {
        $this->devolverComo($this->cajero, 1);

        $this->actingAs($this->cajero)
            ->post("/returns/{$this->ultima()->id}/aprobar")
            ->assertForbidden();

        $this->assertEqualsWithDelta(6, $this->stockDe($this->teclado), 0.001);
    }

    public function test_no_se_aprueba_dos_veces(): void
    {
        $this->devolverComo($this->cajero, 1);
        $id = $this->ultima()->id;

        $this->actingAs($this->supervisor)->post("/returns/{$id}/aprobar");
        $this->actingAs($this->supervisor)->post("/returns/{$id}/aprobar")->assertForbidden();

        $this->assertEqualsWithDelta(7, $this->stockDe($this->teclado), 0.001, 'Un solo reingreso.');
    }

    /* ═══════════════ Rechazar ═══════════════ */

    public function test_al_rechazarla_se_libera_la_cantidad_y_el_inventario_no_cambia(): void
    {
        $this->devolverComo($this->cajero, 3);

        $this->actingAs($this->supervisor)
            ->post("/returns/{$this->ultima()->id}/rechazar", ['motivo_rechazo' => 'No corresponde a la venta'])
            ->assertSessionHasNoErrors();

        $devolucion = $this->ultima();
        $this->assertSame(SaleReturn::RECHAZADA, $devolucion->status);
        $this->assertSame('No corresponde a la venta', $devolucion->rejection_reason);
        $this->assertEqualsWithDelta(0, $this->reservado(), 0.001);
        $this->assertEqualsWithDelta(6, $this->stockDe($this->teclado), 0.001);
        $this->assertEqualsWithDelta(0, $this->trabajando(fn () => $this->venta->fresh()->total_devuelto), 0.01);
    }

    public function test_rechazar_exige_motivo(): void
    {
        $this->devolverComo($this->cajero, 1);

        $this->actingAs($this->supervisor)
            ->post("/returns/{$this->ultima()->id}/rechazar", ['motivo_rechazo' => ''])
            ->assertSessionHasErrors('motivo_rechazo');

        $this->assertSame(SaleReturn::PENDIENTE, $this->ultima()->status);
    }

    /* ═══════════════ Anulación (RN-25) ═══════════════ */

    public function test_una_devolucion_pendiente_impide_anular_la_venta(): void
    {
        $this->devolverComo($this->cajero, 1);

        $this->actingAs($this->supervisor)
            ->post("/sales/{$this->venta->id}/anular", ['motivo' => 'Prueba de anulación'])
            ->assertSessionHasErrors('venta');
    }

    public function test_si_la_devolucion_fue_rechazada_la_venta_si_se_puede_anular(): void
    {
        $this->devolverComo($this->cajero, 1);
        $this->actingAs($this->supervisor)
            ->post("/returns/{$this->ultima()->id}/rechazar", ['motivo_rechazo' => 'Cliente desistió']);

        $this->actingAs($this->supervisor)
            ->post("/sales/{$this->venta->id}/anular", ['motivo' => 'Prueba de anulación'])
            ->assertSessionHasNoErrors();

        $this->trabajando(fn () => $this->assertSame(Sale::ANULADA, $this->venta->fresh()->status));
        $this->assertEqualsWithDelta(10, $this->stockDe($this->teclado), 0.001);
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function devolverComo(User $usuario, float $cantidad)
    {
        $itemId = $this->trabajando(fn () => $this->venta->items()->value('id'));

        return $this->actingAs($usuario)
            ->from("/sales/{$this->venta->id}/devolver")
            ->post("/sales/{$this->venta->id}/devolver", [
                'motivo'  => 'El cliente cambió de opinión',
                'restock' => '1',
                'items'   => [['sale_item_id' => $itemId, 'quantity' => $cantidad]],
            ]);
    }

    private function ultima(): SaleReturn
    {
        return $this->trabajando(fn () => SaleReturn::latest('id')->firstOrFail());
    }

    private function reservado(): float
    {
        return $this->trabajando(fn () => (float) $this->venta->items()->value('returned_quantity'));
    }
}
