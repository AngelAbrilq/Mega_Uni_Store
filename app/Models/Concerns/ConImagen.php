<?php

namespace App\Models\Concerns;

/**
 * Foto de un registro (producto, cliente, proveedor, usuario).
 *
 * En la base de datos solo se guarda una ruta corta —un VARCHAR como
 * "clientes/9f3a2b.jpg"—; el archivo vive en storage/app/public. Este
 * trait convierte esa ruta en algo que se puede poner en un <img> y,
 * cuando no hay foto, entrega las iniciales y un color estable para
 * pintar el círculo de reemplazo.
 *
 * Un modelo que guarde la ruta en otra columna solo tiene que
 * sobreescribir campoImagen().
 */
trait ConImagen
{
    /** Columna donde vive la ruta. */
    public function campoImagen(): string
    {
        return 'image_url';
    }

    /** Nombre que se usa para sacar las iniciales y el color. */
    public function nombreVisible(): string
    {
        return (string) ($this->getAttribute('name') ?? '');
    }

    /**
     * Dirección utilizable en un <img>, o null si no hay foto.
     *
     * Si lo guardado ya es una URL completa (datos antiguos, una imagen
     * externa o la foto de una cuenta de Google) se devuelve tal cual.
     */
    public function getImagenAttribute(): ?string
    {
        $v = $this->getAttribute($this->campoImagen());

        if ($v === null || trim((string) $v) === '') {
            return null;
        }

        if (str_starts_with($v, 'http://') || str_starts_with($v, 'https://') || str_starts_with($v, '/')) {
            return $v;
        }

        return asset('storage/' . $v);
    }

    /**
     * Una o dos letras para el círculo de reemplazo: «Angel Abril» → AA,
     * «Cuadernos» → C.
     */
    public function getInicialesAttribute(): string
    {
        $palabras = preg_split('/\s+/u', trim($this->nombreVisible()), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($palabras === []) {
            return '?';
        }

        $letras = mb_strtoupper(mb_substr($palabras[0], 0, 1));

        if (count($palabras) > 1) {
            $letras .= mb_strtoupper(mb_substr($palabras[1], 0, 1));
        }

        return $letras;
    }

    /**
     * Color de fondo del círculo cuando no hay foto.
     *
     * Sale del propio nombre, así que el mismo cliente siempre se ve del
     * mismo color aunque cambie de página o de computador. Son tonos
     * apagados, de la misma familia que el resto del panel.
     */
    public function getColorAvatarAttribute(): string
    {
        $paleta = ['#2E6EA8', '#3E7D5C', '#7C5C2E', '#96504F', '#4A5C7A', '#5B6E4C', '#6A4E7C', '#215480'];

        $nombre = $this->nombreVisible();

        if ($nombre === '') {
            return $paleta[0];
        }

        return $paleta[abs(crc32(mb_strtolower($nombre))) % count($paleta)];
    }
}
