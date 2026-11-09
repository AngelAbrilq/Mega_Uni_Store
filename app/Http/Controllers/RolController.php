<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\GuardarRolRequest;
use App\Models\Rol;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Support\CatalogoPermisos;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles propios del negocio.
 *
 * ── Qué se puede y qué no ──
 *
 * Se pueden crear, editar y borrar los roles PROPIOS. Los siete del
 * sistema se ven —para saber qué hace cada uno y para copiar sus permisos—
 * pero no se tocan: los controladores exigen «Superadministrador» por
 * nombre, y renombrarlo dejaría a todo el mundo por fuera de todo.
 *
 * Un rol creado aquí es de este negocio y de nadie más. La peluquería
 * puede llamarlo «Estilista» aunque otra peluquería del sistema ya tenga
 * uno con ese nombre.
 */
class RolController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:roles.gestionar')];
    }

    /* ═══════════════ Listado ═══════════════ */

    public function index()
    {
        // `orderByRaw`: primero los propios del negocio, que son los que
        // uno viene a administrar; los del sistema quedan abajo, de
        // referencia.
        $roles = Rol::visibles()
            ->withCount(['permissions', 'users'])
            ->orderByRaw('empresa_id IS NULL')
            ->orderBy('name')
            ->get();

        return view('roles.index', compact('roles'));
    }

    /* ═══════════════ Alta ═══════════════ */

    public function create()
    {
        return view('roles.create', $this->listas());
    }

    public function store(GuardarRolRequest $request)
    {
        $datos = $request->validated();

        $rol = Rol::create([
            'name'       => $datos['name'],
            'guard_name' => 'web',
            'empresa_id' => Contexto::empresaId(),
        ]);

        $rol->syncPermissions($datos['permisos'] ?? []);

        $this->olvidarCache();

        return redirect()->route('roles.index')
            ->with('success', 'Rol «' . $rol->name . '» creado.');
    }

    /* ═══════════════ Edición ═══════════════ */

    public function edit(Rol $role)
    {
        $this->soloPropios($role);

        return view('roles.edit', $this->listas() + ['role' => $role]);
    }

    public function update(GuardarRolRequest $request, Rol $role)
    {
        $this->soloPropios($role);

        $datos = $request->validated();

        $role->name = $datos['name'];
        $role->save();

        $role->syncPermissions($datos['permisos'] ?? []);

        $this->olvidarCache();

        return redirect()->route('roles.index')
            ->with('success', 'Rol «' . $role->name . '» actualizado.');
    }

    /* ═══════════════ Baja ═══════════════ */

    public function destroy(Rol $role)
    {
        $this->soloPropios($role);

        // Borrar un rol que alguien tiene puesto lo deja sin permisos y sin
        // aviso: se entera el lunes, cuando no puede entrar.
        $cuantos = $role->users()->count();

        if ($cuantos > 0) {
            return back()->with(
                'error',
                'El rol «' . $role->name . '» está asignado a ' . $cuantos
                . ' persona(s). Cámbiales el rol antes de borrarlo.'
            );
        }

        $nombre = $role->name;
        $role->delete();

        $this->olvidarCache();

        return redirect()->route('roles.index')
            ->with('success', 'Rol «' . $nombre . '» eliminado.');
    }

    /* ═══════════════ Apoyo ═══════════════ */

    /**
     * Los roles del sistema no se editan desde aquí.
     *
     * Se responde 404 y no 403 a propósito: para este negocio, el rol de
     * otro negocio no existe, y decir «no tienes permiso» sería confirmar
     * que sí existe en algún lado.
     */
    private function soloPropios(Rol $role): void
    {
        abort_unless($role->empresa_id === Contexto::empresaId(), 404);
    }

    /**
     * Los permisos, agrupados como se leen (SA-PER-001 · H-F14: salen de
     * la base, así un módulo nuevo nunca queda fuera del formulario).
     *
     * @return array<string, mixed>
     */
    private function listas(): array
    {
        return ['grupos' => CatalogoPermisos::agrupados()];
    }

    /**
     * La caché de permisos se olvida a mano.
     *
     * spatie la guarda para no consultar la base en cada petición. Si no se
     * limpia, el rol cambia en la base pero la gente sigue con los permisos
     * viejos hasta que la caché caduque sola — y nadie entiende por qué.
     */
    private function olvidarCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
