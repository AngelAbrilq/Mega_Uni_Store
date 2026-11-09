<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plataforma\GuardarPermisoRequest;
use App\Models\Permiso;
use App\Models\Rol;
use App\Support\CatalogoPermisos;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * SA-PER-001 · Catálogo de permisos de la plataforma (/sistema/permisos).
 *
 *   · Los del sistema (los usa el código) se documentan pero no se renombran
 *     ni se borran: cambiarles el nombre apagaría el @can que los pregunta.
 *   · Los personalizados se crean, editan y borran. Al borrar, spatie quita
 *     la relación con los roles y quien lo tenía lo pierde de inmediato.
 *   · Asignarlos y quitarlos de un rol se hace en Roles (RolController).
 */
class PermisoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:sistema.superadmin')];
    }

    public function index(Request $request)
    {
        $modulo = (string) $request->query('modulo', '');
        $rolId  = (int) $request->query('rol', 0);
        $rol    = $rolId ? Rol::query()->find($rolId) : null;

        $permisos = Permiso::query()
            ->withCount('roles')
            ->when($modulo !== '', fn ($q) => $q->where('module', $modulo))
            ->when($rol, fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereKey($rol->id)))
            ->orderBy('module')->orderBy('name')
            ->get()
            ->groupBy(fn (Permiso $p) => $p->modulo());

        return view('sistema.permisos.index', [
            'permisos' => $permisos,
            'modulos'  => Permiso::query()->distinct()->orderBy('module')->pluck('module'),
            'roles'    => Rol::query()->whereNull('empresa_id')->orderBy('name')->get(['id', 'name']),
            'modulo'   => $modulo,
            'rol'      => $rol,
            'total'    => Permiso::count(),
            'propios'  => Permiso::where('is_system', false)->count(),
        ]);
    }

    public function create()
    {
        return view('sistema.permisos.create', ['permiso' => null, 'modulos' => $this->modulos()]);
    }

    public function store(GuardarPermisoRequest $request)
    {
        $permiso = Permiso::create($request->validated() + ['guard_name' => 'web', 'is_system' => false]);

        /* Un permiso que nadie tiene no se puede repartir: el formulario de
           roles solo deja dar lo que uno ya tiene (H-F06). Por eso nace en el
           Superadministrador y, si se pide, en el Administrador de cada negocio. */
        $roles = ['Superadministrador'];

        if ($request->boolean('para_administrador', true)) {
            $roles[] = 'Administrador';
        }

        Rol::query()->whereNull('empresa_id')->whereIn('name', $roles)->get()
            ->each(fn (Rol $r) => $r->givePermissionTo($permiso));

        $this->olvidarCache();

        return redirect()->route('sistema.permisos.index')
            ->with('ok', "Permiso {$permiso->name} creado. Asígnalo a los roles desde Roles.");
    }

    public function edit(Permiso $permiso)
    {
        return view('sistema.permisos.edit', [
            'permiso' => $permiso->load('roles:id,name,empresa_id'),
            'modulos' => $this->modulos(),
        ]);
    }

    public function update(GuardarPermisoRequest $request, Permiso $permiso)
    {
        // Del sistema: solo la descripción. El nombre lo usa el código.
        $datos = $permiso->is_system
            ? ['description' => $request->validated('description')]
            : $request->validated();

        $permiso->update($datos);
        $this->olvidarCache();

        return redirect()->route('sistema.permisos.index')->with('ok', "Permiso {$permiso->name} actualizado.");
    }

    public function destroy(Permiso $permiso)
    {
        if ($permiso->is_system) {
            throw ValidationException::withMessages([
                'permiso' => 'Ese permiso lo usa el sistema: no se puede borrar.',
            ]);
        }

        $roles = $permiso->roles()->count();
        $permiso->delete();
        $this->olvidarCache();

        return redirect()->route('sistema.permisos.index')->with('ok', "Permiso {$permiso->name} eliminado"
            . ($roles ? " y retirado de {$roles} " . ($roles === 1 ? 'rol' : 'roles') . '.' : '.'));
    }

    /** @return array<string, string> */
    private function modulos(): array
    {
        return Permiso::query()->distinct()->orderBy('module')->pluck('module')
            ->reject(fn ($m) => $m === 'sistema')
            ->mapWithKeys(fn ($m) => [$m => CatalogoPermisos::modulo($m)])
            ->all();
    }

    private function olvidarCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
