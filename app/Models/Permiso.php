<?php

namespace App\Models;

use App\Support\CatalogoPermisos;
use Spatie\Permission\Models\Permission;

/**
 * Permiso del sistema con sus datos de catálogo (SA-PER-001).
 *
 *   name         «módulo.acción»: es lo que preguntan @can, policies y middlewares.
 *   module       módulo (primera parte del nombre).
 *   action       acción (segunda parte).
 *   description  para qué sirve, en palabras.
 *   is_system    lo define RolePermissionSeeder y el código lo usa: no se
 *                renombra ni se borra. Los demás son personalizados.
 */
class Permiso extends Permission
{
    protected $fillable = ['name', 'guard_name', 'module', 'action', 'description', 'is_system'];

    protected $attributes = ['guard_name' => 'web', 'is_system' => false];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function modulo(): string
    {
        return $this->module ?: explode('.', $this->name)[0];
    }

    public function accion(): string
    {
        return $this->action ?: (explode('.', $this->name)[1] ?? 'ver');
    }

    public function etiqueta(): string
    {
        return CatalogoPermisos::accion($this->accion());
    }

    public function tipo(): string
    {
        return CatalogoPermisos::tipoDe($this->accion());
    }
}
