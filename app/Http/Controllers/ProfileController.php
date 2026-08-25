<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardaImagen;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use GuardaImagen;

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Guarda nombre, correo y foto de perfil.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        $usuario->fill($request->datosDelPerfil());

        if ($usuario->isDirty('email')) {
            $usuario->email_verified_at = null;
        }

        // La foto va al disco; en la columna solo queda la ruta.
        $foto = $this->guardarImagen($request, 'usuarios', $usuario->avatar_url, 'foto');

        if ($foto !== false) {
            $usuario->avatar_url = $foto;
        }

        $usuario->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
