<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * H-05 · Flujo de compra: borrador → recibida → (anulada).
 *
 * Regla central: registrar NO mueve inventario; solo recibir lo hace.
 * Datos base: «Resma» a $20.000, costo $10.000, 10 en existencia.
 */
class ComprasTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private Product $resma;
    private Supplier $proveedor;
    private PurchaseService $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('compras');
        $this->admin     = $this->empleado('Administrador');
        $this->resma     = $this->productoConExistencia('Resma', 20_000, 10_000, 10);
        $this->proveedor = $this->trabajando(fn () => Supplier::create(['name' => 'Papelería Sur', 'is_active' => true]));
        $this->compras   = app(PurchaseService::class);
    }

    /* ═══════════════ Registrar ═══════════════ */

    public function test_registrar_deja_borrador_con_totales_y_no_mueve_inventario(): void
    {
        $compra = $this->ordenar(cantidad: 5, costo: 9_000, iva: 19);

        // 5 × 9.000 = 45.000 · IVA 19 % = 8.550 · total 53.550
        $this->assertSame(Purchase::BORRADOR, $compra->status);
        $this->assertEqualsWithDelta(45_000, (float) $compra->subtotal, 0.01);
        $this->assertEqualsWithDelta(8_550, (float) $compra->tax_total, 0.01);
        $this->assertEqualsWithDelta(53_550, (float) $compra->total, 0.01);

        $this->assertEqualsWithDelta(10, $this->stockDe($this->resma), 0.001, 'Un borrador no mueve inventario.');
        $this->trabajando(fn () => $this->assertSame(0, StockMovement::where('reason', 'compra')->count()));
    }

    public function test_una_compra_con_cantidad_cero_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);

        $this->ordenar(cantidad: 0, costo: 9_000);
    }

    public function test_el_borrador_se_edita_y_la_recibida_no(): void
    {
        $compra = $this->ordenar(cantidad: 5, costo: 9_000);

        $editada = $this->trabajando(fn () => $this->compras->actualizar(
            $compra, $this->proveedor->id,
            [['product_id' => $this->resma->id, 'quantity' => 8, 'unit_cost' => 9_500]],
            'FAC-99', null, null,
        ));

        $this->assertCount(1, $editada->items, 'Editar reemplaza los renglones, no los suma.');
        $this->assertEqualsWithDelta(76_000, (float) $editada->total, 0.01);

        $this->trabajando(fn () => $this->compras->recibir($editada, $this->admin->id));

        $this->expectException(ValidationException::class);
        $this->trabajando(fn () => $this->compras->actualizar(
            $editada->fresh(), $this->proveedor->id,
            [['product_id' => $this->resma->id, 'quantity' => 1, 'unit_cost' => 1]],
            null, null, null,
        ));
    }

    /* ═══════════════ Recibir ═══════════════ */

    public function test_recibir_suma_existencia_y_deja_kardex_con_el_costo(): void
    {
        $compra = $this->ordenar(cantidad: 5, costo: 9_000);

        $recibida = $this->trabajando(fn () => $this->compras->recibir($compra, $this->admin->id));

        $this->assertSame(Purchase::RECIBIDA, $recibida->status);
        $this->assertNotNull($recibida->received_at);
        $this->assertEqualsWithDelta(15, $this->stockDe($this->resma), 0.001);

        $this->trabajando(function () use ($compra) {
            $movimiento = StockMovement::where('reason', 'compra')->sole();

            $this->assertEqualsWithDelta(5, (float) $movimiento->quantity, 0.001);
            $this->assertEqualsWithDelta(9_000, (float) $movimiento->unit_cost, 0.01);
            $this->assertSame(Purchase::class, $movimiento->source_type);
            $this->assertSame($compra->id, (int) $movimiento->source_id);
        });
    }

    public function test_recibir_dos_veces_no_duplica_la_entrada(): void
    {
        $compra = $this->ordenar(cantidad: 5, costo: 9_000);

        $this->trabajando(fn () => $this->compras->recibir($compra, $this->admin->id));

        // Segundo clic con el modelo viejo en memoria (status aún «borrador»): H-F09.
        try {
            $this->trabajando(fn () => $this->compras->recibir($compra, $this->admin->id));
            $this->fail('La compra se recibió dos veces.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('compra', $e->errors());
        }

        $this->assertEqualsWithDelta(15, $this->stockDe($this->resma), 0.001);
    }

    public function test_sin_actualizar_costo_el_producto_conserva_el_suyo(): void
    {
        $compra = $this->trabajando(fn () => $this->compras->registrar($this->proveedor->id, [[
            'product_id' => $this->resma->id, 'quantity' => 10, 'unit_cost' => 14_000, 'update_cost' => false,
        ]], $this->admin->id));

        $this->trabajando(fn () => $this->compras->recibir($compra, $this->admin->id));

        $this->assertEqualsWithDelta(10_000, $this->costoDe($this->resma), 0.01);
        $this->assertEqualsWithDelta(20, $this->stockDe($this->resma), 0.001, 'El stock sí entra.');
    }

    public function test_el_costo_promedio_pondera_con_la_existencia_previa(): void
    {
        $compra = $this->ordenar(cantidad: 10, costo: 14_000);
        $this->trabajando(fn () => $this->compras->recibir($compra, $this->admin->id));

        // (10 × 10.000 + 10 × 14.000) / 20 = 12.000
        $this->assertEqualsWithDelta(12_000, $this->costoDe($this->resma), 0.01);
    }

    /* ═══════════════ Anular ═══════════════ */

    public function test_anular_un_borrador_no_toca_el_inventario(): void
    {
        $compra = $this->ordenar(cantidad: 5, costo: 9_000);

        $anulada = $this->trabajando(fn () => $this->compras->anular($compra, $this->admin->id, 'Pedido duplicado'));

        $this->assertSame(Purchase::ANULADA, $anulada->status);
        $this->assertStringContainsString('Pedido duplicado', $anulada->notes);
        $this->assertEqualsWithDelta(10, $this->stockDe($this->resma), 0.001);
    }

    public function test_anular_una_recibida_saca_la_mercancia(): void
    {
        $compra = $this->ordenar(cantidad: 5, costo: 9_000);
        $this->trabajando(fn () => $this->compras->recibir($compra, $this->admin->id));

        $this->trabajando(fn () => $this->compras->anular($compra, $this->admin->id, 'Proveedor equivocado'));

        $this->assertEqualsWithDelta(10, $this->stockDe($this->resma), 0.001);
        $this->trabajando(fn () => $this->assertSame(1, StockMovement::where('reason', 'anulacion')->count()));
    }

    public function test_una_compra_anulada_no_se_recibe_ni_se_anula_otra_vez(): void
    {
        $compra = $this->ordenar(cantidad: 5, costo: 9_000);
        $this->trabajando(fn () => $this->compras->anular($compra, $this->admin->id, 'Ya no se necesita'));

        foreach ([
            fn () => $this->compras->recibir($compra, $this->admin->id),
            fn () => $this->compras->anular($compra, $this->admin->id, 'Otra vez'),
        ] as $accion) {
            try {
                $this->trabajando($accion);
                $this->fail('Una compra anulada aceptó otra operación.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('compra', $e->errors());
            }
        }

        $this->assertEqualsWithDelta(10, $this->stockDe($this->resma), 0.001);
    }

    /* ═══════════════ Permisos por HTTP ═══════════════ */

    public function test_el_cajero_no_registra_compras(): void
    {
        $this->actingAs($this->empleado('Cajero'))
            ->post('/purchases', [
                'supplier_id' => $this->proveedor->id,
                'items'       => [['product_id' => $this->resma->id, 'quantity' => 1, 'unit_cost' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_el_bodeguero_recibe_la_mercancia(): void
    {
        $compra = $this->ordenar(cantidad: 5, costo: 9_000);

        $this->actingAs($this->empleado('Bodeguero'))
            ->post("/purchases/{$compra->id}/recibir")
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertEqualsWithDelta(15, $this->stockDe($this->resma), 0.001);
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function ordenar(float $cantidad, float $costo, float $iva = 0): Purchase
    {
        return $this->trabajando(fn () => $this->compras->registrar($this->proveedor->id, [[
            'product_id' => $this->resma->id, 'quantity' => $cantidad, 'unit_cost' => $costo, 'tax_rate' => $iva,
        ]], $this->admin->id));
    }

    private function costoDe(Product $producto): float
    {
        return $this->trabajando(fn () => (float) $producto->fresh()->cost);
    }
}
