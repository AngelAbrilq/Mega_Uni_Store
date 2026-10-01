<?php

namespace Tests\Unit;

use App\Services\InventoryMathService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InventoryMathServiceTest extends TestCase
{
    private InventoryMathService $math;

    protected function setUp(): void
    {
        parent::setUp();
        $this->math = new InventoryMathService(0.01);
    }

    #[Test]
    public function calcula_el_ejemplo_de_referencia_caja_de_20_con_10_unidades_vendida_en_30(): void
    {
        // Arrange / Act
        $r = $this->math->calculate(boxes: 1, unitsPerBox: 10, totalCost: 20, boxSalePrice: 30);

        // Assert
        $this->assertSame(10.0, $r['unidades_totales']);
        $this->assertSame(2.0, $r['costo_unitario']);
        $this->assertSame(3.0, $r['precio_venta_unitario']);
        $this->assertSame(10.0, $r['margen_ganancia']['ganancia_neta_caja']);
        $this->assertSame(1.0, $r['margen_ganancia']['ganancia_unitaria']);
        $this->assertSame(33.33, $r['margen_ganancia']['margen_porcentaje']);
        $this->assertSame(50.0, $r['margen_ganancia']['markup_porcentaje']);
    }

    #[Test]
    public function varias_cajas_multiplican_unidades_y_totales(): void
    {
        $r = $this->math->calculate(3, 12, 72000, unitSalePrice: 2500);

        $this->assertSame(36.0, $r['unidades_totales']);
        $this->assertSame(24000.0, $r['costo_caja']);
        $this->assertSame(2000.0, $r['costo_unitario']);
        $this->assertSame(90000.0, $r['precio_venta_total']);
        $this->assertSame(18000.0, $r['margen_ganancia']['ganancia_total']);
        $this->assertSame(6000.0, $r['margen_ganancia']['ganancia_neta_caja']);
    }

    #[Test]
    public function informa_el_redondeo_cuando_el_costo_no_divide_exacto(): void
    {
        $r = $this->math->calculate(1, 3, 20);

        $this->assertSame(6.67, $r['costo_unitario']);
        $this->assertSame(0.01, $r['ajuste_redondeo_costo']);
        $this->assertContains('REDONDEO_COSTO_UNITARIO', $r['alertas']);
        $this->assertContains('SIN_PRECIO_VENTA', $r['alertas']);
        $this->assertNull($r['margen_ganancia']['ganancia_total']);
    }

    #[Test]
    public function marca_venta_bajo_costo(): void
    {
        $r = $this->math->calculate(1, 10, 20, unitSalePrice: 1.5);

        $this->assertContains('VENTA_BAJO_COSTO', $r['alertas']);
        $this->assertSame(-5.0, $r['margen_ganancia']['ganancia_neta_caja']);
    }

    #[Test]
    public function detecta_la_suma_erronea_de_la_ia(): void
    {
        $computed = $this->math->calculate(1, 10, 20, unitSalePrice: 3);
        $ai = ['unidades_totales' => 10, 'costo_unitario' => 2.5, 'margen_ganancia' => ['ganancia_neta_caja' => 10]];

        $diff = $this->math->discrepancies($ai, $computed);

        $this->assertCount(1, $diff);
        $this->assertSame('costo_unitario', $diff[0]['campo']);
        $this->assertSame(2.0, $diff[0]['valor_calculado']);
    }

    #[Test]
    public function rechaza_cajas_en_cero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->math->calculate(0, 10, 20);
    }

    #[Test]
    public function cuadra_la_factura_con_descuento_e_iva(): void
    {
        $ok  = $this->math->reconcileInvoice([20000, 30000], ['subtotal' => 55000, 'descuento' => 5000, 'iva' => 9500, 'total' => 59500]);
        $bad = $this->math->reconcileInvoice([20000], ['subtotal' => 50000, 'descuento' => 0, 'iva' => 0, 'total' => 50000]);

        $this->assertTrue($ok['cuadra']);
        $this->assertFalse($bad['cuadra']);
        $this->assertSame(30000.0, $bad['diferencia']);
    }

    #[Test]
    public function sugiere_precio_por_markup(): void
    {
        $this->assertSame(2.6, $this->math->suggestUnitPrice(2, 30));
    }
}
