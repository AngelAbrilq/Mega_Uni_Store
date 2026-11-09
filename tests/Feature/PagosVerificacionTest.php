<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * TBL-PAG-001 · Estado de los pagos (aprobado | pendiente | rechazado).
 * RN-32      · Un medio que exige verificación deja el pago pendiente; un
 *              pago rechazado no cuenta como recibido y deja saldo.
 */
class PagosVerificacionTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $cajero;
    private User $supervisor;
    private PaymentMethod $transferencia;
    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('pagos');
        $this->cajero     = $this->empleado('Cajero');
        $this->supervisor = $this->empleado('Supervisor');

        $this->transferencia = $this->trabajando(fn () => PaymentMethod::create([
            'name' => 'Transferencia', 'is_active' => true, 'requires_verification' => true,
        ]));

        $this->producto = $this->productoConExistencia('Morral', 50_000, 30_000, 10);
    }

    /* ═══════════════ Estado al cobrar ═══════════════ */

    public function test_un_medio_que_exige_verificacion_deja_el_pago_pendiente(): void
    {
        $venta = $this->vender([[$this->transferencia->id, 50_000]]);

        $this->assertSame(SalePayment::PENDIENTE, $venta->payments->first()->status);
        $this->assertTrue($venta->pago_por_verificar);
        $this->assertEqualsWithDelta(0, $venta->saldo_pendiente, 0.01, 'Pendiente no es deuda todavía.');
    }

    public function test_el_efectivo_queda_aprobado_y_un_pago_mixto_conserva_cada_estado(): void
    {
        $venta = $this->vender([[$this->efectivo->id, 20_000], [$this->transferencia->id, 30_000]]);

        $this->assertEqualsCanonicalizing(
            [SalePayment::APROBADO, SalePayment::PENDIENTE],
            $venta->payments->pluck('status')->all()
        );
    }

    /* ═══════════════ Verificación ═══════════════ */

    public function test_el_supervisor_aprueba_un_pago_pendiente(): void
    {
        $pago = $this->vender([[$this->transferencia->id, 50_000]])->payments->first();

        $this->actingAs($this->supervisor)->post("/payments/{$pago->id}/aprobar")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sale_payments', [
            'id' => $pago->id, 'status' => SalePayment::APROBADO, 'verified_by' => $this->supervisor->id,
        ]);
    }

    public function test_rechazar_un_pago_deja_saldo_por_cobrar_y_no_cuenta_en_lo_recibido(): void
    {
        // Arrange: pago mixto, la transferencia no llegó
        $venta = $this->vender([[$this->efectivo->id, 20_000], [$this->transferencia->id, 30_000]]);
        $pago  = $venta->payments->firstWhere('status', SalePayment::PENDIENTE);

        // Act
        $this->actingAs($this->supervisor)
            ->post("/payments/{$pago->id}/rechazar", ['rejection_reason' => 'No llegó al banco'])
            ->assertSessionHasNoErrors();

        // Assert
        $this->trabajando(function () use ($venta) {
            $fresca = Sale::with('payments')->find($venta->id);
            $this->assertEqualsWithDelta(30_000, $fresca->saldo_pendiente, 0.01);
            $this->assertEqualsWithDelta(20_000, (float) SalePayment::vigentes()->where('sale_id', $venta->id)->sum('amount'), 0.01);
        });
        $this->assertDatabaseHas('sale_payments', ['id' => $pago->id, 'rejection_reason' => 'No llegó al banco']);
    }

    public function test_rechazar_exige_motivo(): void
    {
        $pago = $this->vender([[$this->transferencia->id, 50_000]])->payments->first();

        $this->actingAs($this->supervisor)
            ->from('/payments')
            ->post("/payments/{$pago->id}/rechazar", ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertDatabaseHas('sale_payments', ['id' => $pago->id, 'status' => SalePayment::PENDIENTE]);
    }

    public function test_el_cajero_no_verifica_pagos(): void
    {
        $pago = $this->vender([[$this->transferencia->id, 50_000]])->payments->first();

        $this->actingAs($this->cajero)->post("/payments/{$pago->id}/aprobar")->assertForbidden();
        $this->actingAs($this->cajero)->get('/payments')->assertForbidden();
    }

    public function test_un_pago_ya_revisado_no_se_revisa_otra_vez(): void
    {
        $pago = $this->vender([[$this->transferencia->id, 50_000]])->payments->first();
        $this->actingAs($this->supervisor)->post("/payments/{$pago->id}/aprobar");

        $this->actingAs($this->supervisor)
            ->from('/payments')
            ->post("/payments/{$pago->id}/rechazar", ['rejection_reason' => 'Tarde'])
            ->assertSessionHasErrors('pago');

        $this->assertDatabaseHas('sale_payments', ['id' => $pago->id, 'status' => SalePayment::APROBADO]);
    }

    /* ═══════════════ Pantallas ═══════════════ */

    public function test_las_pantallas_muestran_el_estado_del_pago(): void
    {
        $venta = $this->vender([[$this->transferencia->id, 50_000]]);

        $this->actingAs($this->supervisor)->get('/payments')
            ->assertOk()->assertSee($venta->number)->assertSee('Llegó · aprobar');
        $this->actingAs($this->supervisor)->get("/sales/{$venta->id}")
            ->assertOk()->assertSee('Por verificar');
        $this->actingAs($this->supervisor)->get('/payments?estado=rechazado')->assertOk();
    }

    /* ═══════════════ Ayudas ═══════════════ */

    /** @param array<int, array{0:int, 1:float}> $pagos  [medio, monto] */
    private function vender(array $pagos): Sale
    {
        return $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->producto->id, 'quantity' => 1]],
            pagos: array_map(fn ($p) => ['payment_method_id' => $p[0], 'amount' => $p[1]], $pagos),
            userId: $this->cajero->id,
        ));
    }
}
