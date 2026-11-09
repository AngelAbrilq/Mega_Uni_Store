<?php

namespace App\Rules;

use App\Support\Contexto;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Reglas `exists` y `unique` acotadas al negocio activo (H-F10 / H-F11).
 *
 * Las reglas de Laravel consultan la tabla directamente, sin los global
 * scopes de los modelos. `exists:customers,id` aceptaba el cliente de OTRO
 * negocio y `unique:categories,name` impedía que dos negocios tuvieran una
 * categoría con el mismo nombre, aunque la base (índice empresa_id + name)
 * sí lo permite.
 *
 * Sin contexto (consola, colas) la condición queda en `empresa_id IS NULL`:
 * falla cerrado en vez de abrir la tabla entera.
 */
final class DelNegocio
{
    /** El registro existe y es del negocio activo. */
    public static function existe(string $tabla, string $columna = 'id'): Exists
    {
        return Rule::exists($tabla, $columna)->where('empresa_id', Contexto::empresaId());
    }

    /** El usuario pertenece al negocio activo (pivote empresa_usuario). */
    public static function usuario(): Exists
    {
        return Rule::exists('empresa_usuario', 'user_id')->where('empresa_id', Contexto::empresaId());
    }

    /** El valor no se repite dentro del negocio activo. Admite ->ignore($id). */
    public static function unico(string $tabla, string $columna): Unique
    {
        return Rule::unique($tabla, $columna)->where('empresa_id', Contexto::empresaId());
    }
}
