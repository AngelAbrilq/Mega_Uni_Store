<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardaImagen;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
                ->orWhere('email', 'like', "%{$q}%")))
            ->when($request->query('rol'), fn ($c, $rol) => $c->role($rol))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::visibles()->orderBy('name')->pluck('name'),
            'q'     => $q,
            'rol'   => (string) $request->query('rol', ''),
        ]);
    }

    public function create()
    {
        return view('users.create', $this->listas());
    }

    public function store(Request $request)
    {
        app(\App\Services\LimiteService::class)->exigir('usuarios');

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles'    => ['required', 'array', 'min:1'],
            'roles.*'  => [Rule::in($this->rolesAsignables())],
        ] + $this->reglasImagen('foto'), $this->mensajes() + $this->mensajesImagen('foto'));

        $usuario = new User();
        $usuario->name              = $data['name'];
        $usuario->email             = $data['email'];
        $usuario->password          = Hash::make($data['password']);
        $usuario->email_verified_at = now();
        $usuario->avatar_url        = $this->guardarImagen($request, 'usuarios', null, 'foto');
        $usuario->save();

        $usuario->syncRoles($data['roles']);

        return redirect()->route('users.index')
            ->with('success', 'Usuario «' . $usuario->name . '» creado.');
    }

    public function show(User $user)
    {
        $user->load('roles.permissions');

        return view('users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $user->load('roles:id,name');

        return view('users.edit', array_merge($this->listas(), ['user' => $user]));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles'    => ['required', 'array', 'min:1'],
            'roles.*'  => [Rule::in($this->rolesAsignables())],
        ] + $this->reglasImagen('foto'), $this->mensajes() + $this->mensajesImagen('foto'));

        // Nadie puede quitarse a sí mismo el rol con el que administra:
        // si lo hiciera, quedaría sin acceso y sin forma de recuperarlo.
        if ($user->id === $request->user()->id
            && $user->hasRole('Superadministrador')
            && ! in_array('Superadministrador', $data['roles'], true)) {
            return back()
                ->withInput()
                ->with('error', 'No puedes quitarte a ti mismo el rol de Superadministrador.');
        }

        $user->name  = $data['name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $foto = $this->guardarImagen($request, 'usuarios', $user->avatar_url, 'foto');

        if ($foto !== false) {
            $user->avatar_url = $foto;
        }

        $user->save();
        $user->syncRoles($data['roles']);

        return redirect()->route('users.index')
            ->with('success', 'Usuario «' . $user->name . '» actualizado.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta mientras la estás usando.');
        }

        // Siempre debe quedar al menos un superadministrador.
        // `deLaEmpresa`: lo que importa es que ESTE negocio no se quede
        // sin dueño. Que el vecino tenga tres superadministradores no
        // salva a este de quedarse sin ninguno.
        if ($user->hasRole('Superadministrador')
            && User::role('Superadministrador')->deLaEmpresa()->count() <= 1) {
            return back()->with('error', 'Este es el único superadministrador: el sistema quedaría sin dueño.');
        }

        $nombre = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario «' . $nombre . '» eliminado.');
    }

    /* ───────────────────────── Apoyo ───────────────────────── */

    /**
     * Los nombres de rol que este negocio puede asignar.
     *
     * Antes la regla era `exists:roles,name`, que solo comprobaba que el
     * rol existiera EN ALGÚN LADO. Bastaba con mandar a mano el nombre del
     * rol de otro cliente —«Estilista»— para asignárselo a un usuario
     * propio. Con la lista cerrada, un nombre que no sea de este negocio
     * simplemente no pasa la validación.
     *
     * @return array<int, string>
     */
    private function rolesAsignables(): array
    {
        return Role::visibles()->pluck('name')->all();
    }

    /** @return array<string, mixed> */
    private function listas(): array
    {
        return [
            // Se traen los permisos, no solo el conteo: la tarjeta del rol
            // los muestra al pasar el mouse por encima.
            // `visibles`: los del sistema más los de este negocio. Sin
            // esto, el desplegable de roles le ofrecería al cajero de la
            // ferretería el «Estilista» que creó una peluquería.
            'roles' => Role::visibles()
                ->with('permissions:id,name')
                ->withCount('permissions')
                ->orderBy('name')
                ->get(),
        ];
    }

    /** @return array<string, string> */
    private function mensajes(): array
    {
        return [
            'name.required'      => 'Escribe el nombre completo.',
            'email.required'     => 'El correo es obligatorio.',
            'email.email'        => 'Escribe un correo electrónico válido.',
            'email.lowercase'    => 'El correo debe ir en minúsculas.',
            'email.unique'       => 'Ese correo ya tiene una cuenta.',
            'password.required'  => 'Define una contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'roles.required'     => 'Un usuario debe tener al menos un rol.',
            'roles.min'          => 'Un usuario debe tener al menos un rol.',
        ];
    }
}
