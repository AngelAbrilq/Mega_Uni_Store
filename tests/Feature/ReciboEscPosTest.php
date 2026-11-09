<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Tienda;
use App\Models\User;
use App\Services\ReciboEscPos;
use App\Services\SaleService;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * RN-28 · Fase 2: recibo ESC/POS que el POS le pasa al agente de caja.
 *
 * Venta base: 2 «Lápiz HB» a $1.500 → total $3.000, pagado con $5.000.
 */
class ReciboEscPosTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $cajero;
    private Sale $venta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('recibo-escpos');
        $this->cajero = $this->empleado('Cajero');
        $producto     = $this->productoConExistencia('Lápiz HB', 1_500, 700, 50);

        $this->venta = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $producto->id, 'quantity' => 2]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 5_000]],
            userId: $this->cajero->id,
        ));
    }

    public function test_el_recibo_es_esc_pos_valido_y_trae_la_venta(): void
    {
        $bytes = $this->trabajando(fn () => app(ReciboEscPos::class)->generar($this->venta));

        $this->assertStringStartsWith("\x1B\x40", $bytes, 'Debe empezar con ESC @ (inicializar).');
        $this->assertStringContainsString("\x1D\x56", $bytes, 'Debe terminar con corte de papel (GS V).');
        $this->assertStringContainsString($this->venta->number, $bytes);
        $this->assertStringContainsString('Lapiz HB', $bytes, 'Las tildes se pasan a ASCII.');
        $this->assertStringContainsString('TOTAL', $bytes);
        $this->assertStringContainsString('Cambio', $bytes);
    }

    public function test_los_renglones_van_sin_impuesto_y_suman_el_subtotal(): void
    {
        $bytes = $this->trabajando(function () {
            $iva = \App\Models\Tax::create(['name' => 'IVA 19 %', 'rate' => 19, 'type' => 'percentage', 'is_active' => true]);
            $cuaderno = $this->productoConExistencia('Cuaderno', 7_800, 4_000, 10, $iva->id);
            $venta = app(SaleService::class)->registrar(
                lineas: [['product_id' => $cuaderno->id, 'quantity' => 1]],
                pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 20_000]],
                userId: $this->cajero->id,
            );

            return app(ReciboEscPos::class)->generar($venta);
        });

        // 7.800 + 19 % = 9.282: el renglón dice 7.800, el total 9.282.
        $this->assertMatchesRegularExpression('/1 x \$7\.800 +\$7\.800/', $bytes);
        $this->assertMatchesRegularExpression('/TOTAL +\$9\.282/', $bytes);
    }

    public function test_en_papel_de_58_ninguna_linea_pasa_de_32_columnas(): void
    {
        $bytes = $this->trabajando(function () {
            Setting::guardar(['recibo.formato' => '58']);

            return app(ReciboEscPos::class)->generar($this->venta);
        });

        // Se quitan los comandos ESC/GS para medir solo el texto impreso.
        $texto = preg_replace('/\x1B@|\x1B[aEd!].|\x1D!.|\x1DV[AB]?./s', '', $bytes);

        foreach (explode("\n", $texto) as $linea) {
            $this->assertLessThanOrEqual(32, mb_strlen($linea), "Línea demasiado larga: «{$linea}»");
        }
    }

    public function test_las_copias_repiten_el_recibo(): void
    {
        [$una, $dos] = $this->trabajando(function () {
            $una = app(ReciboEscPos::class)->generar($this->venta);
            Setting::guardar(['recibo.copias' => '2']);

            return [$una, app(ReciboEscPos::class)->generar($this->venta)];
        });

        $this->assertSame($una . $una, $dos);
    }

    public function test_el_endpoint_entrega_los_bytes_en_base64(): void
    {
        $respuesta = $this->actingAs($this->cajero)
            ->getJson("/sales/{$this->venta->id}/recibo/escpos")
            ->assertOk();

        $this->assertStringStartsWith("\x1B\x40", base64_decode($respuesta->json('datos'), true));
    }

    public function test_en_a4_el_endpoint_remite_al_navegador(): void
    {
        $this->trabajando(fn () => Setting::guardar(['recibo.formato' => 'a4']));

        $this->actingAs($this->cajero)
            ->getJson("/sales/{$this->venta->id}/recibo/escpos")
            ->assertStatus(422);
    }

    public function test_la_ficha_ofrece_la_termica_solo_con_agente(): void
    {
        $this->actingAs($this->cajero)->get("/sales/{$this->venta->id}")
            ->assertDontSee('id="btnTermica"', false);

        $this->tienda->update(['drawer_mode' => Tienda::CAJON_AGENTE]);

        $this->actingAs($this->cajero)->get("/sales/{$this->venta->id}")
            ->assertSee('id="btnTermica"', false)
            ->assertSee('127.0.0.1:9123', false);
    }

    public function test_no_se_genera_el_recibo_de_una_venta_de_otro_negocio(): void
    {
        $otra   = Empresa::create(['nombre' => 'Vecino', 'slug' => 'vecino-recibo', 'rubro' => 'general', 'estado' => 'activa']);
        $tienda = Tienda::create(['empresa_id' => $otra->id, 'nombre' => 'B', 'slug' => 'b-recibo', 'es_principal' => true, 'activa' => true]);

        $ajena = Contexto::comoEmpresa($otra->id, $tienda->id, function () use ($tienda) {
            $medio    = \App\Models\PaymentMethod::create(['name' => 'Efectivo', 'is_active' => true]);
            $producto = Product::create(['name' => 'Ajeno', 'price' => 100, 'cost' => 50, 'is_active' => true]);
            \App\Models\Existencia::create(['tienda_id' => $tienda->id, 'product_id' => $producto->id, 'stock' => 5, 'min_stock' => 0, 'precio' => 100, 'activo' => true]);

            return app(SaleService::class)->registrar(
                lineas: [['product_id' => $producto->id, 'quantity' => 1]],
                pagos: [['payment_method_id' => $medio->id, 'amount' => 100]],
                userId: $this->cajero->id,
            );
        });
        Contexto::olvidar();

        $this->actingAs($this->cajero)
            ->getJson("/sales/{$ajena->id}/recibo/escpos")
            ->assertNotFound();
    }
}
