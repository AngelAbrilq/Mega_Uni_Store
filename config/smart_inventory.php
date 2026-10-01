<?php

/*
|--------------------------------------------------------------------------
| Smart Inventory Entry
|--------------------------------------------------------------------------
| Lectura de facturas de proveedor con IA multimodal. El proveedor se
| elige por .env sin tocar código. Los IDs de modelo cambian con el tiempo
| (Gemini 1.5 y Claude 3.5 Sonnet ya están retirados): verificar contra la
| documentación oficial antes de desplegar.
*/

return [

    // gemini | anthropic
    'driver' => env('SMART_INVENTORY_DRIVER', 'gemini'),

    'providers' => [
        'gemini' => [
            'key'      => env('GEMINI_API_KEY'),
            'model'    => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
        ],
        'anthropic' => [
            'key'      => env('ANTHROPIC_API_KEY'),
            'model'    => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            'endpoint' => env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com/v1/messages'),
            'version'  => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
    ],

    'http' => [
        'timeout'  => (int) env('SMART_INVENTORY_TIMEOUT', 60),
        'retries'  => (int) env('SMART_INVENTORY_RETRIES', 2),
        'retry_ms' => 1500,
    ],

    'image' => [
        'max_kb'     => 8192,
        'mimes'      => ['jpeg', 'jpg', 'png', 'webp'],
        'min_width'  => 600,   // por debajo, la letra pequeña de una factura no es legible
        'min_height' => 600,
        'disk'       => 'local', // privado: storage/app/private
        'dir'        => 'smart-inventory',
    ],

    // Por debajo de esta confianza la lectura se rechaza (foto borrosa, recortada…).
    'min_confidence' => (float) env('SMART_INVENTORY_MIN_CONFIDENCE', 0.6),

    // Diferencia máxima (en pesos) tolerada entre la cifra de la IA y la del backend.
    'tolerance' => 0.01,

    // Markup por omisión cuando ni la factura ni el producto traen precio de venta.
    'default_markup_percent' => (float) env('SMART_INVENTORY_DEFAULT_MARKUP', 30),
];
