<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\Concerns\ReglasDeImagen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Subida de fotos, compartida por productos, clientes, proveedores y
 * usuarios.
 *
 * La regla es siempre la misma: el archivo va al disco (storage/app/public)
 * y en la base de datos queda solo la ruta, en un VARCHAR. Guardar la
 * imagen dentro de la base la infla, hace lentas las consultas y complica
 * los respaldos; guardando la ruta, el respaldo del .sql sigue pesando
 * kilobytes y la foto la sirve el servidor web como cualquier archivo.
 */
trait GuardaImagen
{
    use ReglasDeImagen;

    /**
     * Devuelve qué hay que escribir en la columna:
     *
     *   string  → ruta nueva
     *   null    → dejarla vacía (el usuario quitó la foto)
     *   false   → no tocarla (no se subió nada y ya había una)
     *
     * @param  string  $carpeta  subcarpeta dentro de storage/app/public
     * @param  string  $campo    nombre del <input type="file">
     */
    protected function guardarImagen(
        Request $request,
        string $carpeta,
        ?string $anterior = null,
        string $campo = 'imagen'
    ): string|null|false {
        if ($request->boolean($campo . '_eliminar')) {
            $this->borrarArchivo($anterior);

            return null;
        }

        if (! $request->hasFile($campo)) {
            return $anterior === null ? null : false;
        }

        // store() inventa un nombre único, así que dos fotos que se llamen
        // igual («foto.jpg») nunca se pisan.
        $ruta = $request->file($campo)->store($carpeta, 'public');

        $this->borrarArchivo($anterior);

        return $ruta;
    }

    /** Borra del disco una foto que ya no usa nadie. */
    protected function borrarArchivo(?string $ruta): void
    {
        if ($ruta && ! str_starts_with($ruta, 'http') && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
