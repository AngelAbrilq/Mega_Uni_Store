<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decide en qué idioma se pinta esta petición.
 *
 * El orden importa y es de lo más específico a lo más general:
 *
 *   1. La sesión — el usuario acaba de tocar el selector de idioma.
 *      Manda sobre todo lo demás porque es la última cosa que pidió.
 *   2. Su perfil — cada empleado guarda el suyo. Un negocio puede tener
 *      un cajero que prefiere inglés sin que eso afecte a los demás.
 *   3. La configuración del negocio — el idioma por defecto de la tienda.
 *   4. config/app.php — el último respaldo, para que nunca quede sin valor.
 *
 * Se aprovecha el mismo paso para fijar la zona horaria: si no se hace, las
 * fechas se graban en UTC y el turno de caja de las 8 de la noche aparece
 * al día siguiente.
 */
class EstableceIdioma
{
    /** Los únicos idiomas que el sistema sabe hablar. */
    public const DISPONIBLES = [
        'es' => 'Español',
        'en' => 'English',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $idioma = $this->resolver($request);

        App::setLocale($idioma);

        // La zona horaria vive en la misma pantalla de Configuración, y si
        // se deja en UTC todas las horas del sistema salen corridas.
        $zona = Setting::obtener('regional.zona_horaria', 'America/Bogota');

        if ($zona && in_array($zona, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $zona]);
            date_default_timezone_set($zona);
        }

        // Que «hace 3 minutos» también salga traducido.
        Date::setLocale($idioma);

        return $next($request);
    }

    private function resolver(Request $request): string
    {
        $candidatos = [
            $request->session()->get('idioma'),
            $request->user()?->locale,
            Setting::obtener('regional.idioma'),
            config('app.locale'),
        ];

        foreach ($candidatos as $c) {
            if ($c && array_key_exists($c, self::DISPONIBLES)) {
                return $c;
            }
        }

        return 'es';
    }

    /** ¿Es un idioma que sabemos hablar? Lo usa el controlador al validar. */
    public static function valido(?string $idioma): bool
    {
        return $idioma !== null && array_key_exists($idioma, self::DISPONIBLES);
    }
}
