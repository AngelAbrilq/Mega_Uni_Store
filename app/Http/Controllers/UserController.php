<?php

namespace App\Http\Controllers;

use App\Support\Jerarquia;
use App\Http\Requests\Admin\GuardarUsuarioRequest;
use App\Http\Controllers\Concerns\GuardaImagen;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Contexto;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use App\Models\Rol as Role;

/**
 * Gestión del equipo de trabajo: quién entra al sistema y con qué rol.
 *
 * Es la pieza que faltaba para que spatie/laravel-permission sirviera de
 * algo: sin ella los roles existían en la base de datos pero no había
 * forma de asignarlos desde la aplicación.
 */
class UserController extends Controller implements HasMiddleware
{
    use GuardaImagen;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:usuarios.ver',      only: ['index', 'show']),
            new Middleware('permission:usuarios.crear',    only: ['create', 'store']),
            new Middleware('permission:usuarios.editar',   only: ['edit', 'update']),
            new Middleware('permission:usuarios.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->deLaEmpresa()
            ->with('roles:id,name')
            ->when($q !== '', fn ($c) => $c->where(fn ($s) => $s
                ->where('name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->when($request->query('estado') === 'activos', fn ($c) => $c->where('is_active', true))
            ->when($request->query('estado') === 'inactivos', fn ($c) => $c->where('is_active', false))
            ->when($request->query('rol'), fn ($c, $rol) => $c->role($rol))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::visibles()->orderBy('name')->pluck('name'),
            'q'     => $q,
            'rol'   => (string) $request->query('rol', ''),
            'estado' => (string) $request->query('estado', ''),
        ]);
    }

    public function create()
    {
        return view('users.create', $this->listas());
    }

    public function store(GuardarUsuarioRequest $request)
    {
        // Tope del plan y jerarquía (H-F06): GuardarUsuarioRequest, antes de validar.
        $data = $request->validated();

        $usuario = DB::transaction(function () use ($request, $data) {
            $usuario = new User();
            $usuario->name              = $data['name'];
            $usuario->last_name         = $data['last_name'] ?? null;
            $usuario->email             = $data['email'];
            $usuario->phone             = $data['phone'] ?? null;
            $usuario->is_active         = $data['is_active'] ?? true;
            $usuario->password          = Hash::make($data['password']);
            $usuario->email_verified_at = now();
            $usuario->avatar_url        = $this->guardarImagen($request, 'usuarios', null, 'foto');
            $usuario->save();

            $usuario->syncRoles($data['roles']);
            $this->sincronizarTiendas($usuario, $data);

            return $usuario;
        });

        return redirect()->route('users.index')
            ->with('success', 'Usuario «' . $usuario->name . '» creado.');
    }

    public function show(User $user)
    {
        $user->load('roles.permissions', 'tiendas');

        // TBL-SES-001: dónde está conectado ahora mismo.
        $sesiones = \App\Models\UserSession::query()->vigentes()
            ->where('user_id', $user->id)->orderByDesc('last_activity_at')->get();

        return view('users.show', compact('user', 'sesiones'));
    }

    public function edit(User $user)
    {
        $this->exigirQueNoSeaSuperior($user);

        $user->load('roles:id,name', 'tiendas');

        return view('users.edit', array_merge($this->listas(), ['user' => $user]));
    }

    public function update(GuardarUsuarioRequest $request, User $user)
    {
        $data = $request->validated();

        // Nadie puede quitarse a sí mismo el rol con el que administra:
        // si lo hiciera, quedaría sin acceso y sin forma de recuperarlo.
        if ($user->id === $request->user()->id
            && $user->hasRole('Superadministrador')
            && ! in_array('Superadministrador', $data['roles'], true)) {
            return back()
                ->withInput()
                ->with('error', 'No puedes quitarte a ti mismo el rol de Superadministrador.');
        }

        $activo = $data['is_active'] ?? $user->is_active;

        // Desactivarse a uno mismo es cerrarse la puerta sin llave para volver.
        if ($user->id === $request->user()->id && ! $activo) {
            return back()->withInput()->with('error', 'No puedes desactivar tu propia cuenta mientras la estás usando.');
        }

        if (! $activo && $user->is_active && $this->esElUltimoSuperadministrador($user)) {
            return back()->withInput()->with('error', 'Es el único superadministrador activo: el negocio quedaría sin dueño.');
        }

        $user->name      = $data['name'];
        $user->last_name = $data['last_name'] ?? null;
        $user->email     = $data['email'];
        $user->phone     = $data['phone'] ?? null;
        $user->is_active = $activo;

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $foto = $this->guardarImagen($request, 'usuarios', $user->avatar_url, 'foto');

        if ($foto !== false) {
            $user->avatar_url = $foto;
        }

        DB::transaction(function () use ($user, $data) {
            $user->save();
            $user->syncRoles($data['roles']);
            $this->sincronizarTiendas($user, $data);
        });

        return redirect()->route('users.index')
            ->with('success', 'Usuario «' . $user->name . '» actualizado.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->exigirQueNoSeaSuperior($user);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta mientras la estás usando.');
        }

        // Siempre debe quedar al menos un superadministrador.
        // `deLaEmpresa`: lo que importa es que ESTE negocio no se quede
        // sin dueño. Que el vecino tenga tres superadministradores no
        // salva a este de quedarse sin ninguno.
        if ($this->esElUltimoSuperadministrador($user)) {
            return back()->with('error', 'Este es el único superadministrador: el sistema quedaría sin dueño.');
        }

        // H-F04: baja lógica. Sus ventas, compras, turnos y devoluciones
        // siguen diciendo quién las hizo; él ya no puede entrar.
        $nombre = $user->nombre_completo;

        DB::transaction(function () use ($user) {
            $user->forceFill(['is_active' => false])->save();
            $user->delete();
        });

        return redirect()->route('users.index')
            ->with('success', 'Usuario «' . $nombre . '» dado de baja. Su historial se conserva.');
    }

    /* ───────────────────────── Apoyo ───────────────────────── */

    /**
     * Tampoco se edita ni se borra a alguien con más permisos que uno:
     * sería la forma indirecta de quitarle el acceso o cambiarle la clave.
     */
    private function exigirQueNoSeaSuperior(User $objetivo): void
    {
        abort_if(
            Jerarquia::esSuperior(auth()->user(), $objetivo),
            403,
            'No puedes modificar a un usuario con más permisos que tú.'
        );
    }

    /**
     * ¿Quitarle el acceso a este usuario dejaría al negocio sin superadministrador activo?
     *
     * `deLaEmpresa`: lo que importa es que ESTE negocio no se quede sin
     * dueño. Que el vecino tenga tres no salva a este de quedarse sin ninguno.
     */
    private function esElUltimoSuperadministrador(User $user): bool
    {
        return $user->hasRole('Superadministrador')
            && User::role('Superadministrador')->deLaEmpresa()->activos()->count() <= 1;
    }

    /**
     * SA-USR-002 · Locales de ESTE negocio donde trabaja el usuario.
     *
     * Solo toca los locales del negocio activo: quien pertenece a dos
     * negocios no pierde los del otro porque lo editaron desde este.
     * Sin locales marcados = puede trabajar en todos (ver User::tiendasEn).
     *
     * @param array<string, mixed> $data
     */
    private function sincronizarTiendas(User $user, array $data): void
    {
        if (! array_key_exists('tiendas', $data) && empty($data['tiendas_presentes'])) {
            return;
        }

        $empresaId = Contexto::empresaId();

        if (! $empresaId) {
            return;
        }

        $deEsteNegocio = Tienda::where('empresa_id', $empresaId)->pluck('id');

        $user->tiendas()->detach($deEsteNegocio);
        $user->tiendas()->attach(array_map('intval', $data['tiendas'] ?? []));
    }

    /** @return array<string, mixed> */
    private function listas(): array
    {
        return [
            'tiendas' => Tienda::where('empresa_id', Contexto::empresaId())
                ->orderByDesc('es_principal')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'activa']),
            // Se traen los permisos, no solo el conteo: la tarjeta del rol
            // los muestra al pasar el mouse por encima.
            // `visibles`: los del sistema más los de este negocio. Sin
            // esto, el desplegable de roles le ofrecería al cajero de la
            // ferretería el «Estilista» que creó una peluquería.
            // H-F06: solo los roles que este usuario puede repartir.
            'roles' => Jerarquia::rolesAlAlcance(auth()->user())
                ->each(fn (Role $rol) => $rol->permissions_count = $rol->permissions->count()),
        ];
    }
}
