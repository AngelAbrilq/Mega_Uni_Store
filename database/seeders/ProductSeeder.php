<?php

namespace Database\Seeders;

use App\Models\Existencia;
use App\Support\Contexto;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Catálogo de productos de la tienda universitaria.
 *
 * Cada fila es:
 *   [subcategoría, nombre, símbolo de unidad, precio, costo, stock, stock mínimo, impuesto, proveedor]
 *
 * Las fechas de creación se reparten sobre los últimos seis meses para que
 * las gráficas del panel muestren una tendencia real y no un solo pico.
 */
class ProductSeeder extends Seeder
{
    /** Prefijo de SKU por categoría raíz. */
    private array $prefijos = [
        'Papelería'      => 'PAP',
        'Tecnología'     => 'TEC',
        'Cafetería'      => 'CAF',
        'Uniformes'      => 'UNI',
        'Dibujo técnico' => 'DIB',
        'Aseo y cuidado' => 'ASE',
    ];

    /**
     * @return array<int, array{0:string,1:string,2:string,3:int,4:int,5:int,6:int,7:string,8:string}>
     */
    private function catalogo(): array
    {
        $papelera = 'Distribuidora Papelera del Norte S.A.S.';
        $tecno    = 'Importaciones TecnoAndina Ltda.';
        $cafe     = 'Café de la Sierra S.A.S.';
        $textil   = 'Textiles Institucionales Bogotá';
        $aseo     = 'Suministros de Aseo Limpiamax';
        $escribe  = 'Comercializadora Escribe Colombia';
        $snacks   = 'Alimentos y Snacks del Valle';
        $pan      = 'Panadería La Espiga Dorada';
        $dibujar  = 'Insumos Gráficos Dibujar S.A.S.';

        $iva  = 'IVA general';
        $iva5 = 'IVA reducido';
        $inc  = 'Impoconsumo';
        $exc  = 'Excluido de IVA';

        return [
            // ─────────── Papelería ───────────
            ['Cuadernos', 'Cuaderno cuadriculado 100 hojas',        'und',  7800,   5200, 180, 40, $iva,  $papelera],
            ['Cuadernos', 'Cuaderno rayado 100 hojas',              'und',  7500,   5000, 165, 40, $iva,  $papelera],
            ['Cuadernos', 'Cuaderno argollado 5 materias 200 hojas','und', 18900,  12600,  62, 20, $iva,  $papelera],
            ['Cuadernos', 'Cuaderno cosido doble línea 50 hojas',   'und',  4200,   2700, 210, 50, $iva,  $papelera],
            ['Cuadernos', 'Block iris carta 40 hojas',              'und',  9500,   6300,  48, 15, $iva,  $papelera],

            ['Escritura', 'Bolígrafo negro punta media',            'caj', 14400,   9600,  95, 25, $iva,  $escribe],
            ['Escritura', 'Bolígrafo azul punta media',             'caj', 14400,   9600,  88, 25, $iva,  $escribe],
            ['Escritura', 'Lápiz negro HB',                         'caj',  8900,   5500, 120, 30, $iva,  $escribe],
            ['Escritura', 'Marcador permanente negro',              'und',  3800,   2300, 140, 40, $iva,  $escribe],
            ['Escritura', 'Resaltador amarillo fluorescente',       'und',  3200,   1900, 205, 50, $iva,  $escribe],
            ['Escritura', 'Corrector líquido 20 mL',                'und',  4500,   2800,  18, 25, $iva,  $escribe],
            ['Escritura', 'Borrador de nata grande',                'und',  1200,    600, 310, 60, $iva,  $escribe],
            ['Escritura', 'Tajalápiz metálico',                     'und',  1800,    950, 145, 40, $iva,  $escribe],

            ['Archivo y carpetas', 'Carpeta plástica oficio con gancho', 'und',  2500,  1400, 260, 60, $iva, $papelera],
            ['Archivo y carpetas', 'Legajador AZ oficio lomo ancho',     'und', 12500,  8200,  40, 15, $iva, $papelera],
            ['Archivo y carpetas', 'Sobre manila oficio x50',            'paq', 15900, 10500,  26, 10, $iva, $papelera],
            ['Archivo y carpetas', 'Legajador de cartón oficio',         'und',   900,   450, 420, 80, $iva, $papelera],
            ['Archivo y carpetas', 'Separadores plásticos x10',          'paq',  5400,  3200,   0, 20, $iva, $papelera],

            ['Papel y resmas', 'Resma papel carta 75 g x500',   'rma', 21900, 16500,  54, 20, $iva, $papelera],
            ['Papel y resmas', 'Resma papel oficio 75 g x500',  'rma', 25900, 19500,  37, 15, $iva, $papelera],
            ['Papel y resmas', 'Papel fotográfico A4 x20',      'paq', 18500, 12000,  22,  8, $iva, $papelera],
            ['Papel y resmas', 'Papel bond pliego',             'und',   700,   350, 500, 100, $iva, $papelera],

            // ─────────── Tecnología ───────────
            ['Almacenamiento', 'Memoria USB 64 GB',              'und',  42000,  29000,  46, 12, $iva, $tecno],
            ['Almacenamiento', 'Memoria USB 128 GB',             'und',  68000,  48000,  28,  8, $iva, $tecno],
            ['Almacenamiento', 'Memoria micro SD 128 GB clase 10','und', 79900,  56000,  19,  8, $iva, $tecno],
            ['Almacenamiento', 'Disco duro externo 1 TB',        'und', 249900, 195000,   6,  4, $iva, $tecno],

            ['Accesorios de computador', 'Mouse óptico USB',              'und',  24900,  15500,  64, 15, $iva, $tecno],
            ['Accesorios de computador', 'Teclado USB distribución español','und',39900,  27000,  33, 10, $iva, $tecno],
            ['Accesorios de computador', 'Diadema con micrófono',         'und',  54900,  38000,  21,  8, $iva, $tecno],
            ['Accesorios de computador', 'Base refrigerante para portátil','und', 65000,  44000,   9,  5, $iva, $tecno],
            ['Accesorios de computador', 'Cable HDMI 1.5 m',              'und',  22900,  13500,  47, 12, $iva, $tecno],
            ['Accesorios de computador', 'Adaptador USB-C a HDMI',        'und',  48900,  33000,   3,  6, $iva, $tecno],

            ['Calculadoras', 'Calculadora científica 240 funciones', 'und',  78900,  56000, 41, 12, $iva, $tecno],
            ['Calculadoras', 'Calculadora básica 12 dígitos',        'und',  24900,  15000, 58, 15, $iva, $tecno],
            ['Calculadoras', 'Calculadora graficadora',              'und', 389000, 310000,  4,  3, $iva, $tecno],

            ['Audio', 'Audífonos in-ear 3.5 mm',      'und', 18900, 11000, 76, 20, $iva, $tecno],
            ['Audio', 'Parlante bluetooth portátil',  'und', 89900, 62000, 14,  6, $iva, $tecno],

            // ─────────── Cafetería ───────────
            ['Bebidas frías', 'Agua en botella 600 mL',   'und', 2500, 1400, 240, 60, $exc,  $snacks],
            ['Bebidas frías', 'Gaseosa en lata 330 mL',   'und', 3500, 2200, 186, 48, $iva,  $snacks],
            ['Bebidas frías', 'Jugo natural 500 mL',      'und', 4500, 2800,  92, 30, $iva5, $snacks],
            ['Bebidas frías', 'Té helado 400 mL',         'und', 3800, 2300, 110, 30, $iva,  $snacks],

            ['Bebidas calientes', 'Café americano 8 oz',     'und', 3000, 1200, 999, 100, $inc, $cafe],
            ['Bebidas calientes', 'Café con leche 12 oz',    'und', 4500, 1900, 999, 100, $inc, $cafe],
            ['Bebidas calientes', 'Chocolate caliente 12 oz','und', 5000, 2100, 999, 100, $inc, $cafe],
            ['Bebidas calientes', 'Aromática de frutas',     'und', 2500,  900, 999, 100, $inc, $cafe],

            ['Snacks', 'Papas fritas 45 g',       'und', 3200, 2000, 168, 45, $iva,  $snacks],
            ['Snacks', 'Galleta wafer 40 g',      'und', 2000, 1200, 224, 60, $iva,  $snacks],
            ['Snacks', 'Barra de cereal 30 g',    'und', 2800, 1700,  36, 40, $iva5, $snacks],
            ['Snacks', 'Maní salado 50 g',        'und', 3500, 2200,  98, 30, $iva,  $snacks],
            ['Snacks', 'Chocolatina 35 g',        'und', 2800, 1750, 152, 45, $iva,  $snacks],

            ['Panadería', 'Pan de queso',                  'und', 2500, 1200, 60, 24, $inc, $pan],
            ['Panadería', 'Croissant de jamón y queso',    'und', 5500, 3000, 34, 18, $inc, $pan],
            ['Panadería', 'Empanada de carne',             'und', 3000, 1500, 72, 30, $inc, $pan],
            ['Panadería', 'Sándwich mixto',                'und', 8500, 5000, 26, 15, $inc, $pan],

            // ─────────── Uniformes ───────────
            ['Camisetas', 'Camiseta institucional talla S', 'und', 35000, 22000, 24, 10, $iva5, $textil],
            ['Camisetas', 'Camiseta institucional talla M', 'und', 35000, 22000, 41, 15, $iva5, $textil],
            ['Camisetas', 'Camiseta institucional talla L', 'und', 35000, 22000, 33, 15, $iva5, $textil],
            ['Sudaderas', 'Sudadera institucional talla M', 'und', 89000, 62000, 12,  6, $iva5, $textil],
            ['Sudaderas', 'Chaqueta institucional talla L', 'und',125000, 89000,  5,  5, $iva5, $textil],
            ['Batas de laboratorio', 'Bata de laboratorio blanca talla M', 'und', 55000, 36000, 18, 8, $iva5, $textil],
            ['Batas de laboratorio', 'Bata de laboratorio blanca talla L', 'und', 55000, 36000,  7, 8, $iva5, $textil],

            // ─────────── Dibujo técnico ───────────
            ['Instrumentos', 'Juego de escuadras 30 cm',      'und', 12900,  8000, 44, 12, $iva, $dibujar],
            ['Instrumentos', 'Compás de precisión metálico',  'und', 18900, 12000, 29, 10, $iva, $dibujar],
            ['Instrumentos', 'Regla metálica 50 cm',          'und',  9500,  5800, 51, 15, $iva, $dibujar],
            ['Instrumentos', 'Escalímetro triangular 30 cm',  'und', 24900, 16000, 16,  8, $iva, $dibujar],
            ['Papel especial', 'Pliego de papel mantequilla', 'und',  1200,   600,380, 80, $iva, $dibujar],
            ['Papel especial', 'Block papel durex 1/8 x20',   'paq',  8900,  5500, 23, 10, $iva, $dibujar],

            // ─────────── Aseo y cuidado ───────────
            ['Higiene personal', 'Jabón líquido de manos 1 L', 'und', 9600, 6200, 58, 20, $iva, $aseo],
            ['Higiene personal', 'Gel antibacterial 500 mL',   'und', 8500, 5300, 74, 25, $iva, $aseo],
            ['Higiene personal', 'Toallas de papel x150',      'paq', 7900, 5000, 31, 15, $iva, $aseo],
            ['Limpieza', 'Limpiador multiusos 1 L',            'und', 6900, 4200, 42, 15, $iva, $aseo],
            ['Limpieza', 'Bolsa de basura x10',                'paq', 4500, 2800,  0, 20, $iva, $aseo],
        ];
    }

    public function run(): void
    {
        $categorias = Category::query()->with('parent')->get()->keyBy('name');
        $unidades   = Unit::query()->get()->keyBy('symbol');
        $impuestos  = Tax::query()->get()->keyBy('name');
        $proveedores= Supplier::query()->get()->keyBy('name');

        $autor = User::query()->orderBy('id')->first()?->id;

        $filas   = $this->catalogo();
        $total   = count($filas);
        $consec  = [];
        $creados = 0;

        foreach ($filas as $i => [$subcat, $nombre, $simbolo, $precio, $costo, $stock, $minimo, $impuesto, $proveedor]) {
            $categoria = $categorias->get($subcat);
            $raiz      = $categoria?->parent?->name ?? 'Papelería';
            $prefijo   = $this->prefijos[$raiz] ?? 'GEN';

            $consec[$prefijo] = ($consec[$prefijo] ?? 0) + 1;
            $sku = sprintf('%s-%04d', $prefijo, $consec[$prefijo]);

            // Código de barras EAN-13 ficticio con prefijo de país 770 (Colombia).
            $codigo = '770' . str_pad((string) (1000000 + $i * 137), 10, '0', STR_PAD_LEFT);

            $producto = Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'name'        => $nombre,
                    'description' => $this->descripcion($nombre, $subcat, $raiz),
                    'barcode'     => $codigo,
                    'category_id' => $categoria?->id,
                    'unit_id'     => $unidades->get($simbolo)?->id,
                    'tax_id'      => $impuestos->get($impuesto)?->id,
                    'supplier_id' => $proveedores->get($proveedor)?->id,
                    'price'       => $precio,
                    'cost'        => $costo,
                    'is_active'   => $stock > 0 || $minimo === 0,
                    // Los datos de demostración salen publicados para que la
                    // tienda pública tenga qué mostrar desde el primer día.
                    // En producción `is_public` nace en false a propósito.
                    'is_public'   => true,
                    'created_by'  => $autor,
                ]
            );

            // El stock ya no vive en el producto: vive en la fila del local.
            // Se siembra en la tienda principal, que es donde arranca todo.
            Existencia::updateOrCreate(
                ['tienda_id' => Contexto::tiendaId(), 'product_id' => $producto->id],
                ['stock' => $stock, 'min_stock' => $minimo, 'activo' => true]
            );

            $this->fechar($producto, $i, $total);
            $creados++;
        }

        $this->command?->info('  Productos: ' . $creados);
    }

    /**
     * Reparte las fechas de creación sobre los últimos 180 días con una
     * curva creciente: pocos productos al principio, más en las semanas
     * recientes. Así el panel muestra una tendencia y no una línea plana.
     */
    private function fechar(Product $producto, int $indice, int $total): void
    {
        $t = $total > 1 ? $indice / ($total - 1) : 1.0;   // 0 → 1
        $peso = 1 - pow(1 - $t, 2);                        // curva suave
        $dias = (int) round(178 - $peso * 178);            // 178 → 0 días atrás

        $fecha = Carbon::now()
            ->subDays($dias)
            ->setTime(8 + ($indice % 10), ($indice * 7) % 60, 0);

        $producto->timestamps = false;
        $producto->created_at = $fecha;
        $producto->updated_at = $fecha;
        $producto->save();
        $producto->timestamps = true;
    }

    private function descripcion(string $nombre, string $subcat, string $raiz): string
    {
        return sprintf(
            '%s. Pertenece a %s › %s. Producto de rotación habitual en la tienda universitaria.',
            $nombre,
            $raiz,
            $subcat
        );
    }
}
