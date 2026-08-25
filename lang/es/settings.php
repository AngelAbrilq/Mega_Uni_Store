<?php

/**
 * Pantalla de Configuración.
 *
 * Cada ajuste tiene tres textos: la etiqueta (qué es), la pista (para qué
 * sirve, en palabras de quien atiende el mostrador) y a veces un ejemplo.
 * La pista es la parte que la gente de verdad lee — vale la pena escribirla
 * bien en vez de dejar el campo desnudo.
 */
return [

    'titulo'    => 'Configuración',
    'subtitulo' => 'Cómo se comporta el sistema y qué sale en los documentos',
    'guardado'  => 'Configuración guardada. Los recibos ya salen con estos datos.',
    'guardar'   => 'Guardar configuración',

    'tabs' => [
        'negocio'    => 'Negocio',
        'regional'   => 'Regional',
        'recibo'     => 'Recibo',
        'ventas'     => 'Ventas',
        'inventario' => 'Inventario',
        'apariencia' => 'Apariencia',
    ],

    /* ─────────────────── Negocio ─────────────────── */
    'negocio' => [
        'titulo'    => 'Identificación del negocio',
        'sub'       => 'Cabecera de recibos, informes y correos',
        'nombre'    => 'Nombre del negocio',
        'lema'      => 'Lema o descripción',
        'lema_hint' => 'Sale debajo del nombre en el recibo.',
        'nit'       => 'NIT',
        'nit_hint'  => 'Sin puntos ni guiones. Ejemplo: 9001234567',
        'telefono'  => 'Teléfono',
        'correo'    => 'Correo electrónico',
        'ciudad'    => 'Ciudad',
        'direccion' => 'Dirección',
        'logo'      => 'Logo del negocio',
        'logo_hint' => 'Cuadrado, mínimo 200×200. Sale en el recibo si activas «Mostrar logo». PNG con fondo transparente se ve mejor.',
    ],

    /* ─────────────────── Regional ─────────────────── */
    'regional' => [
        'titulo'  => 'Idioma, moneda y formatos',
        'sub'     => 'Cómo se escriben los números, las fechas y la plata',

        'idioma'      => 'Idioma del sistema',
        'idioma_hint' => 'Cambia todos los textos del panel. Cada usuario puede elegir el suyo desde su perfil; esto es el que trae por defecto.',

        'moneda_codigo'  => 'Código de moneda',
        'moneda_codigo_hint' => 'Tres letras, norma ISO 4217. COP para peso colombiano, USD para dólar, MXN para peso mexicano.',
        'moneda_simbolo' => 'Símbolo',
        'moneda_simbolo_hint' => 'Lo que se pinta antes o después del número: $, US$, €.',
        'simbolo_posicion' => 'Posición del símbolo',
        'simbolo_antes'  => 'Antes del número — $ 1.500',
        'simbolo_despues' => 'Después del número — 1.500 $',

        'decimales'      => 'Decimales en los precios',
        'decimales_hint' => 'En Colombia lo normal es 0: el peso no tiene centavos en el mostrador. Si vendes en dólares, usa 2.',
        'sep_miles'      => 'Separador de miles',
        'sep_decimal'    => 'Separador decimal',
        'punto'          => 'Punto  ·  1.500',
        'coma'           => 'Coma  ·  1,500',
        'espacio'        => 'Espacio  ·  1 500',
        'ninguno'        => 'Ninguno  ·  1500',

        'zona_horaria'   => 'Zona horaria',
        'zona_hint'      => 'Determina la hora que queda grabada en cada venta. Si la cambias, las ventas viejas no se mueven: quedaron con la hora en que se hicieron.',

        'formato_fecha'  => 'Formato de fecha',
        'ejemplo'        => 'Así se va a ver',
    ],

    /* ─────────────────── Recibo ─────────────────── */
    'recibo' => [
        'titulo'   => 'Recibo de venta',
        'sub'      => 'Lo que el cliente se lleva en la mano',

        'formato'  => 'Ancho del papel',
        'formato_hint' => 'Elige el que corresponda a tu impresora. Si imprimes en hojas normales, usa Carta.',
        'f80'      => 'Tirilla 80 mm — impresora térmica común',
        'f58'      => 'Tirilla 58 mm — impresora portátil o de bolsillo',
        'fa4'      => 'Carta / A4 — impresora de hojas',

        'mostrar_logo'    => 'Mostrar el logo del negocio',
        'mostrar_logo_hint' => 'Si no cargaste logo, sale el ícono del sistema.',
        'mostrar_qr'      => 'Imprimir código QR',
        'mostrar_qr_hint' => 'Lleva a la página del recibo. Sirve para que el cliente lo consulte después sin guardar el papel.',
        'mostrar_impuestos' => 'Desglosar impuestos por tarifa',
        'mostrar_impuestos_hint' => 'En vez de una sola línea «Impuestos», muestra cuánto correspondió a cada tarifa. Actívalo si facturas.',
        'mostrar_cajero'  => 'Mostrar quién atendió',
        'mostrar_ahorro'  => 'Mostrar cuánto se ahorró el cliente',
        'mostrar_ahorro_hint' => 'Solo aparece cuando la venta tuvo descuento.',

        'mensaje'  => 'Mensaje de agradecimiento',
        'mensaje_hint' => 'La última línea del recibo. Corta y amable.',
        'pie'      => 'Pie del recibo',
        'pie_hint' => 'Opcional. Por ejemplo: condiciones de cambio o devolución, o el horario de atención.',

        'copias'   => 'Copias al imprimir',
        'copias_hint' => 'Dos si guardas una para la caja.',
        'auto'     => 'Abrir el recibo apenas se cobra',
        'auto_hint' => 'Ahorra un clic en el mostrador. Apágalo si casi nunca imprimes.',
    ],

    /* ─────────────────── Ventas ─────────────────── */
    'ventas' => [
        'titulo' => 'Reglas de venta',
        'sub'    => 'Cómo se comporta el punto de venta',

        'stock_negativo' => 'Permitir vender sin existencias',
        'stock_negativo_hint' => 'Si lo activas, el sistema deja cobrar aunque el stock quede en negativo. Déjalo apagado si quieres que el inventario siempre cuadre.',

        'descuento_max'  => 'Descuento máximo por línea',
        'descuento_max_hint' => 'En porcentaje. Pon 0 para no permitir descuentos y 100 para no poner límite. Evita el «se me fue la mano» del cajero.',

        'cliente_obligatorio' => 'Exigir cliente en cada venta',
        'cliente_obligatorio_hint' => 'Apagado se puede cobrar a «Consumidor final». Enciéndelo si quieres construir la base de datos de compradores.',

        'redondeo' => 'Redondear el total',
        'redondeo_hint' => 'Útil donde ya no circulan las monedas pequeñas.',
        'r_no'     => 'No redondear',
        'r_50'     => 'A los $50 más cercanos',
        'r_100'    => 'A los $100 más cercanos',
    ],

    /* ─────────────────── Inventario ─────────────────── */
    'inventario' => [
        'titulo' => 'Inventario y alertas',
        'sub'    => 'Cuándo avisa el sistema que algo se está acabando',

        'alerta_activa' => 'Avisar cuando un producto llegue al mínimo',
        'alerta_activa_hint' => 'El aviso sale en la campana del panel y en la lista de productos.',

        'dias_rotacion' => 'Días para calcular la rotación',
        'dias_rotacion_hint' => 'Cuántos días atrás mira el sistema para estimar cuánto se vende de cada producto. 30 es lo normal; súbelo si vendes poco volumen.',

        'costo_metodo'  => 'Cómo se valora la salida de inventario',
        'costo_metodo_hint' => 'Cambiarlo no reescribe los movimientos ya registrados; aplica de aquí en adelante.',
        'promedio'      => 'Costo promedio ponderado',
        'ultimo'        => 'Último costo de compra',
    ],

    /* ─────────────────── Apariencia ─────────────────── */
    'apariencia' => [
        'titulo' => 'Apariencia',
        'sub'    => 'Cómo se ve el panel para todo el equipo',

        'acento'      => 'Color de acento',
        'acento_hint' => 'El color de los botones principales y los enlaces.',
        'azul'        => 'Azul · el de siempre',
        'verde'       => 'Verde',
        'violeta'     => 'Violeta',
        'ambar'       => 'Ámbar',
        'grafito'     => 'Grafito',

        'densidad'      => 'Densidad de las tablas',
        'densidad_hint' => 'Compacta cabe más en pantalla; cómoda se lee mejor en el mostrador.',
        'comoda'        => 'Cómoda',
        'compacta'      => 'Compacta',

        'animaciones'      => 'Animaciones y transiciones',
        'animaciones_hint' => 'Apágalas en computadores lentos. El sistema ya las apaga solo si el equipo tiene activada la reducción de movimiento.',
    ],
];
