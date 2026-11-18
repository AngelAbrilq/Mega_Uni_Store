<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tienda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hablarle al agente de caja DESDE EL SERVIDOR (RN-28).
 *
 * El camino normal es navegador → agente: el servidor puede estar en la nube
 * y su 127.0.0.1 no es el PC del mostrador. Pero cuando servidor y caja son
 * la misma máquina (instalación local, `php artisan serve`), el servidor sí
 * alcanza al agente, y sirve de relevo si el navegador tiene bloqueada la red
 * local (permiso denegado, extensión, Brave). Es el mismo camino que usa
 * `php artisan cajon:probar-recibo`, y por eso vive aquí una sola vez.
 */
final class AgenteDeCaja
{
    /** Dirección del agente de un local. Siempre loopback: no es configurable a propósito. */
    public static function url(Tienda $tienda): string
    {
        return 'http://127.0.0.1:' . (int) ($tienda->agent_port ?: 9123);
    }

    /**
     * ¿La petición viene de esta misma máquina?
     *
     * Solo entonces «el 127.0.0.1 del servidor» y «el PC de caja» son lo
     * mismo. Con el sistema en la nube el relevo se niega: de lo contrario
     * el recibo de un local saldría (o fallaría) en el servidor.
     */
    public static function esLaMismaMaquina(Request $request): bool
    {
        return in_array($request->ip(), ['127.0.0.1', '::1'], true);
    }

    /**
     * Entrega bytes ESC/POS al agente del local.
     *
     * Nunca lanza: que la impresora falle no puede tumbar la pantalla de la venta.
     *
     * @return array{impreso:bool, estado:int, detalle:?string}
     */
    public static function imprimir(Tienda $tienda, string $bytes): array
    {
        try {
            $respuesta = Http::timeout(5)
                ->withBody(
                    json_encode(['token' => $tienda->asegurarTokenDeAgente(), 'datos' => base64_encode($bytes)], JSON_THROW_ON_ERROR),
                    'text/plain',
                )
                ->post(self::url($tienda) . '/imprimir');
        } catch (Throwable $e) {
            // El motivo técnico va a la bitácora; a la pantalla, lo que el cajero puede revisar.
            Log::warning('El agente de caja no contesta.', ['tienda' => $tienda->id, 'motivo' => $e->getMessage()]);

            return ['impreso' => false, 'estado' => 0, 'detalle' => 'El agente de caja no contesta en ' . self::url($tienda)];
        }

        return [
            'impreso' => $respuesta->json('impreso') === true,
            'estado'  => $respuesta->status(),
            'detalle' => $respuesta->json('detalle') ?? $respuesta->json('error'),
        ];
    }
}
