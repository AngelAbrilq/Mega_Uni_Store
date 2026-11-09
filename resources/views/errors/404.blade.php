@include('errors.minimal', [
    'codigo' => 404,
    'titulo' => 'Esta página no existe',
    'texto'  => 'La dirección que abriste no corresponde a ninguna sección del sistema. Puede que el registro se haya eliminado o que el enlace esté mal escrito.',
    'icono'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6M11 8v3.4M11 14v.01"/>',
])
