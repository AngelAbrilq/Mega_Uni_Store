<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Copia de seguridad de la base de datos.
 *
 *     php artisan mus:respaldo
 *     php artisan mus:respaldo --dias=30
 *     php artisan mus:respaldo --sin-comprimir
 *
 * El archivo queda en storage/app/respaldos/. Intenta primero con
 * mysqldump (rápido y completo); si no lo encuentra en el equipo, hace el
 * volcado con PHP puro leyendo tabla por tabla, así que el respaldo se
 * genera igual en cualquier máquina.
 *
 * Para restaurar:
 *     mysql -u root mega_uni_store < respaldo.sql
 * (si el archivo terminó en .gz, primero hay que descomprimirlo)
 */
class Respaldar extends Command
{
    protected $signature = 'mus:respaldo
                            {--dias=14 : Cuántos días de respaldos conservar}
                            {--sin-comprimir : Dejar el .sql tal cual, sin pasarlo a .gz}';

    protected $description = 'Genera una copia de seguridad de la base de datos';

    /** Carpeta donde viven los respaldos. */
    private string $carpeta;

    public function handle(): int
    {
        $this->carpeta = storage_path('app/respaldos');
        File::ensureDirectoryExists($this->carpeta);

        $conexion = config('database.default');

        if (config("database.connections.{$conexion}.driver") !== 'mysql') {
            $this->error('  Este comando está hecho para MySQL / MariaDB.');

            return self::FAILURE;
        }

        $base   = config("database.connections.{$conexion}.database");
        $sello  = now()->format('Y-m-d_His');
        $ruta   = "{$this->carpeta}/{$base}_{$sello}.sql";

        $this->newLine();
        $this->line("  Respaldando <options=bold>{$base}</>…");

        $conMysqldump = $this->conMysqldump($ruta, $conexion);

        if (! $conMysqldump) {
            $this->line('  mysqldump no está disponible; usando el volcado interno.');

            try {
                $this->conPhp($ruta);
            } catch (Throwable $e) {
                $this->newLine();
                $this->error('  No se pudo generar el respaldo: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        if (! is_file($ruta) || filesize($ruta) < 100) {
            $this->error('  El archivo salió vacío. Revisa el usuario y la contraseña de la base de datos.');
            @unlink($ruta);

            return self::FAILURE;
        }

        if (! $this->option('sin-comprimir')) {
            $ruta = $this->comprimir($ruta);
        }

        $this->newLine();
        $this->info('  Respaldo listo: ' . basename($ruta) . ' (' . $this->peso(filesize($ruta)) . ')');
        $this->line('  Carpeta: ' . $this->carpeta);

        $this->limpiar();

        $this->newLine();

        return self::SUCCESS;
    }

    /* ─────────────── Vía 1: mysqldump ─────────────── */

    /**
     * Intenta el volcado con mysqldump. La contraseña no viaja en la línea
     * de comandos (se vería en el administrador de tareas): va en un archivo
     * temporal de configuración que se borra al terminar.
     */
    private function conMysqldump(string $destino, string $conexion): bool
    {
        $binario = $this->buscarMysqldump();

        if (! $binario) {
            return false;
        }

        $cnf = tempnam(sys_get_temp_dir(), 'mus');

        if ($cnf === false) {
            return false;
        }

        $cfg = config("database.connections.{$conexion}");

        file_put_contents($cnf, implode(PHP_EOL, [
            '[client]',
            'host = ' . ($cfg['host'] ?? '127.0.0.1'),
            'port = ' . ($cfg['port'] ?? 3306),
            'user = ' . ($cfg['username'] ?? 'root'),
            'password = "' . str_replace('"', '\"', (string) ($cfg['password'] ?? '')) . '"',
            '',
        ]));

        @chmod($cnf, 0600);

        $proceso = new Process([
            $binario,
            '--defaults-extra-file=' . $cnf,
            '--single-transaction',
            '--routines',
            '--events',
            '--default-character-set=utf8mb4',
            '--result-file=' . $destino,
            (string) $cfg['database'],
        ]);

        $proceso->setTimeout(600);

        try {
            $proceso->run();
        } catch (Throwable $e) {
            @unlink($cnf);

            return false;
        }

        @unlink($cnf);

        if (! $proceso->isSuccessful()) {
            $error = trim($proceso->getErrorOutput());

            if ($error !== '') {
                $this->line('  mysqldump avisó: ' . mb_strimwidth($error, 0, 160, '…'));
            }

            return false;
        }

        return true;
    }

    /**
     * Ubica el ejecutable. En Laragon vive dentro de bin/mysql/<versión>/bin.
     * Se puede forzar con MUS_MYSQLDUMP en el .env.
     */
    private function buscarMysqldump(): ?string
    {
        $forzado = env('MUS_MYSQLDUMP');

        if (is_string($forzado) && $forzado !== '' && is_file($forzado)) {
            return $forzado;
        }

        $windows = str_starts_with(strtoupper(PHP_OS_FAMILY), 'WIN');
        $nombre  = $windows ? 'mysqldump.exe' : 'mysqldump';

        // 1. Junto al PHP que está corriendo (Laragon comparte la carpeta bin).
        $candidatos = [];

        foreach (glob(dirname(PHP_BINARY, 3) . '/mysql/*/bin/' . $nombre) ?: [] as $c) {
            $candidatos[] = $c;
        }

        foreach (['C:/laragon/bin/mysql', 'C:/xampp/mysql'] as $raiz) {
            foreach (glob($raiz . '/*/bin/' . $nombre) ?: [] as $c) {
                $candidatos[] = $c;
            }

            if (is_file($raiz . '/bin/' . $nombre)) {
                $candidatos[] = $raiz . '/bin/' . $nombre;
            }
        }

        foreach ($candidatos as $c) {
            if (is_file($c)) {
                return str_replace('\\', '/', $c);
            }
        }

        // 2. En el PATH del sistema.
        $buscador = new Process($windows ? ['where', $nombre] : ['which', $nombre]);

        try {
            $buscador->run();
        } catch (Throwable) {
            return null;
        }

        if ($buscador->isSuccessful()) {
            $linea = trim(strtok($buscador->getOutput(), PHP_EOL) ?: '');

            if ($linea !== '' && is_file($linea)) {
                return $linea;
            }
        }

        return null;
    }

    /* ─────────────── Vía 2: volcado con PHP ─────────────── */

    /**
     * Recorre las tablas con el propio PDO de Laravel y escribe el .sql a
     * mano. Más lento que mysqldump, pero no depende de nada instalado.
     */
    private function conPhp(string $destino): void
    {
        $pdo   = DB::connection()->getPdo();
        $base  = DB::connection()->getDatabaseName();
        $salida = fopen($destino, 'w');

        if ($salida === false) {
            throw new \RuntimeException('no se pudo escribir en ' . $destino);
        }

        fwrite($salida, implode(PHP_EOL, [
            '-- MEGA UNI STORE — respaldo de ' . $base,
            '-- Generado el ' . now()->format('d/m/Y H:i:s'),
            '',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS = 0;',
            'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";',
            '',
            '',
        ]));

        $tablas = array_map(
            fn (array $fila) => array_values($fila)[0],
            $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_ASSOC)
        );

        $barra = $this->output->createProgressBar(count($tablas));
        $barra->start();

        foreach ($tablas as $tabla) {
            $crear = $pdo->query('SHOW CREATE TABLE `' . $tabla . '`')->fetch(PDO::FETCH_NUM)[1] ?? null;

            fwrite($salida, "DROP TABLE IF EXISTS `{$tabla}`;" . PHP_EOL);

            if ($crear) {
                fwrite($salida, $crear . ';' . PHP_EOL . PHP_EOL);
            }

            $this->volcarFilas($pdo, $salida, $tabla);

            $barra->advance();
        }

        $barra->finish();
        $this->newLine();

        fwrite($salida, PHP_EOL . 'SET FOREIGN_KEY_CHECKS = 1;' . PHP_EOL);
        fclose($salida);
    }

    /**
     * Escribe los INSERT de una tabla en lotes, para no cargar en memoria
     * las mil y pico filas del kardex.
     */
    private function volcarFilas(PDO $pdo, $salida, string $tabla): void
    {
        $consulta = $pdo->query('SELECT * FROM `' . $tabla . '`');
        $lote     = [];
        $columnas = null;

        while ($fila = $consulta->fetch(PDO::FETCH_ASSOC)) {
            $columnas ??= '`' . implode('`, `', array_keys($fila)) . '`';

            $valores = array_map(function ($v) use ($pdo) {
                if ($v === null) {
                    return 'NULL';
                }

                return is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v);
            }, $fila);

            $lote[] = '(' . implode(', ', $valores) . ')';

            if (count($lote) >= 200) {
                fwrite($salida, "INSERT INTO `{$tabla}` ({$columnas}) VALUES" . PHP_EOL
                    . implode(',' . PHP_EOL, $lote) . ';' . PHP_EOL);
                $lote = [];
            }
        }

        if ($lote !== []) {
            fwrite($salida, "INSERT INTO `{$tabla}` ({$columnas}) VALUES" . PHP_EOL
                . implode(',' . PHP_EOL, $lote) . ';' . PHP_EOL);
        }

        fwrite($salida, PHP_EOL);
    }

    /* ─────────────── Compresión y limpieza ─────────────── */

    /**
     * Pasa el .sql a .gz. Un respaldo de texto se encoge a menos de la
     * décima parte. Si zlib no está, deja el archivo como está.
     */
    private function comprimir(string $ruta): string
    {
        if (! function_exists('gzopen')) {
            return $ruta;
        }

        $destino = $ruta . '.gz';
        $entrada = fopen($ruta, 'rb');
        $salida  = gzopen($destino, 'wb9');

        if ($entrada === false || $salida === false) {
            return $ruta;
        }

        while (! feof($entrada)) {
            gzwrite($salida, (string) fread($entrada, 512 * 1024));
        }

        fclose($entrada);
        gzclose($salida);
        @unlink($ruta);

        return $destino;
    }

    /** Borra los respaldos más viejos que --dias. */
    private function limpiar(): void
    {
        $dias = max(1, (int) $this->option('dias'));
        $tope = now()->subDays($dias)->getTimestamp();
        $idos = 0;

        foreach (glob($this->carpeta . '/*.sql*') ?: [] as $archivo) {
            if (filemtime($archivo) < $tope) {
                @unlink($archivo);
                $idos++;
            }
        }

        if ($idos > 0) {
            $this->line("  Se borraron {$idos} respaldo(s) con más de {$dias} días.");
        }
    }

    private function peso(int $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i === 0 ? 0 : 1, ',', '.') . ' ' . $unidades[$i];
    }
}
