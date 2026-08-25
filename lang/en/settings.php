<?php

/**
 * Settings screen — English side. Same keys as lang/es/settings.php.
 */
return [

    'titulo'    => 'Settings',
    'subtitulo' => 'How the system behaves and what shows up on documents',
    'guardado'  => 'Settings saved. Receipts now print with these details.',
    'guardar'   => 'Save settings',

    'tabs' => [
        'negocio'    => 'Business',
        'regional'   => 'Regional',
        'recibo'     => 'Receipt',
        'ventas'     => 'Sales',
        'inventario' => 'Inventory',
        'apariencia' => 'Appearance',
    ],

    /* ─────────────────── Business ─────────────────── */
    'negocio' => [
        'titulo'    => 'Business identity',
        'sub'       => 'Header of receipts, reports and emails',
        'nombre'    => 'Business name',
        'lema'      => 'Tagline or description',
        'lema_hint' => 'Prints under the name on the receipt.',
        'nit'       => 'Tax ID',
        'nit_hint'  => 'Digits only, no dots or dashes.',
        'telefono'  => 'Phone',
        'correo'    => 'Email',
        'ciudad'    => 'City',
        'direccion' => 'Address',
        'logo'      => 'Business logo',
        'logo_hint' => 'Square, at least 200×200. Prints on the receipt when "Show logo" is on. A transparent PNG looks best.',
    ],

    /* ─────────────────── Regional ─────────────────── */
    'regional' => [
        'titulo'  => 'Language, currency and formats',
        'sub'     => 'How numbers, dates and money are written',

        'idioma'      => 'System language',
        'idioma_hint' => 'Changes every text in the dashboard. Each user can pick their own from their profile; this is the default.',

        'moneda_codigo'  => 'Currency code',
        'moneda_codigo_hint' => 'Three letters, ISO 4217. COP for Colombian peso, USD for dollar, MXN for Mexican peso.',
        'moneda_simbolo' => 'Symbol',
        'moneda_simbolo_hint' => 'What gets printed before or after the number: $, US$, €.',
        'simbolo_posicion' => 'Symbol position',
        'simbolo_antes'  => 'Before the number — $ 1,500',
        'simbolo_despues' => 'After the number — 1,500 $',

        'decimales'      => 'Decimal places on prices',
        'decimales_hint' => 'In Colombia 0 is normal: the peso has no cents at the counter. Use 2 if you sell in dollars.',
        'sep_miles'      => 'Thousands separator',
        'sep_decimal'    => 'Decimal separator',
        'punto'          => 'Dot  ·  1.500',
        'coma'           => 'Comma  ·  1,500',
        'espacio'        => 'Space  ·  1 500',
        'ninguno'        => 'None  ·  1500',

        'zona_horaria'   => 'Time zone',
        'zona_hint'      => 'Sets the time recorded on every sale. Changing it does not move past sales: they keep the time they happened.',

        'formato_fecha'  => 'Date format',
        'ejemplo'        => 'This is how it will look',
    ],

    /* ─────────────────── Receipt ─────────────────── */
    'recibo' => [
        'titulo'   => 'Sales receipt',
        'sub'      => 'What the customer walks out with',

        'formato'  => 'Paper width',
        'formato_hint' => 'Pick the one your printer uses. For regular sheets, choose Letter.',
        'f80'      => '80 mm roll — common thermal printer',
        'f58'      => '58 mm roll — portable or pocket printer',
        'fa4'      => 'Letter / A4 — sheet printer',

        'mostrar_logo'    => 'Show the business logo',
        'mostrar_logo_hint' => 'If no logo is uploaded, the system icon prints instead.',
        'mostrar_qr'      => 'Print a QR code',
        'mostrar_qr_hint' => 'Links to the receipt page, so the customer can look it up later without keeping the paper.',
        'mostrar_impuestos' => 'Break taxes down by rate',
        'mostrar_impuestos_hint' => 'Instead of a single "Taxes" line, shows how much each rate accounted for. Turn it on if you invoice.',
        'mostrar_cajero'  => 'Show who served the customer',
        'mostrar_ahorro'  => 'Show how much the customer saved',
        'mostrar_ahorro_hint' => 'Only appears when the sale had a discount.',

        'mensaje'  => 'Thank-you message',
        'mensaje_hint' => 'The last line of the receipt. Short and friendly.',
        'pie'      => 'Receipt footer',
        'pie_hint' => 'Optional. For example: exchange and return policy, or opening hours.',

        'copias'   => 'Copies when printing',
        'copias_hint' => 'Two if you keep one for the drawer.',
        'auto'     => 'Open the receipt right after checkout',
        'auto_hint' => 'Saves a click at the counter. Turn it off if you rarely print.',
    ],

    /* ─────────────────── Sales ─────────────────── */
    'ventas' => [
        'titulo' => 'Selling rules',
        'sub'    => 'How the point of sale behaves',

        'stock_negativo' => 'Allow selling with no stock',
        'stock_negativo_hint' => 'When on, the system lets you charge even if stock goes negative. Leave it off if you want inventory to always add up.',

        'descuento_max'  => 'Maximum discount per line',
        'descuento_max_hint' => 'As a percentage. Use 0 to forbid discounts and 100 for no limit. Prevents the cashier going too far.',

        'cliente_obligatorio' => 'Require a customer on every sale',
        'cliente_obligatorio_hint' => 'Off, you can charge a walk-in customer. Turn it on if you want to build a buyer database.',

        'redondeo' => 'Round the total',
        'redondeo_hint' => 'Useful where small coins are no longer in circulation.',
        'r_no'     => 'No rounding',
        'r_50'     => 'To the nearest 50',
        'r_100'    => 'To the nearest 100',
    ],

    /* ─────────────────── Inventory ─────────────────── */
    'inventario' => [
        'titulo' => 'Inventory and alerts',
        'sub'    => 'When the system warns you something is running out',

        'alerta_activa' => 'Warn when a product hits its minimum',
        'alerta_activa_hint' => 'The alert shows in the dashboard bell and in the product list.',

        'dias_rotacion' => 'Days used to compute turnover',
        'dias_rotacion_hint' => 'How far back the system looks to estimate how much of each product sells. 30 is normal; raise it if your volume is low.',

        'costo_metodo'  => 'How stock leaving is valued',
        'costo_metodo_hint' => 'Changing it does not rewrite movements already recorded; it applies from here on.',
        'promedio'      => 'Weighted average cost',
        'ultimo'        => 'Last purchase cost',
    ],

    /* ─────────────────── Appearance ─────────────────── */
    'apariencia' => [
        'titulo' => 'Appearance',
        'sub'    => 'How the dashboard looks for the whole team',

        'acento'      => 'Accent color',
        'acento_hint' => 'The color of primary buttons and links.',
        'azul'        => 'Blue · the usual',
        'verde'       => 'Green',
        'violeta'     => 'Violet',
        'ambar'       => 'Amber',
        'grafito'     => 'Graphite',

        'densidad'      => 'Table density',
        'densidad_hint' => 'Compact fits more on screen; comfortable reads better at the counter.',
        'comoda'        => 'Comfortable',
        'compacta'      => 'Compact',

        'animaciones'      => 'Animations and transitions',
        'animaciones_hint' => 'Turn them off on slow computers. The system already disables them when the device asks for reduced motion.',
    ],
];
