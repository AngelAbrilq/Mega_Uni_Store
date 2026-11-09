<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use App\Services\SesionService;
use App\Support\Jerarquia;
use Illuminate\Http\Request;

/**
 * TBL-SES-001 · Cerrar sesiones a distancia (RN-41).
 *
 *   · Cada persona ve y cierra las suyas desde «Mi perfil».
 *   · Un administrador (`usuarios.editar`) cierra todas las de alguien de
 *     su negocio que no tenga más permisos que él (misma regla que editar).
 *
 * Cerrar no borra la sesión de Laravel: la marca inactiva, y ControlDeSesion
 * saca a esa persona en su siguiente petición.
 */
class SesionController extends Controller
{
    public function __construct(private SesionService $sesiones)
    {
    }

    public function destroy(Request $request, UserSession $sesion)
    {
        abort_unless($sesion->user_id === $request->user()->id, 404);

        $esta = $this->sesiones->actual($request)?->id === $sesion->id;
        $this->sesiones->cerrar($sesion, UserSession::REVOCADA, $request->user()->id);

        if ($esta) {
            return redirect()->route('login');
        }

        return back()->with('success', "Sesión de {$sesion->device} cerrada.");
    }

    public function cerrarOtras(Request $request)
    {
        $cuantas = $this->sesiones->cerrarTodas(
            $request->user(),
            $this->sesiones->actual($request)?->id,
            $request->user()->id,
        );

        return back()->with('success', $cuantas
            ? "Se cerraron {$cuantas} " . ($cuantas === 1 ? 'sesión' : 'sesiones') . ' en otros dispositivos.'
            : 'No tenías sesiones abiertas en otros dispositivos.');
    }

    public function cerrarDeUsuario(Request $request, User $user)
    {
        $actor = $request->user();

        abort_unless($actor->can('usuarios.editar'), 403);
        abort_if($user->id === $actor->id, 403, 'Para las tuyas usa «Mi perfil».');
        abort_if(Jerarquia::esSuperior($actor, $user), 403);

        $cuantas = $this->sesiones->cerrarTodas($user, null, $actor->id);

        return back()->with('success', $cuantas
            ? "{$user->name} sale de {$cuantas} " . ($cuantas === 1 ? 'dispositivo' : 'dispositivos') . ' en su siguiente clic.'
            : "{$user->name} no tenía sesiones abiertas.");
    }
}
