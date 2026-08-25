<?php

namespace App\Support;

/**
 * Qué clase de negocio es, y con qué arranca.
 *
 * ── Para qué sirve esto ──
 *
 * Un sistema vacío no se puede probar. Quien entra a la demostración y ve
 * cero productos, cero clientes y cero ventas no está viendo el software:
 * está viendo un formulario en blanco, y se va antes de los cinco minutos.
 *
 * Aquí está lo que se le siembra a cada rubro para que en el segundo uno
 * ya pueda vender algo. No es relleno: son los productos que esa clase de
 * negocio maneja de verdad, con precios de Colombia. Un ferretero que ve
 * «Cemento gris 50 kg $32.000» entiende inmediatamente que esto es para
 * él; si viera «Producto de prueba 1», entendería lo contrario.
 *
 * ── Por qué los roles también cambian ──
 *
 * Porque el propio Angel lo dijo: una heladería, una ferretería y una
 * peluquería no tienen los mismos cargos. Sembrarle «Bodeguero» a una
 * peluquería es hacerle borrar cosas antes de empezar; sembrarle
 * «Estilista» es ahorrarle el trabajo de inventarlo.
 *
 * Los roles de aquí se crean como roles PROPIOS de esa empresa —con su
 * `empresa_id`— así que no le ensucian la lista a nadie más.
 */
class Rubros
{
    /**
     * @return array<string, array{
     *     nombre:string, descripcion:string, icono:string,
     *     roles:array<string, array<int,string>>,
     *     unidades:array<int, array{0:string,1:string}>,
     *     categorias:array<int,string>,
     *     productos:array<int, array{0:string,1:string,2:float,3:float,4:float,5:string}>
     * }>
     */
    public static function todos(): array
    {
        return [
            'ferreteria'    => self::ferreteria(),
            'supermercado'  => self::supermercado(),
            'peluqueria'    => self::peluqueria(),
            'heladeria'     => self::heladeria(),
            'ropa'          => self::ropa(),
            'general'       => self::general(),
        ];
    }

    public static function existe(string $slug): bool
    {
        return array_key_exists($slug, self::todos());
    }

    /** @return array<string, array{0:string,1:string}> slug => [nombre, descripción] */
    public static function paraEscoger(): array
    {
        $salida = [];

        foreach (self::todos() as $slug => $rubro) {
            $salida[$slug] = [$rubro['nombre'], $rubro['descripcion']];
        }

        return $salida;
    }

    public static function uno(string $slug): array
    {
        return self::todos()[$slug] ?? self::general();
    }

    /* ═══════════════ Los rubros ═══════════════ */

    private static function ferreteria(): array
    {
        return [
            'nombre'      => 'Ferretería',
            'descripcion' => 'Herramienta, material de construcción, eléctricos',
            'icono'       => 'box',
            'roles'       => [
                'Bodeguero de patio' => [
                    'panel.ver', 'inventario.ver', 'inventario.ajustar',
                    'productos.ver', 'compras.ver', 'compras.recibir', 'proveedores.ver',
                ],
                'Mostrador' => [
                    'panel.ver', 'ventas.ver', 'ventas.crear', 'ventas.devolver',
                    'caja.ver', 'caja.abrir', 'caja.cerrar',
                    'productos.ver', 'clientes.ver', 'clientes.crear',
                ],
            ],
            'unidades' => [
                ['Unidad', 'und'], ['Libra', 'lb'], ['Metro', 'm'],
                ['Bulto', 'bto'], ['Galón', 'gal'], ['Caja', 'cja'],
            ],
            'categorias' => [
                'Herramienta manual', 'Eléctricos', 'Plomería',
                'Construcción', 'Pinturas', 'Tornillería', 'Seguridad',
            ],
            // [nombre, categoría, precio, costo, stock, unidad]
            'productos' => [
                ['Martillo de uña 16 oz',            'Herramienta manual', 28_000,  18_000,  24, 'und'],
                ['Destornillador de estrella #2',    'Herramienta manual',  9_500,   5_800,  40, 'und'],
                ['Alicate universal 8"',             'Herramienta manual', 22_000,  14_000,  18, 'und'],
                ['Cinta métrica 5 m',                'Herramienta manual', 15_000,   9_000,  30, 'und'],
                ['Clavo de acero 2"',                'Tornillería',         4_000,   2_400, 120, 'lb'],
                ['Clavo de acero 5"',                'Tornillería',         5_000,   3_100,  80, 'lb'],
                ['Tornillo drywall 1" (caja 100)',   'Tornillería',        12_000,   7_500,  25, 'cja'],
                ['Cemento gris 50 kg',               'Construcción',       32_000,  26_500,  40, 'bto'],
                ['Arena de pega (bulto)',            'Construcción',       18_000,  13_000,  22, 'bto'],
                ['Cable encauchetado 2×14 (metro)',  'Eléctricos',          4_800,   3_100, 200, 'm'],
                ['Toma doble con polo a tierra',     'Eléctricos',          8_500,   5_200,  35, 'und'],
                ['Bombillo LED 12 W luz blanca',     'Eléctricos',          9_000,   5_500,  60, 'und'],
                ['Tubo PVC 1/2" × 6 m',              'Plomería',           14_000,   9_800,  28, 'und'],
                ['Llave terminal para lavamanos',    'Plomería',           19_000,  12_500,  15, 'und'],
                ['Pintura vinilo tipo 1 (galón)',    'Pinturas',           58_000,  42_000,  16, 'gal'],
                ['Brocha 3"',                        'Pinturas',            8_000,   4_600,  26, 'und'],
                ['Guantes de carnaza',               'Seguridad',          12_000,   7_000,  20, 'und'],
                ['Candado 40 mm',                    'Seguridad',          16_000,  10_000,  18, 'und'],
            ],
        ];
    }

    private static function supermercado(): array
    {
        return [
            'nombre'      => 'Supermercado o tienda',
            'descripcion' => 'Abarrotes, aseo, bebidas, fruver',
            'icono'       => 'cart',
            'roles'       => [
                'Cajero de turno' => [
                    'panel.ver', 'ventas.ver', 'ventas.crear',
                    'caja.ver', 'caja.abrir', 'caja.cerrar',
                    'productos.ver', 'clientes.ver',
                ],
                'Surtidor' => [
                    'panel.ver', 'inventario.ver', 'inventario.ajustar',
                    'productos.ver', 'productos.editar', 'compras.recibir',
                ],
            ],
            'unidades' => [
                ['Unidad', 'und'], ['Libra', 'lb'], ['Kilogramo', 'kg'],
                ['Litro', 'L'], ['Paquete', 'paq'], ['Docena', 'doc'],
            ],
            'categorias' => [
                'Abarrotes', 'Bebidas', 'Aseo del hogar', 'Cuidado personal',
                'Fruver', 'Lácteos', 'Panadería', 'Mecato',
            ],
            'productos' => [
                ['Arroz blanco 500 g',           'Abarrotes',        3_200,  2_400, 90, 'und'],
                ['Aceite girasol 1 L',           'Abarrotes',       12_500,  9_800, 45, 'und'],
                ['Panela redonda',               'Abarrotes',        4_500,  3_100, 60, 'und'],
                ['Fríjol cargamanto (libra)',    'Abarrotes',        7_800,  5_900, 40, 'lb'],
                ['Gaseosa 1.5 L',                'Bebidas',          5_500,  4_100, 70, 'und'],
                ['Agua en bolsa 360 ml',         'Bebidas',            800,    450, 200, 'und'],
                ['Jugo de caja 200 ml',          'Bebidas',          1_800,  1_150, 96, 'und'],
                ['Leche entera 1 L',             'Lácteos',          4_300,  3_400, 55, 'und'],
                ['Huevos AA (docena)',           'Lácteos',         14_000, 11_200, 30, 'doc'],
                ['Queso campesino (libra)',      'Lácteos',         13_500, 10_000, 18, 'lb'],
                ['Pan tajado grande',            'Panadería',        6_800,  5_100, 24, 'und'],
                ['Jabón en polvo 900 g',         'Aseo del hogar',   9_900,  7_200, 36, 'und'],
                ['Papel higiénico ×4',           'Aseo del hogar',   8_500,  6_300, 42, 'paq'],
                ['Crema dental 100 g',           'Cuidado personal', 7_200,  5_000, 30, 'und'],
                ['Plátano hartón (libra)',       'Fruver',           2_600,  1_700, 80, 'lb'],
                ['Tomate chonto (libra)',        'Fruver',           3_400,  2_200, 65, 'lb'],
                ['Papa pastusa (libra)',         'Fruver',           2_100,  1_400, 120, 'lb'],
                ['Papas fritas 45 g',            'Mecato',           2_500,  1_600, 88, 'und'],
            ],
        ];
    }

    private static function peluqueria(): array
    {
        return [
            'nombre'      => 'Peluquería o barbería',
            'descripcion' => 'Servicios de belleza y productos de cuidado',
            'icono'       => 'users',
            'roles'       => [
                'Estilista' => [
                    'panel.ver', 'ventas.ver', 'ventas.crear',
                    'clientes.ver', 'clientes.crear', 'clientes.editar',
                    'caja.ver', 'productos.ver',
                ],
                'Recepción' => [
                    'panel.ver', 'ventas.ver', 'ventas.crear',
                    'caja.ver', 'caja.abrir', 'caja.cerrar',
                    'clientes.ver', 'clientes.crear', 'productos.ver',
                ],
            ],
            'unidades' => [
                ['Servicio', 'serv'], ['Unidad', 'und'], ['Sesión', 'ses'],
            ],
            'categorias' => [
                'Corte y peinado', 'Color', 'Tratamientos',
                'Barbería', 'Manicure y pedicure', 'Productos',
            ],
            // Los servicios van con stock alto: no se agotan, pero así el
            // POS los maneja igual que un producto y no hay que explicar
            // dos flujos distintos en una demostración de dos horas.
            'productos' => [
                ['Corte de dama',                'Corte y peinado',      35_000, 0,     999, 'serv'],
                ['Corte de caballero',           'Corte y peinado',      22_000, 0,     999, 'serv'],
                ['Corte de niño',                'Corte y peinado',      18_000, 0,     999, 'serv'],
                ['Cepillado',                    'Corte y peinado',      30_000, 0,     999, 'serv'],
                ['Tinte raíz',                   'Color',                65_000, 22_000, 999, 'serv'],
                ['Mechas o balayage',            'Color',               180_000, 55_000, 999, 'serv'],
                ['Keratina',                     'Tratamientos',        150_000, 48_000, 999, 'serv'],
                ['Hidratación profunda',         'Tratamientos',         45_000, 12_000, 999, 'serv'],
                ['Arreglo de barba',             'Barbería',             15_000, 0,     999, 'serv'],
                ['Corte + barba',                'Barbería',             33_000, 0,     999, 'serv'],
                ['Manicure tradicional',         'Manicure y pedicure',  20_000, 4_000,  999, 'serv'],
                ['Pedicure con esmaltado',       'Manicure y pedicure',  28_000, 6_000,  999, 'serv'],
                ['Shampoo profesional 500 ml',   'Productos',            42_000, 27_000,  14, 'und'],
                ['Cera para peinar 100 g',       'Productos',            25_000, 15_000,  20, 'und'],
                ['Aceite para barba 30 ml',      'Productos',            32_000, 19_000,  12, 'und'],
            ],
        ];
    }

    private static function heladeria(): array
    {
        return [
            'nombre'      => 'Heladería o cafetería',
            'descripcion' => 'Helados, bebidas y postres para llevar',
            'icono'       => 'money',
            'roles'       => [
                'Atención en barra' => [
                    'panel.ver', 'ventas.ver', 'ventas.crear',
                    'caja.ver', 'caja.abrir', 'caja.cerrar', 'productos.ver',
                ],
            ],
            'unidades' => [
                ['Unidad', 'und'], ['Bola', 'bola'], ['Vaso', 'vso'], ['Litro', 'L'],
            ],
            'categorias' => [
                'Helados', 'Bebidas frías', 'Bebidas calientes', 'Postres', 'Adiciones',
            ],
            'productos' => [
                ['Helado 1 bola',            'Helados',           5_000,  1_800, 999, 'bola'],
                ['Helado 2 bolas',           'Helados',           9_000,  3_400, 999, 'bola'],
                ['Cono sencillo',            'Helados',           6_500,  2_300, 999, 'und'],
                ['Malteada de vainilla',     'Bebidas frías',    12_000,  4_500, 999, 'vso'],
                ['Malteada de chocolate',    'Bebidas frías',    12_000,  4_600, 999, 'vso'],
                ['Jugo natural en agua',     'Bebidas frías',     7_000,  2_400, 999, 'vso'],
                ['Limonada de coco',         'Bebidas frías',    11_000,  3_900, 999, 'vso'],
                ['Café americano',           'Bebidas calientes', 3_500,  1_100, 999, 'vso'],
                ['Capuchino',                'Bebidas calientes', 6_500,  2_200, 999, 'vso'],
                ['Chocolate con queso',      'Bebidas calientes', 7_500,  2_800, 999, 'vso'],
                ['Brownie con helado',       'Postres',          14_000,  5_200, 999, 'und'],
                ['Torta de zanahoria',       'Postres',           9_000,  3_400,  20, 'und'],
                ['Copa de frutas',           'Postres',          13_000,  4_800, 999, 'und'],
                ['Salsa extra',              'Adiciones',         1_500,    400, 999, 'und'],
                ['Bola adicional',           'Adiciones',         4_000,  1_500, 999, 'bola'],
            ],
        ];
    }

    private static function ropa(): array
    {
        return [
            'nombre'      => 'Almacén de ropa u hogar',
            'descripcion' => 'Prendas, calzado, textiles y artículos del hogar',
            'icono'       => 'tag',
            'roles'       => [
                'Asesor de venta' => [
                    'panel.ver', 'ventas.ver', 'ventas.crear', 'ventas.devolver',
                    'clientes.ver', 'clientes.crear', 'productos.ver', 'caja.ver',
                ],
                'Bodega' => [
                    'panel.ver', 'inventario.ver', 'inventario.ajustar',
                    'productos.ver', 'compras.recibir',
                ],
            ],
            'unidades' => [
                ['Unidad', 'und'], ['Par', 'par'], ['Juego', 'jgo'], ['Metro', 'm'],
            ],
            'categorias' => [
                'Dama', 'Caballero', 'Niños', 'Calzado', 'Ropa de cama', 'Baño', 'Sonido',
            ],
            'productos' => [
                ['Blusa manga corta',           'Dama',          45_000, 26_000, 22, 'und'],
                ['Jean clásico dama',           'Dama',          89_000, 52_000, 18, 'und'],
                ['Vestido casual',              'Dama',          98_000, 58_000, 12, 'und'],
                ['Camisa manga larga',          'Caballero',     72_000, 42_000, 20, 'und'],
                ['Pantalón dril',               'Caballero',     85_000, 50_000, 16, 'und'],
                ['Camiseta básica',             'Caballero',     32_000, 17_000, 40, 'und'],
                ['Conjunto niño 2 piezas',      'Niños',         55_000, 31_000, 15, 'jgo'],
                ['Pijama niña',                 'Niños',         38_000, 21_000, 18, 'und'],
                ['Tenis deportivo',             'Calzado',      145_000, 88_000, 14, 'par'],
                ['Sandalia dama',               'Calzado',       62_000, 35_000, 16, 'par'],
                ['Juego de sábanas doble',      'Ropa de cama', 120_000, 72_000, 12, 'jgo'],
                ['Cobija térmica queen',        'Ropa de cama', 165_000, 98_000,  9, 'und'],
                ['Almohada de fibra',           'Ropa de cama',  35_000, 18_000, 24, 'und'],
                ['Toalla de cuerpo',            'Baño',          38_000, 21_000, 30, 'und'],
                ['Juego de toallas ×3',         'Baño',          85_000, 49_000, 14, 'jgo'],
                ['Bafle amplificado 12"',       'Sonido',       480_000, 330_000, 6, 'und'],
                ['Cabina de sonido portátil',   'Sonido',       320_000, 215_000, 8, 'und'],
            ],
        ];
    }

    private static function general(): array
    {
        return [
            'nombre'      => 'Otro tipo de negocio',
            'descripcion' => 'Arranca con un catálogo básico y lo armas tú',
            'icono'       => 'grid',
            'roles'       => [],
            'unidades'    => [['Unidad', 'und'], ['Libra', 'lb'], ['Metro', 'm'], ['Servicio', 'serv']],
            'categorias'  => ['General', 'Servicios'],
            'productos'   => [
                ['Producto de ejemplo A', 'General',   15_000,  9_000, 30, 'und'],
                ['Producto de ejemplo B', 'General',   28_000, 17_000, 20, 'und'],
                ['Producto de ejemplo C', 'General',    7_500,  4_200, 50, 'und'],
                ['Servicio de ejemplo',   'Servicios', 40_000,      0, 999, 'serv'],
            ],
        ];
    }
}
