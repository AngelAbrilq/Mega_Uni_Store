<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Existencia;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Tienda;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * H-04 · Policies por registro + H-F12 · el inventario se mueve en el local
 * del documento.
 *
 * Negocio con dos locales: Centro (el activo de todos los usuarios de la
 * prueba) y Norte. Un documento de Norte se ve desde Centro, pero no se opera.
 */
class OperacionPorLocalTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private Tienda $norte;
    private User $admin;
    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('dos-locales');
        $this->norte = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Norte', 'slug' => 'norte-dos-locales',
            'es_principal' => false, 'activa' => true,
        ]);

        $this->admin    = $this->empleado('Administrador'); // asignado solo a Centro
        $this->producto = $this->productoConExistencia('Taladro', 100_000, 60_000, 10);

        $this->enNorte(fn () => Existencia::create([
            'tienda_id' => $this->norte->id, 'product_id' => $this->producto->id,
            'stock' => 10, 'min_stock' => 0, 'precio' => 100_000, 'activo' => true,
        ]));
    }

    /* ═══════════════ Ventas ═══════════════ */

    public function test_una_venta_de_otro_local_se_ve_pero_no_se_anula(): void
    {
        $venta = $this->enNorte(fn () => $this->vender());

        $this->actingAs($this->admin)->get("/sales/{$venta->id}")->assertOk();

        $this->actingAs($this->admin)
            ->post("/sales/{$venta->id}/anular", ['motivo' => 'Intento desde Centro'])
            ->assertForbidden();

        $this->trabajando(fn () => $this->assertSame(Sale::PAGADA, $venta->fresh()->status));
    }

    public function test_una_venta_del_propio_local_si_se_anula(): void
    {
        $venta = $this->trabajando(fn () => $this->vender());

        $this->actingAs($this->admin)
            ->post("/sales/{$venta->id}/anular", ['motivo' => 'Cliente se arrepintió'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->trabajando(fn () => $this->assertSame(Sale::ANULADA, $venta->fresh()->status));
    }

    public function test_no_se_devuelve_una_venta_de_otro_local(): void
    {
        $venta = $this->enNorte(fn () => $this->vender());

        $this->actingAs($this->admin)->get("/sales/{$venta->id}/devolver")->assertForbidden();
    }

    public function test_la_ficha_solo_ofrece_anular_cuando_se_puede(): void
    {
        $propia = $this->trabajando(fn () => $this->vender());
        $ajena  = $this->enNorte(fn () => $this->vender());
        $conDevolucion = $this->trabajando(function () {
            $venta = $this->vender();
            app(\App\Services\ReturnService::class)->registrar(
                $venta, [['sale_item_id' => $venta->items->first()->id, 'quantity' => 1]],
                $this->admin->id, 'Producto defectuoso',
            );

            return $venta;
        });

        $this->actingAs($this->admin)->get("/sales/{$propia->id}")->assertSee('id="btnAnular"', false);
        $this->actingAs($this->admin)->get("/sales/{$ajena->id}")->assertDontSee('id="btnAnular"', false);
        $this->actingAs($this->admin)->get("/sales/{$conDevolucion->id}")->assertDontSee('id="btnAnular"', false);
    }

    /* ═══════════════ Compras + H-F12 ═══════════════ */

    public function test_no_se_recibe_una_compra_de_otro_local(): void
    {
        $compra = $this->enNorte(fn () => $this->ordenar());

        $this->actingAs($this->admin)
            ->post("/purchases/{$compra->id}/recibir")
            ->assertForbidden();

        $this->trabajando(fn () => $this->assertSame(Purchase::BORRADOR, $compra->fresh()->status));
    }

    public function test_recibir_mete_la_mercancia_en_el_local_de_la_compra(): void
    {
        // H-F12: compra de Norte procesada con el contexto puesto en Centro
        // (p. ej. un comando o un servicio llamado sin pasar por la Policy).
        $compra = $this->enNorte(fn () => $this->ordenar(cantidad: 5));

        $this->trabajando(fn () => app(PurchaseService::class)->recibir($compra, $this->admin->id));

        $this->assertEqualsWithDelta(10, $this->existenciaEn($this->tienda->id), 0.001, 'Centro no recibió nada.');
        $this->assertEqualsWithDelta(15, $this->existenciaEn($this->norte->id), 0.001, 'La mercancía entra a Norte.');
    }

    /* ═══════════════ Caja ═══════════════ */

    public function test_un_cajero_no_cierra_el_turno_de_otro(): void
    {
        $turno = $this->turnoDe($this->empleado('Cajero'));

        $this->actingAs($this->empleado('Cajero'))
            ->post("/cash/{$turno->id}/cerrar", ['counted_amount' => 0])
            ->assertForbidden();

        $this->trabajando(fn () => $this->assertSame(CashSession::ABIERTA, $turno->fresh()->status));
    }

    public function test_el_cajero_cierra_su_propio_turno(): void
    {
        $cajero = $this->empleado('Cajero');
        $turno  = $this->turnoDe($cajero);

        $this->actingAs($cajero)
            ->post("/cash/{$turno->id}/cerrar", ['counted_amount' => 0])
            ->assertSessionHasNoErrors();

        $this->trabajando(fn () => $this->assertSame(CashSession::CERRADA, $turno->fresh()->status));
    }

    public function test_el_supervisor_cierra_el_turno_que_dejo_abierto_un_cajero(): void
    {
        $turno = $this->turnoDe($this->empleado('Cajero'));

        $this->actingAs($this->empleado('Supervisor'))
            ->post("/cash/{$turno->id}/cerrar", ['counted_amount' => 0])
            ->assertSessionHasNoErrors();

        $this->trabajando(fn () => $this->assertSame(CashSession::CERRADA, $turno->fresh()->status));
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function vender(): Sale
    {
        return app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->producto->id, 'quantity' => 1]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 100_000]],
            userId: $this->admin->id,
        );
    }

    private function ordenar(float $cantidad = 1): Purchase
    {
        $proveedor = Supplier::firstOrCreate(['name' => 'Ferretería Mayorista'], ['is_active' => true]);

        return app(PurchaseService::class)->registrar($proveedor->id, [[
            'product_id' => $this->producto->id, 'quantity' => $cantidad, 'unit_cost' => 60_000,
        ]], $this->admin->id);
    }

    private function turnoDe(User $cajero): CashSession
    {
        return $this->trabajando(fn () => CashSession::create([
            'user_id' => $cajero->id, 'opening_amount' => 0,
            'status'  => CashSession::ABIERTA, 'opened_at' => now(),
        ]));
    }

    private function existenciaEn(int $tiendaId): float
    {
        return (float) $this->trabajando(fn () => Existencia::where('tienda_id', $tiendaId)
            ->where('product_id', $this->producto->id)->value('stock'));
    }

    private function enNorte(callable $fn): mixed
    {
        try {
            return Contexto::comoEmpresa($this->empresa->id, $this->norte->id, $fn);
        } finally {
            Contexto::olvidar();
        }
    }
}
