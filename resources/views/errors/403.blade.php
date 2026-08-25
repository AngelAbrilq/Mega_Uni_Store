@include('errors.minimal', [
    'codigo' => 403,
    'titulo' => 'No tienes permiso para entrar aquí',
    'texto'  => 'Tu rol no incluye este módulo. Si necesitas acceso, pídele a un administrador que amplíe tus permisos desde Administración › Usuarios.',
    'icono'  => '<path d="M12 3 4.5 6v6c0 4.4 3.1 7.9 7.5 9 4.4-1.1 7.5-4.6 7.5-9V6z"/><path d="M12 10v3M12 16v.01"/>',
])
