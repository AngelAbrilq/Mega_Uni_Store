<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * SV-VTA-001 · Supervisar las ventas del día en tiempo real (RN-42).
 *   1 · Ver ventas del día con estado, cliente, empleado, monto y medio.
 *   2 · Detectar venta de alto valor y abrir su detalle.
 *   3 · KPI acumulado: total, número de ventas y promedio.
 *   4 · Anuladas con quién y por qué.
 */
class MonitorVentasTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $supervisor;
    private User $cajero;
    private Product $silla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('monitor');
        $this->supervisor = $this->empleado('Supervisor');
        $this->cajero     = $this->empleado('Cajero');
        $this->silla      = $this->productoConExistencia('Silla', 100_000, 60_000, 500);
    }

    public function test_el_supervisor_ve_las_ventas_del_dia_con_sus_datos(): void
    {
        $ana = $this->trabajando(fn () => Customer::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']));
        $hoy = $this->vender(1, $ana->id);
        $ayer = $this->vender(1);
        $ayer->forceFill(['sold_at' => now()->subDay()])->save();

        $this->actingAs($this->supervisor)->get('/sales/monitor')
            ->assertOk()
            ->assertSee($hoy->number)
            ->assertSee('Ana Ruiz')
            ->assertSee($this->cajero->name)
            ->assertSee('Efectivo')
            ->assertDontSee($ayer->number);
    }

    public function test_el_resumen_trae_total_numero_y_promedio_del_dia(): void
    {
        $this->vender(1);
        $this->vender(3);

        $r = $this->actingAs($this->supervisor)->getJson('/sales/monitor/datos')->assertOk()->json('resumen');

        $this->assertEquals(400_000, $r['total']);
        $this->assertSame(2, $r['ventas']);
        $this->assertEquals(200_000, $r['promedio']);
        $this->assertSame('Efectivo', $r['medios'][0]['nombre']);
    }

    public function test_el_sondeo_solo_trae_lo_nuevo(): void
    {
        $vieja = $this->vender(1);
        $nueva = $this->vender(2);

        $nuevas = $this->actingAs($this->supervisor)
            ->getJson("/sales/monitor/datos?despues={$vieja->id}")->json('nuevas');

        $this->assertSame([$nueva->id], array_column($nuevas, 'id'));
    }

    public function test_marca_las_ventas_de_alto_valor_contra_el_historico(): void
    {
        // Arrange: cinco ventas de $100.000 en el último mes → umbral $300.000
        foreach (range(1, 5) as $d) {
            $this->vender(1)->forceFill(['sold_at' => now()->subDays($d)])->save();
        }
        $normal = $this->vender(1);
        $grande = $this->vender(4);

        // Act
        $filas = collect($this->actingAs($this->supervisor)->getJson('/sales/monitor/datos')->json('nuevas'))->keyBy('id');

        // Assert
        $this->assertTrue($filas[$grande->id]['alto']);
        $this->assertFalse($filas[$normal->id]['alto']);
        $this->actingAs($this->supervisor)->get('/sales/monitor?estado=alto')
            ->assertSee($grande->number)->assertDontSee($normal->number);
        $this->actingAs($this->supervisor)->get("/sales/{$grande->id}")->assertOk()->assertSee('Silla');
    }

    public function test_las_anuladas_salen_con_quien_y_por_que_y_no_suman(): void
    {
        $this->vender(1);
        $anulada = $this->vender(2);
        $admin = $this->empleado('Administrador');
        $this->trabajando(fn () => app(SaleService::class)->anular($anulada, $admin->id, 'Cliente desistió'));

        $r = $this->actingAs($this->supervisor)->getJson('/sales/monitor/datos')->json('resumen');
        $this->assertEquals(100_000, $r['total']);
        $this->assertSame(1, $r['anuladas']);
        $this->assertEquals(200_000, $r['anulado_total']);

        $this->actingAs($this->supervisor)->get('/sales/monitor?estado=anuladas')
            ->assertOk()->assertSee($anulada->number)->assertSee('Cliente desistió')->assertSee($admin->name);
    }

    public function test_un_cajero_no_ve_el_monitor(): void
    {
        $this->actingAs($this->cajero)->get('/sales/monitor')->assertForbidden();
        $this->actingAs($this->cajero)->getJson('/sales/monitor/datos')->assertForbidden();
    }

    private function vender(int $cantidad, ?int $cliente = null): Sale
    {
        return $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->silla->id, 'quantity' => $cantidad]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 100_000 * $cantidad]],
            userId: $this->cajero->id,
            customerId: $cliente,
        ));
    }
}
