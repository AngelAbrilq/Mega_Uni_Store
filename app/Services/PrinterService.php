<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Contexto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Throwable;

/**
 * Puente entre el POS y el hardware del mostrador.
 *
 * Regla de oro de este servicio: NADA de lo que pase aquí adentro puede
 * tumbar una venta. El dinero ya cambió de manos y la fila sigue esperando;
 * que el cajón esté desconectado es un problema del cajón, no de la venta.
 * Por eso todo método público devuelve datos, nunca excepciones.
 *
 * ── Sobre «saber si el cajón está conectado» ────────────────────────────
 * Hay que separar dos preguntas que parecen la misma y no lo son:
 *
 *   1. ¿El DESTINO responde?  (existe la cola de impresión / la IP contesta)
 *      Se puede saber con cualquier driver: se manda un ESC @ inofensivo
 *      —reinicia la impresora, no imprime ni abre nada— y se mira si el
 *      envío falló.
 *
 *   2. ¿El CAJÓN está enchufado y abierto o cerrado?
 *      Eso lo dice el comando DLE EOT 2, que devuelve el estado del pin 3
 *      del conector RJ11. Pero requiere leer de vuelta, y por la cola de
 *      impresión de Windows NO se puede leer: es un canal de una sola vía.
 *      Con driver `windows` esta pregunta es, físicamente, irrespondible;
 *      el servicio devuelve null y no inventa.
 *
 * Traducción práctica: con el BT-100U por USB puedes saber que el adaptador
 * está instalado y aceptando datos, no que el cable del cajón esté puesto.
 * Si necesitas certeza real, el camino es el driver `network`.
 */
class PrinterService
{
    /** Segundos que se recuerda el último sondeo, para no golpear la cola. */
    private const CACHE_TTL = 10;

    /**
     * Manda el pulso de apertura al cajón monedero.
     *
     * @return bool true si el pulso salió; false si no hay hardware
     *              configurado o el aparato no respondió.
     */
    public function abrirCajon(): bool
    {
        $cfg = config('hardware.cajon');

        if (($cfg['driver'] ?? 'none') === 'none') {
            return false;
        }

        return $this->conversar($cfg, function (Printer $printer) use ($cfg) {
            // pulse(pin, onMs, offMs). El adaptador BT-100U se presenta ante
            // Windows como impresora ESC/POS: recibe el mismo comando que
            // usaría la salida RJ11 de una térmica, no necesita driver aparte.
            $printer->pulse(
                $cfg['pin']    ?? 0,
                $cfg['on_ms']  ?? 120,
                $cfg['off_ms'] ?? 240,
            );
        }, 'abrir el cajón monedero');
    }

    /**
     * Diagnóstico del cajón, sin abrirlo.
     *
     * @param  bool  $forzar  salta la caché de 10 s (botón «Volver a probar»)
     * @return array{
     *     driver:string, destino:?string, conectado:bool, cajon_abierto:?bool,
     *     nivel:string, mensaje:string, detalle:?string
     * }
     *   nivel: apagado | ok | abierto | caido
     */
    public function estado(bool $forzar = false): array
    {
        $cfg    = config('hardware.cajon');
        $driver = $cfg['driver'] ?? 'none';

        if ($driver === 'none') {
            return [
                'driver'        => 'none',
                'destino'       => null,
                'conectado'     => false,
                'cajon_abierto' => null,
                'nivel'         => 'apagado',
                'mensaje'       => 'No hay cajón configurado.',
                'detalle'       => 'Define CASH_DRAWER_DRIVER en el archivo .env.',
            ];
        }

        $sondear = match ($driver) {
            'network' => fn () => $this->sondearRed($cfg),
            'serial'  => fn () => $this->sondearSerial($cfg),
            default   => fn () => $this->sondearWindows($cfg),
        };

        if ($forzar) {
            Cache::forget($this->claveCache());

            return tap($sondear(), fn ($r) => Cache::put($this->claveCache(), $r, self::CACHE_TTL));
        }

        return Cache::remember($this->claveCache(), self::CACHE_TTL, $sondear);
    }

    /* ================================================================== */
    /*  Sondeos                                                           */
    /* ================================================================== */

    /**
     * Driver `windows`: solo se puede comprobar que la cola de impresión
     * acepta datos. ESC @ (initialize) es el comando más inocuo que existe:
     * reinicia el buffer de la impresora y no imprime ni un milímetro de
     * papel. Si la cola no existe o está sin conexión, WindowsPrintConnector
     * revienta al cerrar, que es justo lo que queremos detectar.
     */
    private function sondearWindows(array $cfg): array
    {
        $destino = $this->destino($cfg);

        $ok = $this->conversar(
            $cfg,
            fn (Printer $printer) => $printer->initialize(),
            'sondear el cajón monedero',
        );

        return [
            'driver'        => 'windows',
            'destino'       => $destino,
            'conectado'     => $ok,
            // Irrespondible por la cola de Windows. Ver la nota de la clase.
            'cajon_abierto' => null,
            'nivel'         => $ok ? 'ok' : 'caido',
            'mensaje'       => $ok
                ? "«{$destino}» responde."
                : "«{$destino}» no responde.",
            'detalle'       => $ok
                ? 'El adaptador acepta comandos. Windows no permite leer si el '
                . 'cable del cajón está puesto: eso solo se confirma abriéndolo.'
                : 'Revisa que la impresora esté compartida con ese nombre exacto '
                . 'y que el usuario del servicio de Apache la vea.',
        ];
    }

    /**
     * Driver `network`: aquí sí hay canal de vuelta, así que se puede
     * preguntar de verdad por el pin del cajón.
     *
     * DLE EOT 2 (0x10 0x04 0x02) devuelve un byte de estado; el bit 0x04
     * es el nivel del pin 3 del RJ11, que es el que lee el microinterruptor
     * del cajón. En la mayoría de cajones HIGH = abierto, pero hay modelos
     * cableados al revés: para esos está `estado_invertido` en la config.
     */
    private function sondearRed(array $cfg): array
    {
        $destino = $this->destino($cfg);
        $errno   = 0;
        $error   = '';

        $socket = @fsockopen($cfg['host'], (int) $cfg['port'], $errno, $error, $cfg['timeout'] ?? 3);

        if ($socket === false) {
            Log::warning('El cajón de red no contesta.', ['destino' => $destino, 'motivo' => $error]);

            return [
                'driver'        => 'network',
                'destino'       => $destino,
                'conectado'     => false,
                'cajon_abierto' => null,
                'nivel'         => 'caido',
                'mensaje'       => "{$destino} no contesta.",
                'detalle'       => $error !== '' ? $error : 'Sin ruta hacia la impresora.',
            ];
        }

        try {
            stream_set_timeout($socket, $cfg['timeout'] ?? 3);
            fwrite($socket, "\x10\x04\x02");

            $byte = fread($socket, 1);
            $meta = stream_get_meta_data($socket);

            if ($meta['timed_out'] || $byte === '' || $byte === false) {
                // La impresora existe pero no habla de vuelta: hay térmicas
                // genéricas que ignoran DLE EOT. El destino sirve para
                // mandar el pulso, solo que a ciegas.
                return [
                    'driver'        => 'network',
                    'destino'       => $destino,
                    'conectado'     => true,
                    'cajon_abierto' => null,
                    'nivel'         => 'ok',
                    'mensaje'       => "{$destino} responde.",
                    'detalle'       => 'La impresora no reporta el estado del cajón '
                                     . '(no implementa DLE EOT). El pulso sí le llega.',
                ];
            }

            $alto     = (bool) (ord($byte) & 0x04);
            $abierto  = ($cfg['estado_invertido'] ?? false) ? ! $alto : $alto;

            return [
                'driver'        => 'network',
                'destino'       => $destino,
                'conectado'     => true,
                'cajon_abierto' => $abierto,
                'nivel'         => $abierto ? 'abierto' : 'ok',
                'mensaje'       => $abierto
                    ? 'El cajón está ABIERTO.'
                    : 'Cajón conectado y cerrado.',
                'detalle'       => $abierto
                    ? 'Ciérralo antes de seguir cobrando.'
                    : null,
            ];
        } catch (Throwable $e) {
            return [
                'driver'        => 'network',
                'destino'       => $destino,
                'conectado'     => false,
                'cajon_abierto' => null,
                'nivel'         => 'caido',
                'mensaje'       => "Falló la consulta a {$destino}.",
                'detalle'       => $e->getMessage(),
            ];
        } finally {
            fclose($socket);
        }
    }

    /**
     * Driver `serial`: el adaptador es un puerto COM virtual. Se comprueba
     * que el puerto exista y acepte una escritura; el pin del cajón no se
     * puede leer (estos adaptadores son de una sola vía, igual que la cola
     * de Windows).
     */
    private function sondearSerial(array $cfg): array
    {
        $destino = $this->destino($cfg);

        $ok = $this->conversar(
            $cfg,
            fn (Printer $printer) => $printer->initialize(),
            'sondear el cajón monedero',
        );

        return [
            'driver'        => 'serial',
            'destino'       => $destino,
            'conectado'     => $ok,
            'cajon_abierto' => null,
            'nivel'         => $ok ? 'ok' : 'caido',
            'mensaje'       => $ok
                ? "«{$destino}» responde."
                : "«{$destino}» no responde.",
            'detalle'       => $ok
                ? 'El adaptador acepta comandos. Un puerto COM no permite leer '
                . 'si el cable del cajón está puesto: eso solo se confirma abriéndolo.'
                : 'Comprueba el número de puerto en Administrador de dispositivos › '
                . 'Puertos (COM y LPT). Windows lo cambia si enchufas el adaptador '
                . 'en otro USB.',
        ];
    }

    /**
     * Abre el puerto COM dejándolo configurado.
     *
     * `mode` es lo que fija velocidad, paridad y bits de parada. Sin eso el
     * puerto queda con lo que dejó el último programa que lo usó y el cajón
     * recibe basura —o nada—. Si exec() está deshabilitado en el php.ini se
     * intenta igual: muchos adaptadores traen 9600 8N1 de fábrica y funcionan
     * sin tocar nada.
     */
    private function conectarSerial(array $cfg): FilePrintConnector
    {
        $com  = strtoupper(trim((string) $cfg['com']));
        $baud = $cfg['baud'] ?? 9600;

        if (function_exists('exec') && ! in_array('exec', explode(',', (string) ini_get('disable_functions')), true)) {
            @exec(sprintf('mode %s: BAUD=%d PARITY=N DATA=8 STOP=1 xon=off 2>&1', $com, $baud));
        }

        // COM10 y superiores necesitan el prefijo de espacio de nombres NT.
        return new FilePrintConnector('\\\\.\\' . $com);
    }

    /* ================================================================== */
    /*  Plomería                                                          */
    /* ================================================================== */

    /**
     * Abre la conexión, ejecuta lo que le pidan y cierra pase lo que pase.
     *
     * @param  \Closure(Printer):void  $accion
     */
    private function conversar(array $cfg, \Closure $accion, string $queHacia): bool
    {
        $conector = null;
        $printer  = null;
        $cerrado  = false;

        // WindowsPrintConnector hace el copy() hacia la cola con las
        // funciones nativas de PHP: cuando el recurso compartido no existe
        // emite un E_WARNING ANTES de que la librería lance su excepción.
        // Ese warning acaba en pantalla (feísimo en tinker) y en el log de
        // PHP duplicando lo que nosotros ya registramos ordenado. Se calla
        // aquí y solo aquí; fuera de este método los warnings siguen vivos.
        set_error_handler(static fn () => true, E_WARNING | E_NOTICE);

        try {
            $conector = $this->conectar($cfg);
            $printer  = new Printer($conector);

            $accion($printer);

            /*
             * ¡El close() va DENTRO del try, y esto no es un detalle!
             *
             * El conector NO escribe nada hasta que se cierra: acumula todo
             * en un buffer y en finalize() lo vuelca de un golpe hacia la
             * cola de impresión. Es ahí —y solo ahí— donde se sabe si el
             * destino existe.
             *
             * Con el close() en el finally, este método devolvía true
             * alegremente contra una impresora inexistente: la excepción
             * "Failed to copy file to printer" nacía después del return y
             * se la tragaba el catch defensivo del cierre. El semáforo del
             * POS se pintaba verde mientras el pulso se perdía en el aire.
             */
            $printer->close();
            $cerrado = true;

            return true;
        } catch (Throwable $e) {
            // warning, no error: es una degradación esperable del mostrador.
            Log::warning("No se pudo {$queHacia}.", [
                'driver'  => $cfg['driver'] ?? null,
                'destino' => $this->destino($cfg),
                'motivo'  => $e->getMessage(),
            ]);

            return false;
        } finally {
            restore_error_handler();

            // Solo si el cierre "bueno" no llegó a ocurrir. Sin esto, un
            // fallo al construir el Printer deja el conector abierto y en
            // Windows el archivo temporal de la cola colgado; al cabo de una
            // jornada de ventas son cientos de handles.
            if (! $cerrado) {
                try {
                    $printer?->close();
                } catch (Throwable) {
                    try {
                        $conector?->close();
                    } catch (Throwable) {
                        // Ya estaba muerto. No hay nada más que hacer.
                    }
                }
            }
        }
    }

    /** Arma el conector que corresponde al driver configurado. */
    private function conectar(array $cfg): PrintConnector
    {
        return match ($cfg['driver']) {
            'windows' => new WindowsPrintConnector($this->rutaWindows($cfg)),

            'network' => new NetworkPrintConnector(
                $cfg['host'],
                $cfg['port'],
                $cfg['timeout'] ?? 3,
            ),

            // Un puerto COM en Windows se abre como si fuera un archivo, pero
            // antes hay que fijarle velocidad y paridad o escribe basura. El
            // prefijo \\.\ es obligatorio a partir de COM10: sin él, PHP
            // interpreta "COM10" como un nombre de archivo normal y falla.
            'serial' => $this->conectarSerial($cfg),

            default => throw new \RuntimeException(
                "Driver de cajón desconocido: {$cfg['driver']}"
            ),
        };
    }

    /**
     * WindowsPrintConnector espera "nombre", "smb://usuario:clave@host/nombre"
     * o "\\\\HOST\\nombre". Se arma aquí para que el .env quede legible.
     */
    private function rutaWindows(array $cfg): string
    {
        $nombre = trim((string) $cfg['printer_name']);

        if (! empty($cfg['user']) && str_starts_with($nombre, '\\\\')) {
            // \\PC-CAJA\CAJON_USB  →  smb://user:pass@PC-CAJA/CAJON_USB
            [$host, $recurso] = explode('\\', ltrim($nombre, '\\'), 2);

            return sprintf(
                'smb://%s:%s@%s/%s',
                rawurlencode((string) $cfg['user']),
                rawurlencode((string) $cfg['password']),
                $host,
                $recurso,
            );
        }

        return $nombre;
    }

    /** Texto corto del destino, para logs y para la pantalla de diagnóstico. */
    private function destino(array $cfg): string
    {
        return match ($cfg['driver'] ?? null) {
            'network' => "{$cfg['host']}:{$cfg['port']}",
            'serial'  => strtoupper((string) ($cfg['com'] ?? '')),
            default   => (string) ($cfg['printer_name'] ?? ''),
        };
    }

    /** La caché es por tienda: mañana cada local tendrá su propio cajón. */
    private function claveCache(): string
    {
        return 'cajon.estado.' . (Contexto::tiendaId() ?? 'global');
    }
}
