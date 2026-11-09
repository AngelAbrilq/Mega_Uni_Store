<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * `php artisan mus:idioma`
 *
 * Dos preguntas que hay que poder responder en cualquier momento mientras se
 * traduce un sistema que ya estaba escrito:
 *
 *   1. ¿Qué vistas siguen con el texto quemado en el HTML?
 *      Traducir 121 vistas a ojo termina siempre igual: uno cree que ya
 *      terminó y el cliente encuentra un «Guardar» suelto en la pantalla de
 *      proveedores. Este comando da la lista y el número exacto.
 *
 *   2. ¿Las claves de español e inglés siguen emparejadas?
 *      Una clave que existe en es/ y no en en/ no rompe nada: imprime la
 *      clave cruda en mitad de la pantalla. Es el peor tipo de error,
 *      porque no falla — solo se ve mal, y nadie lo nota hasta que lo ve
 *      un cliente.
 */
class AuditarIdioma extends Command
{
    protected $signature = 'mus:idioma
                            {--detalle : lista cada texto pendiente, no solo el conteo}
                            {--vista=  : revisa una sola vista, por ejemplo products.index}';

    protected $description = 'Revisa qué falta por traducir y si es/ y en/ tienen las mismas claves';

    public function handle(): int
    {
        $this->components->info('Paridad de claves entre idiomas');
        $problemas = $this->revisarClaves();

        $this->newLine();
        $this->components->info('Textos todavía en el HTML');
        $pendientes = $this->revisarVistas();

        $this->newLine();

        if ($problemas === 0 && $pendientes === 0) {
            $this->components->info('Todo traducido y emparejado.');

            return self::SUCCESS;
        }

        $this->components->warn("Quedan {$pendientes} textos por mover a lang/ y {$problemas} claves descuadradas.");

        // Devuelve 0 igual: esto es un informe de avance, no una prueba que
        // deba tumbar el despliegue mientras la traducción está a medias.
        return self::SUCCESS;
    }

    /* ═════════════════ 1. Paridad de claves ═════════════════ */

    private function revisarClaves(): int
    {
        $es = $this->aplanarIdioma('es');
        $en = $this->aplanarIdioma('en');

        $faltanEn = array_diff(array_keys($es), array_keys($en));
        $faltanEs = array_diff(array_keys($en), array_keys($es));

        // Una clave traducida idéntica en los dos idiomas casi siempre es
        // una que se copió y se olvidó traducir. «Total» y «SKU» son
        // legítimas; el resto conviene mirarlas.
        $iguales = [];

        foreach ($es as $clave => $valor) {
            if (isset($en[$clave]) && $en[$clave] === $valor && mb_strlen($valor) > 4) {
                $iguales[] = $clave;
            }
        }

        $this->line('  claves en es/: ' . count($es) . '   ·   en en/: ' . count($en));

        foreach ($faltanEn as $c) {
            $this->components->error("falta en en/  →  {$c}");
        }

        foreach ($faltanEs as $c) {
            $this->components->error("falta en es/  →  {$c}");
        }

        if ($iguales !== []) {
            $this->line('  <fg=yellow>iguales en los dos idiomas (revisar):</> ' . implode(', ', array_slice($iguales, 0, 12))
                      . (count($iguales) > 12 ? ' …y ' . (count($iguales) - 12) . ' más' : ''));
        }

        if ($faltanEn === [] && $faltanEs === []) {
            $this->line('  <fg=green>sin claves descuadradas</>');
        }

        return count($faltanEn) + count($faltanEs);
    }

    /** @return array<string,string> «archivo.a.b» => texto */
    private function aplanarIdioma(string $idioma): array
    {
        $base = base_path('lang/' . $idioma);
        $out  = [];

        foreach (glob($base . '/*.php') ?: [] as $ruta) {
            $archivo = basename($ruta, '.php');
            $this->aplanar(require $ruta, $archivo, $out);
        }

        return $out;
    }

    private function aplanar(array $arr, string $prefijo, array &$out): void
    {
        foreach ($arr as $k => $v) {
            $clave = $prefijo . '.' . $k;

            if (is_array($v)) {
                $this->aplanar($v, $clave, $out);
            } else {
                $out[$clave] = (string) $v;
            }
        }
    }

    /* ═════════════════ 2. Textos sin traducir ═════════════════ */

    private function revisarVistas(): int
    {
        $vistas = $this->vistas();
        $filas  = [];
        $total  = 0;

        foreach ($vistas as $ruta) {
            $nombre = $this->nombreVista($ruta);

            if ($this->option('vista') && $nombre !== $this->option('vista')) {
                continue;
            }

            $textos = $this->textosQuemados(file_get_contents($ruta));

            if ($textos === []) {
                continue;
            }

            $total += count($textos);
            $filas[] = [$nombre, count($textos), implode(' · ', array_slice($textos, 0, 3))];

            if ($this->option('detalle')) {
                $this->line("  <fg=cyan>{$nombre}</>");

                foreach ($textos as $t) {
                    $this->line('     ' . $t);
                }
            }
        }

        if (! $this->option('detalle')) {
            usort($filas, fn ($a, $b) => $b[1] <=> $a[1]);

            $this->table(
                ['vista', 'textos', 'ejemplos'],
                array_slice($filas, 0, 25)
            );

            if (count($filas) > 25) {
                $this->line('  …y ' . (count($filas) - 25) . ' vistas más. Usa --detalle para verlas todas.');
            }
        }

        $this->line("  vistas revisadas: " . count($vistas) . "   ·   con texto pendiente: " . count($filas));

        return $total;
    }

    /** @return array<int,string> */
    private function vistas(): array
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        $out = [];

        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $out[] = $f->getPathname();
            }
        }

        sort($out);

        return $out;
    }

    private function nombreVista(string $ruta): string
    {
        $rel = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $ruta);

        return str_replace([DIRECTORY_SEPARATOR, '.blade.php'], ['.', ''], $rel);
    }

    /**
     * Saca los textos en español que siguen escritos a mano.
     *
     * Es una heurística, no un analizador: busca palabras con letras entre
     * etiquetas y en los atributos que el usuario lee. Se le escapa algo y
     * marca de más de vez en cuando — pero da el orden de magnitud y la
     * lista por dónde empezar, que es para lo que sirve.
     *
     * @return array<int,string>
     */
    private function textosQuemados(string $src): array
    {
        // Fuera lo que no es interfaz: estilos, scripts, comentarios de
        // Blade y bloques @php. Ahí adentro hay mucho texto que no se ve.
        $s = preg_replace('/<style\b.*?<\/style>/s', '', $src);
        $s = preg_replace('/<script\b.*?<\/script>/s', '', (string) $s);
        $s = preg_replace('/\{\{--.*?--\}\}/s', '', (string) $s);
        $s = preg_replace('/@php\b.*?@endphp/s', '', (string) $s);
        $s = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '', (string) $s);

        $encontrados = [];

        // 1. Texto entre etiquetas.
        preg_match_all('/>([^<>{}@]{3,})</', (string) $s, $m);

        foreach ($m[1] as $t) {
            $t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');

            if ($this->pareceTexto($t)) {
                $encontrados[] = $t;
            }
        }

        // 2. Atributos que el usuario sí lee.
        // El (?<![:\w-]) descarta los atributos con dos puntos —:label=—
        // porque esos ya llevan una expresión de PHP, normalmente un __().
        preg_match_all(
            '/(?<![:\w-])(label|placeholder|title|alt|aria-label|what|subtitle|sub|text|hint|empty)="([^"{@]{3,})"/i',
            (string) $s,
            $m2
        );

        foreach ($m2[2] as $t) {
            $t = trim($t);

            if ($this->pareceTexto($t)) {
                $encontrados[] = $t;
            }
        }

        return array_values(array_unique($encontrados));
    }

    private function pareceTexto(string $t): bool
    {
        if (mb_strlen($t) < 3) {
            return false;
        }

        // Tiene que tener al menos dos letras seguidas: descarta «·», «—»,
        // «19%», «$1.500» y los separadores decorativos.
        if (! preg_match('/\p{L}{2,}/u', $t)) {
            return false;
        }

        // Restos de plantilla o de código que se colaron.
        if (preg_match('/^(true|false|null|https?:|[\d\s.,%$·—–-]+)$/i', $t)) {
            return false;
        }

        // Trozos de PHP que el limpiador de arriba no alcanzó: una llamada
        // a __(), una variable, el final de una condición.
        if (preg_match('/__\(|\$\w|===|!==|=>|::/', $t)) {
            return false;
        }

        return true;
    }
}
