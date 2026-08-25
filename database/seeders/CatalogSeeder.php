<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Datos maestros: unidades, impuestos, medios de pago, atributos y el
 * árbol de categorías. Son las tablas de las que dependen los productos,
 * así que este seeder corre antes que ProductSeeder.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->unidades();
        $this->impuestos();
        $this->mediosDePago();
        $this->atributos();
        $this->categorias();
    }

    private function unidades(): void
    {
        $datos = [
            ['Unidad',      'und', 'Conteo'],
            ['Docena',      'dz',  'Conteo'],
            ['Par',         'par', 'Conteo'],
            ['Caja',        'caj', 'Empaque'],
            ['Paquete',     'paq', 'Empaque'],
            ['Resma',       'rma', 'Empaque'],
            ['Kilogramo',   'kg',  'Peso'],
            ['Gramo',       'g',   'Peso'],
            ['Libra',       'lb',  'Peso'],
            ['Litro',       'L',   'Volumen'],
            ['Mililitro',   'mL',  'Volumen'],
            ['Metro',       'm',   'Longitud'],
            ['Centímetro',  'cm',  'Longitud'],
        ];

        foreach ($datos as [$nombre, $simbolo, $tipo]) {
            Unit::updateOrCreate(
                ['symbol' => $simbolo],
                ['name' => $nombre, 'type' => $tipo]
            );
        }

        $this->command?->info('  Unidades: ' . count($datos));
    }

    private function impuestos(): void
    {
        $datos = [
            ['IVA general',       'Tarifa general del impuesto al valor agregado en Colombia.', 19.00, 'percentage', true],
            ['IVA reducido',      'Tarifa diferencial del 5% (algunos alimentos y textiles).',   5.00, 'percentage', true],
            ['Excluido de IVA',   'Bienes excluidos: no causan impuesto.',                       0.00, 'percentage', true],
            ['Impoconsumo',       'Impuesto nacional al consumo para expendio de comidas.',      8.00, 'percentage', true],
            ['Bolsa plástica',    'Impuesto fijo por unidad de bolsa entregada.',               66.00, 'fixed',      true],
            ['Exento',            'Tarifa 0% con derecho a devolución.',                         0.00, 'percentage', false],
        ];

        foreach ($datos as [$nombre, $desc, $tasa, $tipo, $activo]) {
            Tax::updateOrCreate(
                ['name' => $nombre],
                ['description' => $desc, 'rate' => $tasa, 'type' => $tipo, 'is_active' => $activo]
            );
        }

        $this->command?->info('  Impuestos: ' . count($datos));
    }

    private function mediosDePago(): void
    {
        $datos = [
            ['Efectivo',              'Pago en caja con billetes y monedas.',                 true],
            ['Tarjeta débito',        'Datáfono, débito a cuenta de ahorros o corriente.',    true],
            ['Tarjeta crédito',       'Datáfono, con opción de diferir a cuotas.',            true],
            ['Nequi',                 'Transferencia desde la billetera digital Nequi.',      true],
            ['Daviplata',             'Transferencia desde la billetera digital Daviplata.',  true],
            ['Transferencia bancaria','Consignación o transferencia a cuenta institucional.', true],
            ['PSE',                   'Débito en línea desde el banco del cliente.',          true],
            ['Crédito institucional', 'Cargo a la cuenta del estudiante o funcionario.',      false],
        ];

        foreach ($datos as [$nombre, $desc, $activo]) {
            PaymentMethod::updateOrCreate(
                ['name' => $nombre],
                ['description' => $desc, 'is_active' => $activo]
            );
        }

        $this->command?->info('  Medios de pago: ' . count($datos));
    }

    private function atributos(): void
    {
        $datos = [
            ['Color',        'lista',   true],
            ['Talla',        'lista',   true],
            ['Material',     'lista',   true],
            ['Presentación', 'lista',   true],
            ['Sabor',        'lista',   true],
            ['Capacidad',    'numero',  true],
            ['Peso neto',    'numero',  true],
            ['Importado',    'booleano', false],
        ];

        foreach ($datos as [$nombre, $tipo, $activo]) {
            Attribute::updateOrCreate(
                ['name' => $nombre],
                ['type' => $tipo, 'is_active' => $activo]
            );
        }

        $this->command?->info('  Atributos: ' . count($datos));
    }

    /**
     * Árbol de dos niveles: categoría raíz → subcategorías.
     */
    private function categorias(): void
    {
        $arbol = [
            'Papelería' => [
                'descripcion' => 'Todo lo que se usa para escribir, archivar y presentar trabajos.',
                'hijas' => ['Cuadernos', 'Escritura', 'Archivo y carpetas', 'Papel y resmas'],
            ],
            'Tecnología' => [
                'descripcion' => 'Accesorios electrónicos de uso académico.',
                'hijas' => ['Almacenamiento', 'Accesorios de computador', 'Calculadoras', 'Audio'],
            ],
            'Cafetería' => [
                'descripcion' => 'Alimentos y bebidas para consumo dentro del campus.',
                'hijas' => ['Bebidas frías', 'Bebidas calientes', 'Snacks', 'Panadería'],
            ],
            'Uniformes' => [
                'descripcion' => 'Prendas institucionales y de laboratorio.',
                'hijas' => ['Camisetas', 'Sudaderas', 'Batas de laboratorio'],
            ],
            'Dibujo técnico' => [
                'descripcion' => 'Instrumentos y soportes para dibujo y diseño.',
                'hijas' => ['Instrumentos', 'Papel especial'],
            ],
            'Aseo y cuidado' => [
                'descripcion' => 'Higiene personal y limpieza general.',
                'hijas' => ['Higiene personal', 'Limpieza'],
            ],
        ];

        $total = 0;

        foreach ($arbol as $nombre => $info) {
            $padre = Category::updateOrCreate(
                ['name' => $nombre, 'parent_id' => null],
                ['description' => $info['descripcion'], 'is_active' => true]
            );
            $total++;

            foreach ($info['hijas'] as $hija) {
                Category::updateOrCreate(
                    ['name' => $hija, 'parent_id' => $padre->id],
                    ['description' => $nombre . ' · ' . $hija, 'is_active' => true]
                );
                $total++;
            }
        }

        $this->command?->info('  Categorías: ' . $total);
    }
}
