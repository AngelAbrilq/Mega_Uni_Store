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
        'cobrar'      => 'Take payment',
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
        'alguien'         => 'Someone',
        'sin_turno'       => 'No shift',
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

    /* ────────────────── System gates ──────────────────
       The layout's transition screens: session check, sign-off, the red
       "you are viewing another business" banner, and the trial clock. */
    'puertas' => [
        'verificando'    => 'Checking your session',
        'clic_entrar'    => 'Click to continue',
        'hasta_pronto'   => 'See you soon',
        'cargando'       => 'Loading…',
        /* The phrases that cycle on the entry screen. Read from JavaScript,
           which is why they live here and not loose in the script. */
        'gate_frases'    => ['Checking your session', 'Loading modules', 'Getting your panel ready', 'All set'],
        'mirando'        => 'You are viewing “:negocio”',
        'mirando_aviso'  => 'Anything you do here is logged under your name in the customer\'s audit trail.',
        'volver_a_lo_mio' => 'Back to my account',
        'demo_de'        => 'Trial of “:negocio”',
        'demo_min'       => 'min',
        'demo_aviso'     => 'This is sample data. When you are done, get in touch and we will set up yours.',
    ],

    /* ────────────────── Search and alerts ────────────────── */
    'buscador' => [
        'avisos'          => 'Alerts',
        'avisos_n'        => 'System alerts (:n)',
        'sin_avisos'      => 'Nothing needs your attention.',
        'abrir'           => 'Search (Ctrl K)',
        'kbd_ctrl_k'      => 'Ctrl K',
        'kbd_enter'       => 'Enter',
        'kbd_esc'         => 'Esc',
    ],

    /* ────────────────── Point of sale ────────────────── */
    'pos' => [
        'titulo'          => 'Point of sale',
        'subtitulo'       => 'Serve the customer and take payment',
        'buscar_ph'       => 'Name, SKU or barcode…  (F2)',
        'sin_coincidencias' => 'No product matches that search.',

        'turno_abierto'   => 'Shift open · float :base',
        'ventas_dia'      => 'Sales today',
        'sin_turno'       => 'You do not have a register shift open.',
        'sin_turno_detalle' => 'You can still sell, but the sale will not belong to any cash count. '
            . 'Better to open the register with its float before you start.',

        'venta_en_curso'  => 'Current sale',
        'sin_productos'   => 'No items yet',
        'toca_para_agregar' => 'Tap a product to add it.',
        'nota_venta'      => 'Sale note (optional)',

        'total_a_cobrar'  => 'Amount due',
        'pago_exacto'     => 'Exact amount on the first method',
        'recibido'        => 'Tendered',
        'falta'           => 'Still owed',
        'cambio'          => 'Change',
        'cobrar_aria'     => 'Take payment for this sale',
        /* Strings the register's JavaScript builds. They live here rather
           than loose in the script because they are the most-read text in
           the whole system: the cashier sees them on every tap. */
        'js_producto'     => 'item',
        'js_productos'    => 'items',
        'js_unidad'       => 'unit',
        'js_unidades'     => 'units',
        'js_sin_stock'    => 'Out of stock',
        'js_solo_quedan'  => 'Only :n of “:que” left.',
        'js_guardando'    => 'Saving…',
        'confirmar_venta' => 'Complete sale',
    ],

    /* ────────────────── Sales ────────────────── */
    'ventas' => [
        'titulo'          => 'Sales',
        'subtitulo'       => 'Everything that has gone through the register',
        'nueva'           => 'New sale',
        'ir_al_pos'       => 'Go to point of sale',
        'buscar_ph'       => 'Number or customer…',

        'del_periodo'     => 'Sales this period',
        'facturado'       => 'Revenue',
        'utilidad'        => 'Profit',
        'margen_pct'      => 'Margin %',
        'ticket_promedio' => 'Average ticket',
        'anuladas'        => 'Voided',
        'todas'           => 'All',
        'pagadas'         => 'Paid',
        'todos_vendedores' => 'All cashiers',

        'numero'          => 'Number',
        'vendedor'        => 'Cashier',
        'articulos'       => 'Items',
        'anulada'         => 'Voided',
        'pagada'          => 'Paid',

        'vacio_titulo'    => 'No sales in this period',
        'vacio_texto'     => 'Change the date range, or ring up your first sale at the point of sale.',

        /* ── Detail ── */
        'una'             => 'Sale',
        'recibo'          => 'Receipt',
        'devolucion'      => 'Return',
        'anular'          => 'Void',
        'volver_listado'  => 'Back to the list',

        'esta_anulada'    => 'Sale voided',
        'la_anulo'        => ':quien voided it on :cuando. The goods went back into stock.',
        'con_devoluciones' => ':n return(s) against this sale',
        'se_regreso'      => ':monto was refunded to the customer.',

        'productos_vendidos' => 'Items sold',
        'renglones'       => 'lines',
        'cant'            => 'Qty',
        'devuelto'        => 'Returned',
        'como_se_pago'    => 'How it was paid',
        'medios'          => 'method(s)',
        'datos'           => 'Sale details',
        'turno_caja'      => 'Register shift',
        'costo_vendido'   => 'Cost of goods sold',
        'nota'            => 'Note',

        'anular_titulo'   => 'Void this sale',
        'anular_aviso'    => 'The sale is not deleted: it is marked as voided and the items go back into '
            . 'stock with their own inventory movement. This cannot be undone.',
        'anular_motivo'   => 'Reason for voiding',
        'anular_motivo_ph' => 'e.g. the customer returned the goods',
        'anular_si'       => 'Yes, void this sale',
    ],

    /* ────────────────── Register ────────────────── */
    'caja' => [
        'titulo'          => 'Register',
        'subtitulo'       => 'Shifts, cash counts and discrepancies',
        'abrir'           => 'Open shift',
        'ir_a_mi_turno'   => 'Go to my open shift',
        'turnos'          => 'shifts',
        'responsable'     => 'Cashier',
        'base'            => 'Float',
        'esperado'        => 'Expected',
        'contado'         => 'Counted',
        'diferencia'      => 'Difference',
        'abierta'         => 'Open',
        'cerrada'         => 'Closed',

        'vacio_titulo'    => 'No register shift has been opened yet',
        'vacio_texto'     => 'Open a shift with its starting float so cash sales can be reconciled.',
        'vacio_accion'    => 'Open the first shift',

        /* ── Opening ── */
        'abrir_titulo'    => 'Open a register shift',
        'abrir_subtitulo' => 'Count the float before you start selling',
        'base_inicial'    => 'Starting float',
        'base_sub'        => 'The cash the drawer starts with',
        'base_monto'      => 'Float amount',
        'base_hint'       => 'What is physically in the drawer before the first sale.',
        'obs_hint'        => 'Optional. For example: who handed over the float.',
        'abrir_aviso'     => 'When you close the shift, the system works out how much cash there should be '
            . '(float + sales paid in cash) and compares it with what you count. '
            . 'The difference is recorded.',

        /* ── Shift detail ── */
        'turno_num'       => 'Register shift #:n',
        'abierto_el'      => '· opened on :fecha',
        'cierre_z'        => 'Z report',
        'cerrar_arquear'  => 'Close and count',
        'vendido_turno'   => 'Sold this shift',
        'efectivo_esperado' => 'Expected cash',
        'efectivo_esperado_sub' => 'float + cash payments',
        'desde_hace'      => 'for',
        'ventas_turno'    => 'Sales this shift',
        'registros'       => 'records',
        'sin_ventas'      => 'No sales yet',
        'sin_ventas_texto' => 'Your first sale will show up here.',
        'sin_cobros_turno' => 'Nothing has been collected on this shift yet.',
        'por_medio_pago'  => 'By payment method',
        'por_medio_sub'   => 'What to compare against when you count',
        'datos_turno'     => 'Shift details',
        'abrio'           => 'Opened by',
        'apertura'        => 'Opened',
        'cerro'           => 'Closed by',
        'cierre'          => 'Closed',
        'volver_turnos'   => 'Back to shifts',

        /* ── Cash count ── */
        'arqueo'          => 'Cash count',
        'arqueo_segun'    => 'The system expects',
        'arqueo_detalle'  => 'in cash (a :base float plus cash payments). '
            . 'Count the drawer and enter what is actually there.',
        'efectivo_contado' => 'Cash counted',
        'cerrar_turno'    => 'Close shift',

        /* ── Z report ── */
        'cuadro'          => 'register balanced',
        'sobrante'        => 'over',
        'faltante'        => 'short',
        'z_titulo'        => 'Z report · Shift #:n — :negocio',
        'z_encabezado'    => 'Z REPORT',
        'z_provisional'   => 'SHIFT STILL OPEN · provisional report',
        'z_turno'         => 'Shift',
        'z_sin_cerrar'    => 'Not closed',
        'z_operaciones'   => 'Transactions',
        'z_total'         => 'TOTAL SOLD',
        'z_cobros'        => 'Payments by method',
        'z_sin_cobros'    => 'No payments recorded',
        'z_arqueo'        => 'Cash count',
        'z_cobros_efectivo' => 'Cash payments',
        'z_esperado'      => 'EXPECTED',
        'z_cuadro'        => 'REGISTER BALANCED',
        'z_sobrante'      => 'OVER :monto',
        'z_faltante'      => 'SHORT :monto',
        'z_mas_vendido'   => 'Best sellers',
        'z_und'           => 'units',
        'z_firma_cajero'  => 'Cashier signature',
        'z_firma_recibe'  => 'Received by',
        'z_generado'      => 'Z report generated on :fecha',
    ],
];
