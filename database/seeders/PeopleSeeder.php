<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Terceros: proveedores a los que se les compra y clientes a los que se
 * les vende. Los datos son ficticios pero con la forma real de Colombia
 * (NIT con dígito de verificación, cédulas de 10 dígitos, celulares 3xx).
 */
class PeopleSeeder extends Seeder
{
    public function run(): void
    {
        $this->proveedores();
        $this->clientes();
    }

    private function proveedores(): void
    {
        $datos = [
            ['Distribuidora Papelera del Norte S.A.S.', '900123456-7', '6015550101', 'ventas@papeleranorte.co',   'Calle 68 # 24-15, Bogotá',        'Marcela Ospina',   true],
            ['Importaciones TecnoAndina Ltda.',         '830456789-1', '6014448822', 'compras@tecnoandina.co',    'Carrera 13 # 82-40, Bogotá',      'Julián Restrepo',  true],
            ['Café de la Sierra S.A.S.',                '901222333-4', '6046012233', 'pedidos@cafesierra.com.co', 'Vereda La Cumbre, Manizales',     'Diana Zapata',     true],
            ['Textiles Institucionales Bogotá',         '860998877-2', '6013317744', 'contacto@textilesib.co',    'Calle 13 # 32-08, Bogotá',        'Hernán Castillo',  true],
            ['Suministros de Aseo Limpiamax',           '901777888-5', '6027889900', 'servicio@limpiamax.co',     'Autopista Sur # 45-90, Cali',     'Paola Aguirre',    true],
            ['Comercializadora Escribe Colombia',       '811334455-6', '6042235566', 'b2b@escribecol.co',         'Carrera 48 # 20-114, Medellín',   'Andrés Villa',     true],
            ['Alimentos y Snacks del Valle',           '900555111-3', '6025554433', 'ventas@snacksvalle.co',     'Calle 25 Norte # 6-30, Cali',     'Luz Marina Cruz',  true],
            ['Panadería La Espiga Dorada',             '1032567890',  '3145558877', 'laespiga@correo.com',       'Calle 45 Sur # 12-20, Bogotá',    'Óscar Beltrán',    true],
            ['Insumos Gráficos Dibujar S.A.S.',        '901889900-8', '6013456677', 'info@dibujarsas.co',        'Carrera 30 # 8-45, Bogotá',       'Natalia Guerrero', true],
            ['Electrónicos Global Import',             '830112233-9', '6017778899', 'import@globalelec.co',      'Zona Franca, Bogotá',             'Kevin Moreno',     false],
        ];

        foreach ($datos as [$nombre, $nit, $tel, $mail, $dir, $contacto, $activo]) {
            Supplier::updateOrCreate(
                ['name' => $nombre],
                [
                    'tax_id'       => $nit,
                    'phone'        => $tel,
                    'email'        => $mail,
                    'address'      => $dir,
                    'contact_name' => $contacto,
                    'is_active'    => $activo,
                ]
            );
        }

        $this->command?->info('  Proveedores: ' . count($datos));
    }

    private function clientes(): void
    {
        $datos = [
            ['María Fernanda',  'Ruiz Gómez',      'CC',  '1023456789', 'mfruiz@correo.com',        '3105551122'],
            ['Carlos Andrés',   'Peña Molina',     'CC',  '1098765432', 'capena@correo.com',        '3125557788'],
            ['Laura Sofía',     'Gómez Herrera',   'TI',  '1099887766', 'lgomez@correo.com',        '3164443311'],
            ['Juan David',      'Torres Ríos',     'CC',  '1122334455', null,                       '3009998877'],
            ['Distribuidora',   'El Sol S.A.S.',   'NIT', '900123457-1','ventas@elsol.co',          '6013334455'],
            ['Ana Lucía',       'Martínez Sanz',   'CC',  '1015678234', 'analu.martinez@correo.com','3187772211'],
            ['Sebastián',       'Cárdenas Uribe',  'CC',  '1032987654', 'scardenas@correo.com',     '3112223344'],
            ['Valentina',       'Ospina Duque',    'TI',  '1045123987', 'vospina@correo.com',       '3204445566'],
            ['Andrés Felipe',   'Quintero Lara',   'CC',  '1077889900', null,                       '3156667788'],
            ['Camila',          'Rodríguez Nieto', 'CC',  '1067452310', 'crodriguez@correo.com',    '3138889900'],
            ['Instituto',       'Técnico Central', 'NIT', '860011223-4','compras@itc.edu.co',       '6012223344'],
            ['Diego Alejandro', 'Salazar Pinto',   'CC',  '1088776655', 'dsalazar@correo.com',      '3175554433'],
            ['Paula Andrea',    'Vargas Cortés',   'CC',  '1054321098', 'pvargas@correo.com',       '3196662211'],
            ['Santiago',        'Mejía Rincón',    'TI',  '1002345678', null,                       '3143331199'],
            ['Isabella',        'Franco Muñoz',    'CC',  '1093456712', 'ifranco@correo.com',       '3169998844'],
            ['Ricardo',         'Benavides Soto',  'CE',  'E1234567',   'rbenavides@correo.com',    '3018887766'],
            ['Natalia',         'Cifuentes Rojas', 'CC',  '1019283746', 'ncifuentes@correo.com',    '3122229988'],
            ['Miguel Ángel',    'Guerrero Díaz',   'CC',  '1074839201', null,                       '3134447755'],
            ['Fundación',       'Aprender Juntos', 'NIT', '901445566-3','admin@aprenderjuntos.org', '6015556677'],
            ['Sara',            'Londoño Pérez',   'CC',  '1039876540', 'slondono@correo.com',      '3208886644'],
            ['Julián',          'Ramírez Acosta',  'CC',  '1085647382', 'jramirez@correo.com',      '3151114422'],
            ['Daniela',         'Castaño Moreno',  'TI',  '1006574839', null,                       '3117773366'],
            ['Felipe',          'Arango Bermúdez', 'CC',  '1027384950', 'farango@correo.com',       '3184442277'],
            ['Mariana',         'Silva Contreras', 'CC',  '1061728394', 'msilva@correo.com',        '3193338811'],
            ['Óscar Iván',      'Pineda Gallego',  'CC',  '1049586372', null,                       '3025556699'],
            ['Luisa Fernanda',  'Correa Naranjo',  'CC',  '1013243546', 'lcorrea@correo.com',       '3167778855'],
            ['Tomás',           'Bedoya Escobar',  'TI',  '1008172635', null,                       '3129994433'],
            ['Carolina',        'Jaramillo Ruiz',  'CC',  '1092837465', 'cjaramillo@correo.com',    '3146665511'],
            ['Esteban',         'Higuita Zapata',  'CC',  '1076543219', 'ehiguita@correo.com',      '3172221177'],
            ['Papelería',       'La Esquina',      'NIT', '901667788-2','laesquina@correo.com',     '6018889911'],
        ];

        $direcciones = [
            'Calle 45 # 12-30, Bogotá', 'Carrera 7 # 80-15, Bogotá', 'Calle 10 Sur # 22-40, Medellín',
            'Carrera 66 # 14-25, Cali', 'Calle 72 # 9-55, Bogotá', 'Carrera 43A # 18-90, Medellín',
            'Calle 100 # 15-20, Bogotá', 'Carrera 5 # 34-12, Bucaramanga', 'Calle 30 # 6-77, Pereira',
            'Carrera 15 # 93-60, Bogotá',
        ];

        $total = count($datos);

        foreach ($datos as $i => [$nombre, $apellido, $tipoDoc, $numDoc, $correo, $tel]) {
            $cliente = Customer::updateOrCreate(
                ['document_number' => $numDoc],
                [
                    'first_name'    => $nombre,
                    'last_name'     => $apellido,
                    'email'         => $correo,
                    'phone'         => $tel,
                    'document_type' => $tipoDoc,
                    'address'       => $direcciones[$i % count($direcciones)],
                ]
            );

            // Fechas repartidas sobre los últimos 6 meses para que las
            // gráficas del panel muestren crecimiento y no un solo pico.
            $t     = $total > 1 ? $i / ($total - 1) : 1.0;
            $fecha = Carbon::now()->subDays((int) round(172 - $t * 172))
                                  ->setTime(9 + ($i % 9), ($i * 11) % 60, 0);

            $cliente->timestamps = false;
            $cliente->created_at = $fecha;
            $cliente->updated_at = $fecha;
            $cliente->save();
            $cliente->timestamps = true;
        }

        $this->command?->info('  Clientes: ' . count($datos));
    }
}
