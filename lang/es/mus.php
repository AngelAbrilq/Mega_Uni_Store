<?php

/**
 * Vocabulario compartido de todo el panel.
 *
 * La idea: las 121 vistas del sistema repiten las mismas 300 palabras.
 * «Guardar», «Cancelar», «Nombre», «Precio», «Activo»… Si cada vista tuviera
 * su propio archivo de traducción, la misma palabra quedaría escrita quince
 * veces y el día que se corrija una traducción habría que corregir quince.
 *
 * Aquí va todo lo que se repite. Lo que es exclusivo de un módulo —los
 * textos largos de configuración, el recibo— vive en su propio archivo.
 *
 * Se usa así en una vista:
 *     {{ __('mus.acciones.guardar') }}
 *     {{ __('mus.mensajes.creado', ['que' => __('mus.entidades.producto')]) }}
 */
return [

    /* ─────────────────────── Acciones ─────────────────────── */
    'acciones' => [
        'nuevo'       => 'Nuevo',
        'crear'       => 'Crear',
        'guardar'     => 'Guardar',
        'guardar_cambios' => 'Guardar cambios',
        'cancelar'    => 'Cancelar',
        'volver'      => 'Volver',
        'volver_panel' => 'Volver al panel',
        'editar'      => 'Editar',
        'ver'         => 'Ver',
        'ver_detalle' => 'Ver detalle',
        'eliminar'    => 'Eliminar',
        'restaurar'   => 'Restaurar',
        'buscar'      => 'Buscar',
        'filtrar'     => 'Filtrar',
        'limpiar'     => 'Limpiar',
        'limpiar_filtros' => 'Quitar filtros',
        'exportar'    => 'Exportar',
        'imprimir'    => 'Imprimir',
        'descargar'   => 'Descargar',
        'compartir'   => 'Compartir',
        'copiar'      => 'Copiar',
        'copiado'     => 'Copiado',
        'cerrar'      => 'Cerrar',
        'confirmar'   => 'Confirmar',
        'aceptar'     => 'Aceptar',
        'cobrar'      => 'Cobrar',
        'anular'      => 'Anular',
        'aplicar'     => 'Aplicar',
        'seleccionar' => 'Seleccionar',
        'subir_foto'  => 'Subir foto',
        'quitar_foto' => 'Quitar foto',
        'cambiar_foto' => 'Cambiar foto',
        'cerrar_sesion' => 'Cerrar sesión',
        'iniciar_sesion' => 'Iniciar sesión',
        'mi_perfil'   => 'Mi perfil',
        'ir_al_panel' => 'Ir al panel',
    ],

    /* ─────────────────────── Entidades ─────────────────────── */
    'entidades' => [
        'producto'   => 'producto',   'productos'   => 'Productos',
        'categoria'  => 'categoría',  'categorias'  => 'Categorías',
        'cliente'    => 'cliente',    'clientes'    => 'Clientes',
        'proveedor'  => 'proveedor',  'proveedores' => 'Proveedores',
        'venta'      => 'venta',      'ventas'      => 'Ventas',
        'compra'     => 'compra',     'compras'     => 'Compras',
        'devolucion' => 'devolución', 'devoluciones' => 'Devoluciones',
        'usuario'    => 'usuario',    'usuarios'    => 'Usuarios',
        'unidad'     => 'unidad',     'unidades'    => 'Unidades',
        'impuesto'   => 'impuesto',   'impuestos'   => 'Impuestos',
        'medio_pago' => 'medio de pago', 'medios_pago' => 'Medios de pago',
        'atributo'   => 'atributo',   'atributos'   => 'Atributos',
        'turno'      => 'turno',      'turnos'      => 'Turnos',
        'movimiento' => 'movimiento', 'movimientos' => 'Movimientos',
    ],

    /* ─────────────────────── Campos ─────────────────────── */
    'campos' => [
        'nombre'        => 'Nombre',
        'descripcion'   => 'Descripción',
        'codigo'        => 'Código',
        'sku'           => 'SKU',
        'barras'        => 'Código de barras',
        'precio'        => 'Precio',
        'costo'         => 'Costo',
        'margen'        => 'Margen',
        'cantidad'      => 'Cantidad',
        'existencias'   => 'Existencias',
        'stock_minimo'  => 'Existencia mínima',
        'unidad'        => 'Unidad',
        'categoria'     => 'Categoría',
        'proveedor'     => 'Proveedor',
        'cliente'       => 'Cliente',
        'impuesto'      => 'Impuesto',
        'estado'        => 'Estado',
        'fecha'         => 'Fecha',
        'hora'          => 'Hora',
        'total'         => 'Total',
        'subtotal'      => 'Subtotal',
        'descuento'     => 'Descuento',
        'descuentos'    => 'Descuentos',
        'impuestos'     => 'Impuestos',
        'notas'         => 'Notas',
        'observaciones' => 'Observaciones',
        'telefono'      => 'Teléfono',
        'correo'        => 'Correo electrónico',
        'direccion'     => 'Dirección',
        'ciudad'        => 'Ciudad',
        'documento'     => 'Documento',
        'nit'           => 'NIT',
        'rol'           => 'Rol',
        'roles'         => 'Roles',
        'permiso'       => 'Permiso',
        'permisos'      => 'Permisos',
        'contrasena'    => 'Contraseña',
        'confirmar_contrasena' => 'Confirmar contraseña',
        'foto'          => 'Foto',
        'imagen'        => 'Imagen',
        'activo'        => 'Activo',
        'creado'        => 'Creado',
        'actualizado'   => 'Actualizado',
        'atendio'       => 'Atendió',
        'metodo'        => 'Método',
        'referencia'    => 'Referencia',
        'valor'         => 'Valor',
        'acciones'      => 'Acciones',
    ],

    /* ─────────────────────── Estados ─────────────────────── */
    'estados' => [
        'activo'      => 'Activo',
        'inactivo'    => 'Inactivo',
        'disponible'  => 'Disponible',
        'agotado'     => 'Agotado',
        'bajo'        => 'Bajo',
        'pagada'      => 'Pagada',
        'anulada'     => 'Anulada',
        'pendiente'   => 'Pendiente',
        'recibida'    => 'Recibida',
        'abierto'     => 'Abierto',
        'cerrado'     => 'Cerrado',
        'todos'       => 'Todos',
        'ninguno'     => 'Ninguno',
        'si'          => 'Sí',
        'no'          => 'No',
    ],

    /* ─────────────────────── Mensajes ─────────────────────── */
    'mensajes' => [
        'creado'      => ':Que creado correctamente.',
        'actualizado' => ':Que actualizado correctamente.',
        'eliminado'   => ':Que eliminado correctamente.',
        'guardado'    => 'Cambios guardados.',
        'sin_cambios' => 'No había nada que guardar.',
        'error'       => 'Algo salió mal. Revisa los datos e inténtalo otra vez.',
        'confirmar_eliminar' => '¿Seguro que quieres eliminar :que? Esta acción no se puede deshacer.',
        'sin_permiso' => 'No tienes permiso para hacer esto.',
        'no_encontrado' => 'No se encontró lo que buscabas.',
    ],

    /* ─────────────────────── Vacíos ─────────────────────── */
    'vacio' => [
        'sin_resultados'  => 'Ningún resultado coincide',
        'prueba_otra'     => 'Prueba con otra búsqueda o quita los filtros.',
        'sin_registros'   => 'Todavía no hay nada aquí',
        'agrega_primero'  => 'Agrega el primero para empezar a ver movimiento.',
        'sin_categoria'   => 'Sin categoría',
        'sin_proveedor'   => 'Sin proveedor',
        'consumidor_final' => 'Consumidor final',
        'alguien'         => 'Alguien',
        'sin_turno'       => 'Sin turno',
        'guion'           => '—',
    ],

    /* ─────────────────────── Tiempo ─────────────────────── */
    'tiempo' => [
        'hoy'        => 'Hoy',
        'ayer'       => 'Ayer',
        'semana'     => 'Esta semana',
        'mes'        => 'Este mes',
        'anio'       => 'Este año',
        'personalizado' => 'Personalizado',
        'desde'      => 'Desde',
        'hasta'      => 'Hasta',
        'periodo'    => 'Periodo',
    ],

    /* ─────────────────────── Paginación ─────────────────────── */
    'paginacion' => [
        'resultados'  => ':n resultados',
        'mostrando'   => 'Mostrando :desde–:hasta de :total :que',
        'anterior'    => 'Anterior',
        'siguiente'   => 'Siguiente',
    ],

    /* ─────────────────────── Interfaz general ─────────────────────── */
    'ui' => [
        'panel'          => 'Panel de gestión',
        'abrir_menu'     => 'Abrir menú',
        'fijar_menu'     => 'Fijar el menú abierto',
        'menu_usuario'   => 'Menú de usuario',
        'buscar_panel'   => 'Buscar en el panel',
        'buscar_ph'      => 'Buscar producto, cliente, venta o sección…',
        'ir_a'           => 'Ir a',
        'crear_rapido'   => 'Crear',
        'moverse'        => 'moverse',
        'abrir'          => 'abrir',
        'cerrar_kbd'     => 'cerrar',
        'notificaciones' => 'Notificaciones',
        'sin_avisos'     => 'No hay avisos pendientes.',
        'idioma'         => 'Idioma',
        'requerido'      => 'obligatorio',
        'opcional'       => 'opcional',
    ],

    /* ────────────────── Negocio y local activos ────────────────── */
    'contexto' => [
        'titulo'        => 'Dónde estás trabajando',
        'negocio'       => 'Negocio',
        'local'         => 'Local',
        'cambiar'       => 'Cambiar de local',
        'un_solo_local' => 'Local único',
        'sin_local'     => 'Sin local asignado',
        'ahora_en'      => 'Ahora estás trabajando en :lugar.',
        'no_es_tuya'    => 'Ese negocio no es tuyo.',
        'vencida'       => 'La prueba de «:negocio» se venció.',
        'suspendida'    => 'La cuenta de «:negocio» está suspendida.',
        'aqui_vendes'   => 'Lo que registres queda en este local.',
        'est_vencida'   => 'prueba vencida',
        'est_suspendida'=> 'suspendida',
    ],

    /* ────────────────── Puertas del sistema ──────────────────
       Las pantallas de transición del layout: la que verifica la
       sesión, la despedida, el aviso rojo de «estás mirando otro
       negocio» y el reloj de la demostración. */
    'puertas' => [
        'verificando'    => 'Verificando sesión',
        'clic_entrar'    => 'Clic para entrar',
        'hasta_pronto'   => 'Hasta pronto',
        'cargando'       => 'Cargando…',
        /* Las frases que van pasando en la pantalla de entrada. Se leen
           desde el JavaScript: por eso viven aquí y no sueltas en el guion. */
        'gate_frases'    => ['Verificando sesión', 'Cargando módulos', 'Preparando tu panel', 'Todo listo'],
        'mirando'        => 'Estás viendo «:negocio»',
        'mirando_aviso'  => 'Lo que hagas queda a tu nombre en la auditoría del cliente.',
        'volver_a_lo_mio' => 'Volver a lo mío',
        'demo_de'        => 'Prueba de «:negocio»',
        'demo_min'       => 'min',
        'demo_aviso'     => 'Los datos son de ejemplo. Al terminar, escríbenos y te montamos el tuyo.',
    ],

    /* ────────────────── Buscador y avisos ────────────────── */
    'buscador' => [
        'avisos'          => 'Avisos',
        'avisos_n'        => 'Avisos del sistema (:n)',
        'sin_avisos'      => 'No hay nada que atender.',
        'abrir'           => 'Buscar (Ctrl K)',
        'kbd_ctrl_k'      => 'Ctrl K',
        'kbd_enter'       => 'Enter',
        'kbd_esc'         => 'Esc',
    ],

    /* ────────────────── Punto de venta ────────────────── */
    'pos' => [
        'titulo'          => 'Punto de venta',
        'subtitulo'       => 'Atiende al cliente y cobra',
        'buscar_ph'       => 'Nombre, SKU o código de barras…  (F2)',
        'sin_coincidencias' => 'Ningún producto coincide con la búsqueda.',

        'turno_abierto'   => 'Turno abierto · base :base',
        'ventas_dia'      => 'Ventas del día',
        'sin_turno'       => 'No tienes un turno de caja abierto.',
        'sin_turno_detalle' => 'Puedes vender igual, pero la venta no quedará asociada a ningún arqueo. '
            . 'Lo recomendable es abrir la caja con su base antes de empezar.',

        'venta_en_curso'  => 'Venta en curso',
        'sin_productos'   => 'Sin productos',
        'toca_para_agregar' => 'Toca un producto para agregarlo.',
        'nota_venta'      => 'Nota de la venta (opcional)',

        'total_a_cobrar'  => 'Total a cobrar',
        'pago_exacto'     => 'Pago exacto en el primer medio',
        'recibido'        => 'Recibido',
        'falta'           => 'Falta',
        'cambio'          => 'Cambio',
        'cobrar_aria'     => 'Cobrar la venta',
        /* Los textos que arma el JavaScript del mostrador. Van aquí y no
           sueltos en el guion porque son los que más se leen en todo el
           sistema: el cajero los ve en cada producto que toca. */
        'js_producto'     => 'producto',
        'js_productos'    => 'productos',
        'js_unidad'       => 'unidad',
        'js_unidades'     => 'unidades',
        'js_sin_stock'    => 'Sin existencias',
        'js_solo_quedan'  => 'De «:que» solo quedan :n.',
        'js_guardando'    => 'Guardando…',
        'confirmar_venta' => 'Confirmar venta',
    ],

    /* ────────────────── Ventas ────────────────── */
    'ventas' => [
        'titulo'          => 'Ventas',
        'subtitulo'       => 'Todo lo que ha pasado por la caja',
        'nueva'           => 'Nueva venta',
        'ir_al_pos'       => 'Ir al punto de venta',
        'buscar_ph'       => 'Número o cliente…',

        'del_periodo'     => 'Ventas del periodo',
        'facturado'       => 'Facturado',
        'utilidad'        => 'Utilidad',
        'margen_pct'      => '% de margen',
        'ticket_promedio' => 'Ticket promedio',
        'anuladas'        => 'Anuladas',
        'todas'           => 'Todas',
        'pagadas'         => 'Pagadas',
        'todos_vendedores' => 'Todos los vendedores',

        'numero'          => 'Número',
        'vendedor'        => 'Vendedor',
        'articulos'       => 'Artículos',
        'anulada'         => 'Anulada',
        'pagada'          => 'Pagada',

        'vacio_titulo'    => 'No hay ventas en este periodo',
        'vacio_texto'     => 'Cambia el rango de fechas o registra la primera venta desde el punto de venta.',

        /* ── Detalle ── */
        'una'             => 'Venta',
        'recibo'          => 'Recibo',
        'devolucion'      => 'Devolución',
        'anular'          => 'Anular',
        'volver_listado'  => 'Volver al listado',

        'esta_anulada'    => 'Venta anulada',
        'la_anulo'        => ':quien la anuló el :cuando. La mercancía volvió al inventario.',
        'con_devoluciones' => ':n devolución(es) sobre esta venta',
        'se_regreso'      => 'Se le regresaron :monto al cliente.',

        'productos_vendidos' => 'Productos vendidos',
        'renglones'       => 'renglones',
        'cant'            => 'Cant.',
        'devuelto'        => 'Devuelto',
        'como_se_pago'    => 'Cómo se pagó',
        'medios'          => 'medio(s)',
        'datos'           => 'Datos de la venta',
        'turno_caja'      => 'Turno de caja',
        'costo_vendido'   => 'Costo de lo vendido',
        'nota'            => 'Nota',

        'anular_titulo'   => 'Anular la venta',
        'anular_aviso'    => 'La venta no se borra: queda marcada como anulada y los productos vuelven al '
            . 'inventario con su movimiento de kardex. Esto no se puede deshacer.',
        'anular_motivo'   => 'Motivo de la anulación',
        'anular_motivo_ph' => 'Ej.: el cliente devolvió la mercancía',
        'anular_si'       => 'Sí, anular la venta',
    ],

    /* ────────────────── Caja ────────────────── */
    'caja' => [
        'titulo'          => 'Caja',
        'subtitulo'       => 'Turnos, arqueos y diferencias',
        'abrir'           => 'Abrir turno',
        'ir_a_mi_turno'   => 'Ir a mi turno abierto',
        'turnos'          => 'turnos',
        'responsable'     => 'Responsable',
        'base'            => 'Base',
        'esperado'        => 'Esperado',
        'contado'         => 'Contado',
        'diferencia'      => 'Diferencia',
        'abierta'         => 'Abierta',
        'cerrada'         => 'Cerrada',

        'vacio_titulo'    => 'Todavía no se ha abierto ninguna caja',
        'vacio_texto'     => 'Abre un turno con su base inicial para que las ventas en efectivo queden cuadradas.',
        'vacio_accion'    => 'Abrir el primer turno',

        /* ── Apertura ── */
        'abrir_titulo'    => 'Abrir turno de caja',
        'abrir_subtitulo' => 'Cuenta la base antes de empezar a vender',
        'base_inicial'    => 'Base inicial',
        'base_sub'        => 'El efectivo con el que arranca el cajón',
        'base_monto'      => 'Monto de la base',
        'base_hint'       => 'Lo que hay físicamente en la caja antes de la primera venta.',
        'obs_hint'        => 'Opcional. Por ejemplo: quién entregó la base.',
        'abrir_aviso'     => 'Al cerrar el turno el sistema calculará cuánto efectivo debería haber '
            . '(base + ventas cobradas en efectivo) y lo comparará con lo que cuentes. '
            . 'La diferencia queda registrada.',

        /* ── Detalle del turno ── */
        'turno_num'       => 'Turno de caja #:n',
        'abierto_el'      => '· abierto el :fecha',
        'cierre_z'        => 'Cierre Z',
        'cerrar_arquear'  => 'Cerrar y arquear',
        'vendido_turno'   => 'Vendido en el turno',
        'efectivo_esperado' => 'Efectivo esperado',
        'efectivo_esperado_sub' => 'base + cobros en efectivo',
        'desde_hace'      => 'desde hace',
        'ventas_turno'    => 'Ventas del turno',
        'registros'       => 'registros',
        'sin_ventas'      => 'Sin ventas todavía',
        'sin_ventas_texto' => 'Cuando registres la primera venta aparecerá aquí.',
        'sin_cobros_turno' => 'Todavía no se ha cobrado nada en este turno.',
        'por_medio_pago'  => 'Por medio de pago',
        'por_medio_sub'   => 'Lo que hay que comparar al arquear',
        'datos_turno'     => 'Datos del turno',
        'abrio'           => 'Abrió',
        'apertura'        => 'Apertura',
        'cerro'           => 'Cerró',
        'cierre'          => 'Cierre',
        'volver_turnos'   => 'Volver a los turnos',

        /* ── Arqueo ── */
        'arqueo'          => 'Arqueo de caja',
        'arqueo_segun'    => 'Según el sistema debería haber',
        'arqueo_detalle'  => 'en efectivo (base de :base más los cobros en efectivo). '
            . 'Cuenta el cajón y escribe lo que hay de verdad.',
        'efectivo_contado' => 'Efectivo contado',
        'cerrar_turno'    => 'Cerrar turno',

        /* ── Cierre Z ── */
        'cuadro'          => 'la caja cuadró',
        'sobrante'        => 'sobrante',
        'faltante'        => 'faltante',
        'z_titulo'        => 'Cierre Z · Turno #:n — :negocio',
        'z_encabezado'    => 'CIERRE Z',
        'z_provisional'   => 'TURNO TODAVÍA ABIERTO · informe provisional',
        'z_turno'         => 'Turno',
        'z_sin_cerrar'    => 'Sin cerrar',
        'z_operaciones'   => 'Operaciones',
        'z_total'         => 'TOTAL VENDIDO',
        'z_cobros'        => 'Cobros por medio de pago',
        'z_sin_cobros'    => 'Sin cobros registrados',
        'z_arqueo'        => 'Arqueo de efectivo',
        'z_cobros_efectivo' => 'Cobros en efectivo',
        'z_esperado'      => 'ESPERADO',
        'z_cuadro'        => 'LA CAJA CUADRÓ',
        'z_sobrante'      => 'SOBRANTE :monto',
        'z_faltante'      => 'FALTANTE :monto',
        'z_mas_vendido'   => 'Lo más vendido',
        'z_und'           => 'und.',
        'z_firma_cajero'  => 'Firma del cajero',
        'z_firma_recibe'  => 'Firma de quien recibe',
        'z_generado'      => 'Cierre Z generado el :fecha',
    ],
];
