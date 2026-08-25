<?php

/**
 * Recibo de venta.
 *
 * Va en su propio archivo porque el recibo se imprime — y lo que se imprime
 * lo lee un cliente, no un usuario del sistema. El tono es distinto: más
 * corto, sin jerga, sin «registro» ni «entidad».
 */
return [
    'titulo'      => 'Recibo',
    'recibo'      => 'Recibo',
    'fecha'       => 'Fecha',
    'atendio'     => 'Atendió',
    'cliente'     => 'Cliente',
    'documento'   => 'Documento',
    'consumidor'  => 'Consumidor final',
    'nit'         => 'NIT',
    'tel'         => 'Tel.',

    'producto'    => 'Producto',
    'valor'       => 'Valor',
    'cantidad'    => 'Cant.',

    'subtotal'    => 'Subtotal',
    'descuentos'  => 'Descuentos',
    'impuestos'   => 'Impuestos',
    'total'       => 'TOTAL',
    'cambio'      => 'Cambio',
    'recibido'    => 'Recibido',
    'ahorro'      => 'Te ahorraste',

    'anulada'     => 'VENTA ANULADA',
    'gracias'     => '¡Gracias por tu compra!',
    'resumen'     => ':unidades unidades · :referencias referencias',
    'qr_pie'      => 'Escanea para ver este recibo',

    'imprimir'    => 'Imprimir',
    'volver'      => 'Volver',
    'compartir'   => 'Compartir por WhatsApp',
    'copiar_link' => 'Copiar enlace',
    'copiado'     => 'Enlace copiado',
    'formato'     => 'Formato',

    'wa_texto'    => 'Recibo :numero de :negocio por :total. Míralo aquí: :enlace',
];
