<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * VD-VTA-002 · El Vendedor anula una venta, con aprobación (RN-43).
 *   1 · Anular exitosamente (pide → supervisor aprueba → anulada, stock de vuelta).
 *   2 · Venta ya anulada: «Esta venta ya fue anulada».
 *   3 · El stock vuelve solo.
 *   4 · Queda en la auditoría.
 */
class AnulacionConAprobacionTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $vendedor;
    private User $supervisor;
    private Product $lampara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('anulaciones');
        $this->vendedor   = $this->empleado('Vendedor');
        $this->supervisor = $this->empleado('Supervisor');
        $this->lampara    = $this->productoConExistencia('Lámpara', 80_000, 50_000, 10);
    }

    public function test_el_vendedor_pide_la_anulacion_y_la_venta_no_cambia_todavia(): void
    {
        $venta = $this->vender(2);

        $this->actingAs($this->vendedor)
            ->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'El cliente desistió en caja'])
            ->assertRedirect("/sales/{$venta->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sale_void_requests', ['sale_id' => $venta->id, 'status' => 'pendiente', 'requested_by' => $this->vendedor->id]);
        $this->assertSame(Sale::PAGADA, $venta->fresh()->status);
        $this->assertEquals(8, $this->stockDe($this->lampara));
        $this->assertSame(1, $this->supervisor->notifications()->count(), 'Al supervisor le llega el aviso a la campana.');
    }

    public function test_el_vendedor_no_anula_directo(): void
    {
        $venta = $this->vender(1);

        $this->actingAs($this->vendedor)
            ->post("/sales/{$venta->id}/anular", ['motivo' => 'Quiero anularla ya'])
            ->assertForbidden();
    }

    public function test_al_aprobar_queda_anulada_el_stock_vuelve_y_queda_auditada(): void
    {
        // Arrange
        $venta = $this->vender(3);
        $this->actingAs($this->vendedor)->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'Cobré el producto equivocado']);
        $solicitud = SaleVoidRequest::where('sale_id', $venta->id)->sole();

        // Act
        $this->actingAs($this->supervisor)->post("/anulaciones/{$solicitud->id}/aprobar")->assertSessionHasNoErrors();

        // Assert
        $venta->refresh();
        $this->assertSame(Sale::ANULADA, $venta->status);
        $this->assertSame($this->supervisor->id, $venta->voided_by);
        $this->assertStringContainsString('Cobré el producto equivocado', $venta->void_reason);
        $this->assertStringContainsString('solicitada por', $venta->void_reason);
        $this->assertEquals(10, $this->stockDe($this->lampara));
        $this->assertSame(SaleVoidRequest::APROBADA, $solicitud->fresh()->status);
        $this->assertTrue(
            \App\Models\AuditLog::withoutGlobalScopes()->where('auditable_type', Sale::class)->where('auditable_id', $venta->id)
                ->where('event', 'actualizado')->where('new_values', 'like', '%anulada%')->exists(),
            'El cambio de estado a «anulada» queda en la auditoría.',
        );
    }

    public function test_rechazar_exige_motivo_y_deja_la_venta_igual(): void
    {
        $venta = $this->vender(1);
        $this->actingAs($this->vendedor)->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'Me equivoqué de cliente']);
        $solicitud = SaleVoidRequest::where('sale_id', $venta->id)->sole();

        $this->actingAs($this->supervisor)->from("/sales/{$venta->id}")
            ->post("/anulaciones/{$solicitud->id}/rechazar", ['motivo' => ''])->assertSessionHasErrors('motivo');

        $this->actingAs($this->supervisor)
            ->post("/anulaciones/{$solicitud->id}/rechazar", ['motivo' => 'Se corrige con una devolución'])->assertSessionHasNoErrors();

        $this->assertSame(SaleVoidRequest::RECHAZADA, $solicitud->fresh()->status);
        $this->assertSame(Sale::PAGADA, $venta->fresh()->status);

        // Y se puede volver a pedir.
        $this->actingAs($this->vendedor)->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'Ahora sí desistió'])->assertSessionHasNoErrors();
    }

    public function test_una_venta_ya_anulada_no_admite_solicitud(): void
    {
        $venta = $this->vender(1);
        $this->trabajando(fn () => app(SaleService::class)->anular($venta, $this->supervisor->id, 'Error de digitación'));

        $this->actingAs($this->vendedor)->from("/sales/{$venta->id}")
            ->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'El cliente desistió'])
            ->assertSessionHasErrors(['venta' => 'Esta venta ya fue anulada.']);
    }

    public function test_no_hay_dos_solicitudes_pendientes_ni_devoluciones_mientras_espera(): void
    {
        $venta = $this->vender(2);
        $this->actingAs($this->vendedor)->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'Primera solicitud']);

        $this->actingAs($this->vendedor)->from("/sales/{$venta->id}")
            ->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'Segunda solicitud'])
            ->assertSessionHasErrors('venta');

        $this->actingAs($this->vendedor)->from("/sales/{$venta->id}")
            ->post("/sales/{$venta->id}/devolver", [
                'motivo' => 'Devuelve una', 'items' => [['sale_item_id' => $venta->items->first()->id, 'quantity' => 1]],
            ])->assertSessionHasErrors();
        $this->assertDatabaseCount('sale_returns', 0);
    }

    public function test_si_el_supervisor_anula_directo_la_solicitud_queda_aprobada(): void
    {
        $venta = $this->vender(1);
        $this->actingAs($this->vendedor)->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'El cliente desistió']);

        $this->actingAs($this->supervisor)->post("/sales/{$venta->id}/anular", ['motivo' => 'Anulada desde la ficha']);

        $this->assertSame(SaleVoidRequest::APROBADA, SaleVoidRequest::where('sale_id', $venta->id)->value('status'));
    }

    public function test_la_ficha_y_la_bandeja_muestran_la_solicitud(): void
    {
        $venta = $this->vender(1);

        $this->actingAs($this->vendedor)->get("/sales/{$venta->id}")->assertSee('Pedir anulación');
        $this->actingAs($this->vendedor)->post("/sales/{$venta->id}/solicitar-anulacion", ['motivo' => 'El cliente desistió en caja']);

        $this->actingAs($this->vendedor)->get("/sales/{$venta->id}")
            ->assertSee('Anulación por aprobar')->assertDontSee('Aprobar y anular');
        $this->actingAs($this->supervisor)->get("/sales/{$venta->id}")->assertSee('Aprobar y anular');
        $this->actingAs($this->supervisor)->get('/anulaciones')->assertOk()->assertSee($venta->number)->assertSee('El cliente desistió en caja')->assertSee('Aprobar y anular');
        $this->actingAs($this->vendedor)->get('/anulaciones')->assertForbidden();
    }

    private function vender(int $cantidad): Sale
    {
        return $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->lampara->id, 'quantity' => $cantidad]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 80_000 * $cantidad]],
            userId: $this->vendedor->id,
        ));
    }
}
