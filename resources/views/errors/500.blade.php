@include('errors.minimal', [
    'codigo' => 500,
    'titulo' => 'Se cayó algo del lado del servidor',
    'texto'  => 'El sistema encontró un error mientras procesaba tu petición. Ya quedó registrado en storage/logs para revisarlo.',
    'icono'  => '<path d="M10.3 4.3 2.6 18a1.8 1.8 0 0 0 1.6 2.7h15.6A1.8 1.8 0 0 0 21.4 18L13.7 4.3a1.9 1.9 0 0 0-3.4 0Z"/><path d="M12 9.5v4M12 17v.01"/>',
])
