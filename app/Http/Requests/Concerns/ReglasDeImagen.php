<?php

namespace App\Http\Requests\Concerns;

/**
 * Reglas de validación de una foto, compartidas por Form Requests y por
 * el trait GuardaImagen de los controladores. 2 MB es de sobra para una
 * foto de perfil y evita que alguien suba una imagen de cámara de 12 MP.
 */
trait ReglasDeImagen
{
    /** @return array<string, mixed> */
    protected function reglasImagen(string $campo = 'imagen'): array
    {
        return [
            $campo              => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            $campo . '_eliminar' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesImagen(string $campo = 'imagen'): array
    {
        return [
            $campo . '.image' => 'El archivo debe ser una imagen.',
            $campo . '.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            $campo . '.max'   => 'La imagen no puede pesar más de 2 MB.',
        ];
    }
}
