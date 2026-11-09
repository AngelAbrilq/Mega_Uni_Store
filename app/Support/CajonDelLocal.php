<?php

namespace App\Support;

use App\Models\Tienda;

/**
 * Cómo abre el cajón el local activo (una sola fuente para la vista del POS
 * y para PosController).
 *
 *   server → PrinterService en el servidor (instalación local / Laragon)
 *   agent  → el navegador le habla al agente del PC de caja (nube)
 *   none   → sin cajón: el POS no muestra el botón
 */
final class CajonDelLocal
{
    /**
     * @return array{modo:string, activo:bool, agente:?array{url:string, token:string}}
     */
    public static function configuracion(?Tienda $tienda = null): array
    {
        $tienda ??= Contexto::tienda();
        $modo     = $tienda?->drawer_mode ?? Tienda::CAJON_SERVIDOR;

        return match ($modo) {
            Tienda::CAJON_AGENTE => [
                'modo'   => $modo,
                'activo' => true,
                'agente' => [
                    'url'   => 'http://127.0.0.1:' . (int) ($tienda->agent_port ?: 9123),
                    'token' => $tienda->asegurarTokenDeAgente(),
                ],
            ],
            Tienda::CAJON_SERVIDOR => [
                'modo'   => $modo,
                'activo' => config('hardware.cajon.driver', 'none') !== 'none',
                'agente' => null,
            ],
            default => ['modo' => Tienda::CAJON_NINGUNO, 'activo' => false, 'agente' => null],
        };
    }

    public static function modo(?Tienda $tienda = null): string
    {
        return self::configuracion($tienda)['modo'];
    }
}
