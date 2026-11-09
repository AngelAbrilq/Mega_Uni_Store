<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Hardware del mostrador
|--------------------------------------------------------------------------
| Todo lo que el POS le pide a un aparato físico (cajón monedero, impresora
| térmica) se configura aquí y NUNCA con env() disperso por el código: en
| producción se cachea la configuración (`php artisan config:cache`) y a
| partir de ese momento env() devuelve null fuera de estos archivos.
|
| Ojo con quién ejecuta el pulso: PHP corre en el SERVIDOR (Laragon), no en
| el navegador del cajero. El cajón debe estar accesible desde esa máquina,
| sea porque está enchufado a ella o porque la impresora/adaptador está
| compartida en la red local.
*/

return [

    'cajon' => [

        // windows  → cola de impresión de Windows (USB o recurso compartido)
        // network  → impresora térmica con IP propia, cajón en su RJ11
        // serial   → adaptador USB trigger que se presenta como puerto COM
        // none     → sin hardware (desarrollo, demos, servidores Linux)
        'driver' => env('CASH_DRAWER_DRIVER', 'none'),

        // Driver "windows": nombre EXACTO del recurso compartido.
        // Local:  CAJON_USB          (la impresora debe estar compartida igual)
        // Remoto: \\PC-CAJA\CAJON_USB
        'printer_name' => env('PRINTER_NAME', 'CAJON_USB'),

        // Credenciales solo si el recurso compartido las pide.
        'user'     => env('PRINTER_USER'),
        'password' => env('PRINTER_PASSWORD'),

        // Driver "network".
        'host' => env('PRINTER_HOST', '192.168.1.100'),
        'port' => (int) env('PRINTER_PORT', 9100),

        // Driver "serial". La mayoría de adaptadores USB trigger baratos NO
        // se instalan como impresora: crean un puerto COM virtual y abren el
        // cajón con cualquier byte que se escriba ahí. Windows numera ese
        // puerto solo, así que el número cambia según en qué USB se enchufe:
        // se mira en Administrador de dispositivos › Puertos (COM y LPT).
        'com'  => env('CASH_DRAWER_COM', 'COM3'),
        'baud' => (int) env('CASH_DRAWER_BAUD', 9600),

        // Pin del conector RJ11. Casi todos los cajones responden al 0;
        // algunos modelos de dos cajones usan el 1 para el segundo.
        'pin' => (int) env('CASH_DRAWER_PIN', 0),

        // Milisegundos del pulso. Por debajo de 50 hay cajones que no
        // alcanzan a mover el solenoide; por encima de 200 se calienta.
        'on_ms'  => (int) env('CASH_DRAWER_ON_MS', 120),
        'off_ms' => (int) env('CASH_DRAWER_OFF_MS', 240),

        // Segundos antes de rendirse con una impresora de red muerta. Sin
        // esto, un cable suelto congela la petición hasta el timeout de PHP
        // y el cajero cree que la venta no se guardó.
        'timeout' => (int) env('CASH_DRAWER_TIMEOUT', 3),

        // Solo para el driver "network". El comando DLE EOT devuelve el
        // nivel del pin 3 del RJ11: en casi todos los cajones ALTO = abierto,
        // pero hay modelos cableados al revés. Si el diagnóstico dice
        // "abierto" con el cajón cerrado, pon esto en true.
        'estado_invertido' => (bool) env('CASH_DRAWER_INVERTIDO', false),
    ],

];
