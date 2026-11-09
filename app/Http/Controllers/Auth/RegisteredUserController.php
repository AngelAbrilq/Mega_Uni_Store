<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use App\Models\Rol as Role;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'name.required'      => 'Necesitamos tu nombre completo.',
            'email.required'     => 'El correo electrónico es obligatorio.',
            'email.email'        => 'Escribe un correo electrónico válido.',
            'email.lowercase'    => 'El correo debe ir en minúsculas.',
            'email.unique'       => 'Ese correo ya tiene una cuenta registrada.',
            'password.required'  => 'Define una contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        /**
         * Rol por defecto de quien se registra por su cuenta.
         * "Vendedor" puede entrar al panel, consultar el catálogo y
         * gestionar clientes; nada más. Un administrador le amplía el rol
         * después desde Administración › Usuarios.
         */
        if (Role::where('name', 'Vendedor')->exists()) {
            $user->assignRole('Vendedor');
        }

        event(new Registered($user));

        Auth::login($user);

        // Dispara la animación de bienvenida (variante "cuenta creada").
        $request->session()->flash('mus_welcome', $user->name);
        $request->session()->flash('mus_welcome_new', true);

        return redirect(route('dashboard', absolute: false));
    }
}
