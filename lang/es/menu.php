<?php

/**
 * Menú lateral y paleta de comandos (Ctrl + K).
 *
 * Están juntos porque nombran lo mismo: las secciones del sistema. Si el
 * menú dice «Vender» y la paleta dice «Punto de venta», el usuario cree
 * que son dos pantallas distintas.
 */
return [

    /* Títulos de los grupos del menú */
    'grupos' => [
        'principal'      => 'Principal',
        'catalogo'       => 'Catálogo',
        'comercial'      => 'Comercial',
        'configuracion'  => 'Configuración',
        'administracion' => 'Administración',
    ],

    /* Cada entrada: nombre corto (menú) y descripción (paleta) */
    'panel'           => 'Panel',
    'panel_sub'       => 'Resumen general',
    'vender'          => 'Vender',
    'vender_sub'      => 'Cobrar en el mostrador',
    'ventas'          => 'Ventas',
    'ventas_sub'      => 'Historial de la caja',
    'caja'            => 'Caja',
    'caja_sub'        => 'Turnos y arqueos',
    'devoluciones'    => 'Devoluciones',
    'devoluciones_sub' => 'Cambios y reintegros',
    'reportes'        => 'Reportes',
    'reportes_sub'    => 'Cómo va el negocio',
    'productos'       => 'Productos',
    'productos_sub'   => 'Catálogo y precios',
    'categorias'      => 'Categorías',
    'categorias_sub'  => 'Árbol de clasificación',
    'inventario'      => 'Inventario',
    'traslados'       => 'Traslados',
    'inventario_sub'  => 'Kardex de existencias',
    'clientes'        => 'Clientes',
    'clientes_sub'    => 'Base de compradores',
    'proveedores'     => 'Proveedores',
    'proveedores_sub' => 'Abastecimiento',
    'compras'         => 'Compras',
    'compras_sub'     => 'Pedidos y entradas',
    'unidades'        => 'Unidades',
    'unidades_sub'    => 'Medidas de venta',
    'impuestos'       => 'Impuestos',
    'impuestos_sub'   => 'Tarifas aplicables',
    'medios_pago'     => 'Medios de pago',
    'medios_pago_sub' => 'Formas de cobro',
    'atributos'       => 'Atributos',
    'atributos_sub'   => 'Variantes de producto',
    'usuarios'        => 'Usuarios',
    'usuarios_sub'    => 'Equipo y permisos',
    'roles'           => 'Roles',
    'plan'            => 'Tu plan',
    'sistema'         => 'Todos los negocios',
    'auditoria'       => 'Auditoría',
    'auditoria_sub'   => 'Quién cambió qué',
    'ajustes'         => 'Configuración',
    'ajustes_sub'     => 'Datos del negocio',
    'perfil'          => 'Mi perfil',
    'perfil_sub'      => 'Datos de la cuenta',

    /* Acciones rápidas de la paleta */
    'abrir_caja'      => 'Abrir caja',
    'abrir_caja_sub'  => 'Iniciar turno',
    'nuevo_usuario'   => 'Nuevo usuario',
    'nuevo_usuario_sub' => 'Dar acceso al sistema',
    'nuevo_producto'  => 'Nuevo producto',
    'nuevo_producto_sub' => 'Agregar al catálogo',
    'nueva_categoria' => 'Nueva categoría',
    'nueva_categoria_sub' => 'Clasificar inventario',
    'nuevo_cliente'   => 'Nuevo cliente',
    'nuevo_cliente_sub' => 'Registrar comprador',
    'nuevo_proveedor' => 'Nuevo proveedor',
    'nuevo_proveedor_sub' => 'Sumar aliado',
    'nueva_compra'    => 'Nueva compra',
    'nueva_compra_sub' => 'Pedido a proveedor',
    'nueva_unidad'    => 'Nueva unidad',
    'nueva_unidad_sub' => 'Medida de venta',
    'nuevo_impuesto'  => 'Nuevo impuesto',
    'nuevo_impuesto_sub' => 'Tarifa aplicable',
];
