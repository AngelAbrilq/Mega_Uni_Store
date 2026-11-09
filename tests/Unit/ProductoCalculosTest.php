<?php

namespace Tests\Unit;

use App\Models\Product;
use Tests\TestCase;

/**
 * Los cálculos del modelo no tocan la base de datos, así que se pueden
 * probar sin arrancar la aplicación.
 */
class ProductoCalculosTest extends TestCase
{
    private function producto(float $precio, float $costo, int $stock = 0, int $minimo = 0): Product
    {
        $p = new Product();
        $p->price     = $precio;
        $p->cost      = $costo;
        $p->stock     = $stock;
        $p->min_stock = $minimo;

        return $p;
    }

    public function test_la_ganancia_es_precio_menos_costo(): void
    {
        $this->assertSame(2600.0, $this->producto(7800, 5200)->profit);
    }

    public function test_el_margen_se_calcula_sobre_el_precio_de_venta(): void
    {
        $this->assertEqualsWithDelta(33.33, $this->producto(7800, 5200)->margin, 0.01);
    }

    public function test_un_precio_en_cero_no_revienta_el_margen(): void
    {
        $this->assertSame(0.0, $this->producto(0, 0)->margin);
    }

    public function test_el_valor_en_bodega_es_costo_por_existencias(): void
    {
        $this->assertSame(936000.0, $this->producto(7800, 5200, 180)->stock_value);
    }

    public function test_el_estado_del_stock(): void
    {
        $this->assertSame('agotado', $this->producto(100, 50, 0, 10)->stock_state);
        $this->assertSame('bajo',    $this->producto(100, 50, 10, 10)->stock_state);
        $this->assertSame('bajo',    $this->producto(100, 50, 4, 10)->stock_state);
        $this->assertSame('ok',      $this->producto(100, 50, 40, 10)->stock_state);
    }
}
