<?php

namespace App\Support;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * H-F06 · Nadie reparte ni toca más poder del que tiene.
 *
 * Única fuente de la regla de jerarquía: la usan UserController y
 * GuardarUsuarioRequest, y la usarán las Policies (H-04).
 */
final class Jerarquia
{
    /**
     * Roles que `$actor` puede asignar: los visibles (sistema + su negocio)
     * cuyos permisos son un subconjunto de los suyos. Así un Administrador
     * no ve ni reparte «Superadministrador» (`sistema.superadmin`).
     *
     * Historia: la regla era `exists:roles,name`, que solo comprobaba que el
     * rol existiera EN ALGÚN LADO; bastaba mandar a mano el «Estilista» de
     * otro cliente para asignarlo. Con esta lista cerrada no pasa.
     *
     * @return Collection<int, Rol>
     */
    public static function rolesAlAlcance(User $actor): Collection
    {
        $mios = $actor->getAllPermissions()->pluck('name');

        return Rol::visibles()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->filter(fn (Rol $rol) => $rol->permissions->pluck('name')->diff($mios)->isEmpty())
            ->values();
    }

    /** ¿Tiene `$objetivo` algún permiso que `$actor` no tiene? */
    public static function esSuperior(User $actor, User $objetivo): bool
    {
        return $objetivo->getAllPermissions()->pluck('name')
            ->diff($actor->getAllPermissions()->pluck('name'))
            ->isNotEmpty();
    }
}
