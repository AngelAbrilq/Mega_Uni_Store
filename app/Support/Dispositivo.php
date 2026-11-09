<?php

namespace App\Support;

/**
 * Lee el User-Agent y lo dice en palabras: «Chrome en Windows», «Safari en
 * iPhone». No es un detector exhaustivo —ni tiene que serlo—: es para que
 * una persona reconozca su propio equipo en la lista de sesiones.
 */
final class Dispositivo
{
    /** @return array{0: string, 1: string} [descripción, tipo] */
    public static function describir(?string $agente): array
    {
        $ua = (string) $agente;

        $navegador = match (true) {
            str_contains($ua, 'Edg/')                              => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser')                    => 'Samsung Internet',
            str_contains($ua, 'Firefox/')                          => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/')                           => 'Safari',
            $ua === ''                                             => 'Desconocido',
            default                                                => 'Navegador',
        };

        $sistema = match (true) {
            str_contains($ua, 'iPhone')     => 'iPhone',
            str_contains($ua, 'iPad')       => 'iPad',
            str_contains($ua, 'Android')    => 'Android',
            str_contains($ua, 'Windows')    => 'Windows',
            str_contains($ua, 'Mac OS X')   => 'macOS',
            str_contains($ua, 'CrOS')       => 'ChromeOS',
            str_contains($ua, 'Linux')      => 'Linux',
            default                         => null,
        };

        $tipo = match (true) {
            str_contains($ua, 'iPad') || (str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile')) => 'tableta',
            str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone')                                    => 'celular',
            default                                                                                        => 'escritorio',
        };

        return [$sistema ? "{$navegador} en {$sistema}" : $navegador, $tipo];
    }
}
