<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EstableceIdioma;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Cambio de idioma desde el menú de usuario.
 *
 * Guarda en dos sitios a propósito:
 *
 *   - En la sesión, para que el cambio se vea en la siguiente pantalla
 *     aunque el usuario no esté autenticado (pantalla de ingreso).
 *   - En el perfil, para que la próxima vez que entre desde otro
 *     computador el sistema ya lo recuerde.
 *
 * Es POST, no GET: cambiar el idioma modifica el estado del usuario, y una
 * dirección que cambia algo con solo abrirla se dispara sola con cualquier
 * precarga del navegador.
 */
class IdiomaController extends Controller
{
    /** Se consulta una vez por petición, no una vez por llamada. */
    private static ?bool $hayColumna = null;

    public function cambiar(Request $request)
    {
        $idioma = (string) $request->input('idioma');

        if (! EstableceIdioma::valido($idioma)) {
            return back();
        }

        // La sesión primero: es lo que de verdad hace que el idioma cambie
        // ya mismo. Lo del perfil es memoria para la próxima vez, y no debe
        // poder impedir el cambio si falla.
        $request->session()->put('idioma', $idioma);

        if ($usuario = $request->user()) {
            $this->recordarEnPerfil($usuario, $idioma);
        }

        // Se vuelve a donde estaba, no al panel: cambiar el idioma no debería
        // hacerle perder al usuario la pantalla en la que estaba trabajando.
        return back();
    }

    /**
     * Deja el idioma escrito en el perfil, si se puede.
     *
     * Se comprueba que la columna exista antes de escribir. El motivo es
     * real y ya pasó: los archivos del sistema se actualizan copiándolos, y
     * la migración se corre después —a veces bastante después—. En esa
     * ventana, `users.locale` todavía no existe y un `save()` a secas tumba
     * la petición con un error 500 por algo tan menor como elegir idioma.
     *
     * Con esta comprobación, el idioma igual cambia (vive en la sesión) y
     * lo único que se pierde es que quede recordado para la próxima entrada.
     * Cuando la migración corra, empieza a guardarse solo.
     *
     * `saveQuietly` y no `save`: guardar una preferencia de idioma no tiene
     * por qué aparecer en la auditoría del negocio, que es para saber quién
     * tocó los precios y el inventario.
     */
    private function recordarEnPerfil(Authenticatable $usuario, string $idioma): void
    {
        // El cliente de la tienda pública entra por otro guard y no tiene
        // esta columna: no es un usuario del panel.
        if (! $usuario instanceof \Illuminate\Database\Eloquent\Model) {
            return;
        }

        try {
            if (self::$hayColumna === null) {
                self::$hayColumna = Schema::hasColumn($usuario->getTable(), 'locale');
            }

            if (! self::$hayColumna) {
                return;
            }

            $usuario->forceFill(['locale' => $idioma])->saveQuietly();
        } catch (\Throwable $e) {
            // Se deja constancia en el log, pero no se le muestra al usuario:
            // desde su lado el idioma cambió, que es lo que pidió.
            report($e);
        }
    }
}
