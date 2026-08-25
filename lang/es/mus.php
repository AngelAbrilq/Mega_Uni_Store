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
];
