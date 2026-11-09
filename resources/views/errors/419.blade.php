@include('errors.minimal', [
    'codigo' => 419,
    'titulo' => 'La sesión caducó',
    'texto'  => 'Pasó demasiado tiempo con el formulario abierto y el sistema cerró la sesión por seguridad. Vuelve a entrar y repite la operación.',
    'icono'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7.2V12l3.2 2"/>',
])
