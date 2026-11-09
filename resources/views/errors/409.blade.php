@include('errors.minimal', [
    'codigo' => 409,
    'titulo' => 'Esta pestaña quedó en otro local',
    'texto'  => 'Cambiaste de negocio o de local en otra pestaña, y esta seguía mostrando el anterior. No se guardó nada a propósito: lo que ibas a registrar habría caído en el sitio equivocado. Recarga esta página y vuelve a intentarlo.',
    'icono'  => '<path d="M3.5 9.5 5 4.5h14l1.5 5"/><path d="M5 11.5V19a.8.8 0 0 0 .8.8h12.4a.8.8 0 0 0 .8-.8v-7.5"/><path d="M12 13v3M12 18.5v.01"/>',
])
