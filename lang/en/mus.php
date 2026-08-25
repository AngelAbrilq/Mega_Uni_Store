<?php

/**
 * Shared vocabulary for the whole dashboard — English side.
 *
 * Keys are identical to lang/es/mus.php. They stay in Spanish on purpose:
 * the key is an identifier, not a translation. If the key were English,
 * every view would read as English source code and the Spanish translation
 * would look like the "secondary" one — and Spanish is the primary market.
 *
 * Rule when adding a string: add it to BOTH files in the same commit.
 * A missing key does not break the page, it prints the raw key
 * ("mus.acciones.guardar") in the middle of the interface, which is worse
 * than an error because nobody notices until a customer sees it.
 */
return [

    /* ─────────────────────── Actions ─────────────────────── */
    'acciones' => [
        'nuevo'       => 'New',
        'crear'       => 'Create',
        'guardar'     => 'Save',
        'guardar_cambios' => 'Save changes',
        'cancelar'    => 'Cancel',
        'volver'      => 'Back',
        'volver_panel' => 'Back to dashboard',
        'editar'      => 'Edit',
        'ver'         => 'View',
        'ver_detalle' => 'View details',
        'eliminar'    => 'Delete',
        'restaurar'   => 'Restore',
        'buscar'      => 'Search',
        'filtrar'     => 'Filter',
        'limpiar'     => 'Clear',
        'limpiar_filtros' => 'Clear filters',
        'exportar'    => 'Export',
        'imprimir'    => 'Print',
        'descargar'   => 'Download',
        'compartir'   => 'Share',
        'copiar'      => 'Copy',
        'copiado'     => 'Copied',
        'cerrar'      => 'Close',
        'confirmar'   => 'Confirm',
        'aceptar'     => 'OK',
        'anular'      => 'Void',
        'aplicar'     => 'Apply',
        'seleccionar' => 'Select',
        'subir_foto'  => 'Upload photo',
        'quitar_foto' => 'Remove photo',
        'cambiar_foto' => 'Change photo',
        'cerrar_sesion' => 'Log out',
        'iniciar_sesion' => 'Log in',
        'mi_perfil'   => 'My profile',
        'ir_al_panel' => 'Go to dashboard',
    ],

    /* ─────────────────────── Entities ─────────────────────── */
    'entidades' => [
        'producto'   => 'product',    'productos'   => 'Products',
        'categoria'  => 'category',   'categorias'  => 'Categories',
        'cliente'    => 'customer',   'clientes'    => 'Customers',
        'proveedor'  => 'supplier',   'proveedores' => 'Suppliers',
        'venta'      => 'sale',       'ventas'      => 'Sales',
        'compra'     => 'purchase',   'compras'     => 'Purchases',
        'devolucion' => 'return',     'devoluciones' => 'Returns',
        'usuario'    => 'user',       'usuarios'    => 'Users',
        'unidad'     => 'unit',       'unidades'    => 'Units',
        'impuesto'   => 'tax',        'impuestos'   => 'Taxes',
        'medio_pago' => 'payment method', 'medios_pago' => 'Payment methods',
        'atributo'   => 'attribute',  'atributos'   => 'Attributes',
        'turno'      => 'shift',      'turnos'      => 'Shifts',
        'movimiento' => 'movement',   'movimientos' => 'Movements',
    ],

    /* ─────────────────────── Fields ─────────────────────── */
    'campos' => [
        'nombre'        => 'Name',
        'descripcion'   => 'Description',
        'codigo'        => 'Code',
        'sku'           => 'SKU',
        'barras'        => 'Barcode',
        'precio'        => 'Price',
        'costo'         => 'Cost',
        'margen'        => 'Margin',
        'cantidad'      => 'Quantity',
        'existencias'   => 'Stock',
        'stock_minimo'  => 'Minimum stock',
        'unidad'        => 'Unit',
        'categoria'     => 'Category',
        'proveedor'     => 'Supplier',
        'cliente'       => 'Customer',
        'impuesto'      => 'Tax',
        'estado'        => 'Status',
        'fecha'         => 'Date',
        'hora'          => 'Time',
        'total'         => 'Total',
        'subtotal'      => 'Subtotal',
        'descuento'     => 'Discount',
        'descuentos'    => 'Discounts',
        'impuestos'     => 'Taxes',
        'notas'         => 'Notes',
        'observaciones' => 'Remarks',
        'telefono'      => 'Phone',
        'correo'        => 'Email',
        'direccion'     => 'Address',
        'ciudad'        => 'City',
        'documento'     => 'ID number',
        'nit'           => 'Tax ID',
        'rol'           => 'Role',
        'roles'         => 'Roles',
        'permiso'       => 'Permission',
        'permisos'      => 'Permissions',
        'contrasena'    => 'Password',
        'confirmar_contrasena' => 'Confirm password',
        'foto'          => 'Photo',
        'imagen'        => 'Image',
        'activo'        => 'Active',
        'creado'        => 'Created',
        'actualizado'   => 'Updated',
        'atendio'       => 'Served by',
        'metodo'        => 'Method',
        'referencia'    => 'Reference',
        'valor'         => 'Amount',
        'acciones'      => 'Actions',
    ],

    /* ─────────────────────── States ─────────────────────── */
    'estados' => [
        'activo'      => 'Active',
        'inactivo'    => 'Inactive',
        'disponible'  => 'In stock',
        'agotado'     => 'Out of stock',
        'bajo'        => 'Low',
        'pagada'      => 'Paid',
        'anulada'     => 'Voided',
        'pendiente'   => 'Pending',
        'recibida'    => 'Received',
        'abierto'     => 'Open',
        'cerrado'     => 'Closed',
        'todos'       => 'All',
        'ninguno'     => 'None',
        'si'          => 'Yes',
        'no'          => 'No',
    ],

    /* ─────────────────────── Messages ─────────────────────── */
    'mensajes' => [
        'creado'      => ':Que created successfully.',
        'actualizado' => ':Que updated successfully.',
        'eliminado'   => ':Que deleted successfully.',
        'guardado'    => 'Changes saved.',
        'sin_cambios' => 'There was nothing to save.',
        'error'       => 'Something went wrong. Check the data and try again.',
        'confirmar_eliminar' => 'Delete :que? This cannot be undone.',
        'sin_permiso' => 'You do not have permission to do this.',
        'no_encontrado' => 'We could not find what you were looking for.',
    ],

    /* ─────────────────────── Empty states ─────────────────────── */
    'vacio' => [
        'sin_resultados'  => 'No results match',
        'prueba_otra'     => 'Try a different search or clear the filters.',
        'sin_registros'   => 'Nothing here yet',
        'agrega_primero'  => 'Add the first one to start seeing activity.',
        'sin_categoria'   => 'No category',
        'sin_proveedor'   => 'No supplier',
        'consumidor_final' => 'Walk-in customer',
        'guion'           => '—',
    ],

    /* ─────────────────────── Time ─────────────────────── */
    'tiempo' => [
        'hoy'        => 'Today',
        'ayer'       => 'Yesterday',
        'semana'     => 'This week',
        'mes'        => 'This month',
        'anio'       => 'This year',
        'personalizado' => 'Custom',
        'desde'      => 'From',
        'hasta'      => 'To',
        'periodo'    => 'Period',
    ],

    /* ─────────────────────── Pagination ─────────────────────── */
    'paginacion' => [
        'resultados'  => ':n results',
        'mostrando'   => 'Showing :desde–:hasta of :total :que',
        'anterior'    => 'Previous',
        'siguiente'   => 'Next',
    ],

    /* ─────────────────────── General interface ─────────────────────── */
    'ui' => [
        'panel'          => 'Management dashboard',
        'abrir_menu'     => 'Open menu',
        'fijar_menu'     => 'Keep the menu open',
        'menu_usuario'   => 'User menu',
        'buscar_panel'   => 'Search the dashboard',
        'buscar_ph'      => 'Search a product, customer, sale or section…',
        'ir_a'           => 'Go to',
        'crear_rapido'   => 'Create',
        'moverse'        => 'move',
        'abrir'          => 'open',
        'cerrar_kbd'     => 'close',
        'notificaciones' => 'Notifications',
        'sin_avisos'     => 'No pending alerts.',
        'idioma'         => 'Language',
        'requerido'      => 'required',
        'opcional'       => 'optional',
    ],

    /* ────────────────── Active business and store ────────────────── */
    'contexto' => [
        'titulo'        => 'Where you are working',
        'negocio'       => 'Business',
        'local'         => 'Store',
        'cambiar'       => 'Switch store',
        'un_solo_local' => 'Single store',
        'sin_local'     => 'No store assigned',
        'ahora_en'      => 'You are now working at :lugar.',
        'no_es_tuya'    => 'That business is not yours.',
        'vencida'       => 'The trial for “:negocio” has expired.',
        'suspendida'    => 'The account for “:negocio” is suspended.',
        'aqui_vendes'   => 'Whatever you register stays in this store.',
        'est_vencida'   => 'trial expired',
        'est_suspendida'=> 'suspended',
    ],
];
