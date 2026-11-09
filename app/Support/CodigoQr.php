<?php

namespace App\Support;

/**
 * Generador de códigos QR, sin depender de nada.
 *
 * ¿Por qué escribirlo en vez de instalar un paquete? Porque el punto de
 * venta tiene que funcionar cuando se cae internet, y porque agregar una
 * dependencia —con su cadena de dependencias, su actualización y su día de
 * incompatibilidad— para dibujar cuadritos negros es caro. Son doscientas
 * líneas que no van a cambiar nunca: el estándar ISO/IEC 18004 es de 2000.
 *
 * Alcance a propósito limitado:
 *   · Modo byte (sirve para cualquier texto UTF-8, que es lo que hay que
 *     meter: una dirección web).
 *   · Nivel de corrección M — recupera el 15% del código. Es el que se usa
 *     para impresión: L se borra con que la tirilla se manche, y Q/H gastan
 *     espacio que en 58 mm no sobra.
 *   · Versiones 1 a 10, o sea hasta 213 caracteres. Una dirección de recibo
 *     mide 60.
 *
 * Uso:
 *     CodigoQr::svg('https://mitienda.com/ventas/12/recibo', 120)
 */
class CodigoQr
{
    /** Nivel M. [codewords de datos, EC por bloque, [bloques => datos por bloque]] */
    private const VERSIONES = [
        1  => [16,  10, [[1, 16]]],
        2  => [28,  16, [[1, 28]]],
        3  => [44,  26, [[1, 44]]],
        4  => [64,  18, [[2, 32]]],
        5  => [86,  24, [[2, 43]]],
        6  => [108, 16, [[4, 27]]],
        7  => [124, 18, [[4, 31]]],
        8  => [154, 22, [[2, 38], [2, 39]]],
        9  => [182, 22, [[3, 36], [2, 37]]],
        10 => [216, 26, [[4, 43], [1, 44]]],
    ];

    /** Centros de los patrones de alineación por versión. */
    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /** Información de versión (solo hace falta de la 7 en adelante). */
    private const INFO_VERSION = [
        7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3,
    ];

    /** Bits de relleno al final del flujo, por versión. */
    private const RESTO = [
        1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7,
        7 => 0, 8 => 0, 9 => 0, 10 => 0,
    ];

    /** Tablas de logaritmos de GF(256), que se arman una sola vez. */
    private static ?array $exp = null;
    private static ?array $log = null;

    /**
     * Dibuja el código como SVG.
     *
     * Se devuelve SVG y no PNG porque no necesita la extensión GD, pesa
     * menos que la imagen, y al imprimirlo sale nítido en cualquier
     * impresora — que es justo lo que un QU necesita para poder leerse.
     *
     * @param  int  $lado    tamaño final en píxeles
     * @param  int  $margen  módulos de zona en blanco (el estándar pide 4)
     */
    public static function svg(string $texto, int $lado = 120, int $margen = 4): string
    {
        $m = self::matriz($texto);
        $n = count($m);
        $t = $n + $margen * 2;

        $d = '';

        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $d .= 'M' . ($x + $margen) . ' ' . ($y + $margen) . 'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $lado . '" height="' . $lado . '" '
             . 'viewBox="0 0 ' . $t . ' ' . $t . '" shape-rendering="crispEdges" role="img">'
             . '<rect width="' . $t . '" height="' . $t . '" fill="#fff"/>'
             . '<path d="' . $d . '" fill="#000"/>'
             . '</svg>';
    }

    /**
     * La matriz de módulos: true = negro.
     *
     * @return array<int, array<int, bool>>
     */
    public static function matriz(string $texto): array
    {
        [$version, $bits] = self::codificar($texto);

        $n      = 17 + $version * 4;
        $m      = array_fill(0, $n, array_fill(0, $n, false));
        $usado  = array_fill(0, $n, array_fill(0, $n, false));

        self::patronesFijos($m, $usado, $version, $n);
        self::ponerDatos($m, $usado, $bits, $n);

        // Se prueban las ocho máscaras y se queda la que menos penaliza.
        // Sin esto, un código con muchos ceros seguidos sale con franjas
        // grandes que el lector confunde con los patrones de esquina.
        $mejor = null;
        $mejorPena = PHP_INT_MAX;

        for ($k = 0; $k < 8; $k++) {
            $c = self::aplicarMascara($m, $usado, $k, $n);
            self::ponerFormato($c, $k, $n);

            $p = self::penalizacion($c, $n);

            if ($p < $mejorPena) {
                $mejorPena = $p;
                $mejor     = $c;
            }
        }

        return $mejor;
    }

    /* ═══════════════════ Codificación de los datos ═══════════════════ */

    /** @return array{0:int, 1:array<int,int>} versión y flujo de bits */
    private static function codificar(string $texto): array
    {
        $bytes = array_values(unpack('C*', $texto));
        $largo = count($bytes);

        $version = null;

        foreach (self::VERSIONES as $v => [$datos, , ]) {
            // 4 bits de modo + 8 o 16 de longitud, en bytes.
            $cabecera = $v < 10 ? 12 : 20;

            if ($largo + (int) ceil($cabecera / 8) <= $datos) {
                $version = $v;
                break;
            }
        }

        if ($version === null) {
            throw new \InvalidArgumentException(
                'El texto es demasiado largo para un QR versión 10 nivel M (máximo 213 caracteres).'
            );
        }

        [$capacidad, $ecPorBloque, $grupos] = self::VERSIONES[$version];

        /* ── Flujo de bits ── */
        $bits = [];

        self::empujar($bits, 0b0100, 4);                       // modo byte
        self::empujar($bits, $largo, $version < 10 ? 8 : 16);  // longitud

        foreach ($bytes as $b) {
            self::empujar($bits, $b, 8);
        }

        // Terminador: hasta cuatro ceros, y solo si caben.
        $tope = $capacidad * 8;

        for ($i = 0; $i < 4 && count($bits) < $tope; $i++) {
            $bits[] = 0;
        }

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // Relleno alternando 11101100 / 00010001, como manda el estándar.
        $relleno = [0xEC, 0x11];
        $i = 0;

        while (count($bits) < $tope) {
            self::empujar($bits, $relleno[$i++ % 2], 8);
        }

        /* ── Bloques y corrección de errores ── */
        $codewords = [];

        for ($i = 0; $i < $tope; $i += 8) {
            $b = 0;

            for ($j = 0; $j < 8; $j++) {
                $b = ($b << 1) | $bits[$i + $j];
            }

            $codewords[] = $b;
        }

        $bloquesDatos = [];
        $bloquesEc    = [];
        $pos          = 0;

        foreach ($grupos as [$cuantos, $porBloque]) {
            for ($b = 0; $b < $cuantos; $b++) {
                $bloque = array_slice($codewords, $pos, $porBloque);
                $pos   += $porBloque;

                $bloquesDatos[] = $bloque;
                $bloquesEc[]    = self::reedSolomon($bloque, $ecPorBloque);
            }
        }

        // Intercalado: se toma el primer codeword de cada bloque, luego el
        // segundo… Así una mancha en la tirilla daña un poco de todos los
        // bloques en vez de destruir uno entero, que es irrecuperable.
        $salida = [];
        $maxD   = max(array_map('count', $bloquesDatos));

        for ($i = 0; $i < $maxD; $i++) {
            foreach ($bloquesDatos as $bloque) {
                if (isset($bloque[$i])) {
                    $salida[] = $bloque[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPorBloque; $i++) {
            foreach ($bloquesEc as $bloque) {
                $salida[] = $bloque[$i];
            }
        }

        $final = [];

        foreach ($salida as $b) {
            self::empujar($final, $b, 8);
        }

        for ($i = 0; $i < self::RESTO[$version]; $i++) {
            $final[] = 0;
        }

        return [$version, $final];
    }

    private static function empujar(array &$bits, int $valor, int $cuantos): void
    {
        for ($i = $cuantos - 1; $i >= 0; $i--) {
            $bits[] = ($valor >> $i) & 1;
        }
    }

    /* ═══════════════════ Reed-Solomon ═══════════════════ */

    private static function tablas(): void
    {
        if (self::$exp !== null) {
            return;
        }

        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);

        $x = 1;

        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;

            $x <<= 1;

            if ($x & 0x100) {
                $x ^= 0x11D;   // polinomio primitivo del estándar
            }
        }

        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    /** @param array<int,int> $datos */
    private static function reedSolomon(array $datos, int $cuantos): array
    {
        self::tablas();

        // Polinomio generador: (x - a^0)(x - a^1)…(x - a^(n-1))
        $gen = [1];

        for ($i = 0; $i < $cuantos; $i++) {
            $nuevo = array_fill(0, count($gen) + 1, 0);

            foreach ($gen as $j => $c) {
                $nuevo[$j]     ^= $c;
                $nuevo[$j + 1] ^= self::mul($c, self::$exp[$i]);
            }

            $gen = $nuevo;
        }

        $resto = array_merge($datos, array_fill(0, $cuantos, 0));

        for ($i = 0; $i < count($datos); $i++) {
            $coef = $resto[$i];

            if ($coef === 0) {
                continue;
            }

            foreach ($gen as $j => $g) {
                $resto[$i + $j] ^= self::mul($g, $coef);
            }
        }

        return array_slice($resto, count($datos));
    }

    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /* ═══════════════════ Dibujo de la matriz ═══════════════════ */

    private static function patronesFijos(array &$m, array &$u, int $version, int $n): void
    {
        /* Ojos de las tres esquinas, con su separador blanco */
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$cx, $cy]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $px = $cx + $x;
                    $py = $cy + $y;

                    if ($px < 0 || $py < 0 || $px >= $n || $py >= $n) {
                        continue;
                    }

                    $borde  = ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6))
                           || ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6));
                    $centro = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;

                    $m[$py][$px] = $borde || $centro;
                    $u[$py][$px] = true;
                }
            }
        }

        /* Líneas de tiempo */
        for ($i = 8; $i < $n - 8; $i++) {
            $negro = $i % 2 === 0;

            $m[6][$i] = $negro;  $u[6][$i] = true;
            $m[$i][6] = $negro;  $u[$i][6] = true;
        }

        /* Patrones de alineación */
        $centros = self::ALINEACION[$version];

        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                // No se pisan los ojos de las esquinas.
                if (($cx === 6 && $cy === 6)
                    || ($cx === 6 && $cy === $n - 7)
                    || ($cx === $n - 7 && $cy === 6)) {
                    continue;
                }

                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $m[$cy + $y][$cx + $x] = max(abs($x), abs($y)) !== 1;
                        $u[$cy + $y][$cx + $x] = true;
                    }
                }
            }
        }

        /* Módulo oscuro, siempre negro y siempre en el mismo sitio */
        $m[$n - 8][8] = true;
        $u[$n - 8][8] = true;

        /* Espacio reservado para el formato */
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $u[8][$i] = true;
                $u[$i][8] = true;
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $u[8][$n - 1 - $i] = true;
            $u[$n - 1 - $i][8] = true;
        }

        /* Información de versión, de la 7 en adelante */
        if ($version >= 7) {
            $info = self::INFO_VERSION[$version];

            for ($i = 0; $i < 18; $i++) {
                $bit = (bool) (($info >> $i) & 1);
                $a   = intdiv($i, 3);
                $b   = $i % 3;

                $m[$n - 11 + $b][$a] = $bit;  $u[$n - 11 + $b][$a] = true;
                $m[$a][$n - 11 + $b] = $bit;  $u[$a][$n - 11 + $b] = true;
            }
        }
    }

    /** Recorrido en zigzag de abajo a la derecha hacia arriba. */
    private static function ponerDatos(array &$m, array $u, array $bits, int $n): void
    {
        $i     = 0;
        $arriba = true;

        for ($col = $n - 1; $col > 0; $col -= 2) {
            // La columna 6 es la línea de tiempo vertical: se salta entera.
            if ($col === 6) {
                $col--;
            }

            for ($f = 0; $f < $n; $f++) {
                $fila = $arriba ? $n - 1 - $f : $f;

                foreach ([0, 1] as $d) {
                    $c = $col - $d;

                    if ($u[$fila][$c]) {
                        continue;
                    }

                    $m[$fila][$c] = isset($bits[$i]) && $bits[$i] === 1;
                    $i++;
                }
            }

            $arriba = ! $arriba;
        }
    }

    private static function aplicarMascara(array $m, array $u, int $k, int $n): array
    {
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($u[$y][$x]) {
                    continue;
                }

                $invertir = match ($k) {
                    0 => ($y + $x) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($y + $x) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => ($y * $x) % 2 + ($y * $x) % 3 === 0,
                    6 => ((($y * $x) % 2 + ($y * $x) % 3) % 2) === 0,
                    7 => ((($y + $x) % 2 + ($y * $x) % 3) % 2) === 0,
                };

                if ($invertir) {
                    $m[$y][$x] = ! $m[$y][$x];
                }
            }
        }

        return $m;
    }

    /** Los 15 bits de formato, por duplicado, en las dos esquinas. */
    private static function ponerFormato(array &$m, int $mascara, int $n): void
    {
        $datos = (0b00 << 3) | $mascara;   // 00 = nivel M
        $resto = $datos << 10;

        for ($i = 4; $i >= 0; $i--) {
            if ($resto & (1 << ($i + 10))) {
                $resto ^= 0b10100110111 << $i;
            }
        }

        $formato = (($datos << 10) | $resto) ^ 0b101010000010010;

        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($formato >> $i) & 1);

            /* Copia 1: rodeando el ojo de arriba a la izquierda.
               Los primeros siete bits bajan por la columna 8 y los otros
               ocho siguen por la fila 8 hacia la izquierda. El salto en el
               6 es porque ahí pasa la línea de tiempo. */
            if ($i < 6) {
                $m[$i][8] = $bit;
            } elseif ($i === 6) {
                $m[7][8] = $bit;
            } elseif ($i === 7) {
                $m[8][8] = $bit;
            } elseif ($i === 8) {
                $m[8][7] = $bit;
            } else {
                $m[8][14 - $i] = $bit;
            }

            /* Copia 2: la mitad en la esquina de abajo, la otra mitad en la
               de la derecha. Va duplicada para que el lector pueda leer el
               formato aunque una esquina esté dañada. */
            if ($i < 8) {
                $m[8][$n - 1 - $i] = $bit;
            } else {
                $m[$n - 15 + $i][8] = $bit;
            }
        }

        $m[$n - 8][8] = true;   // el módulo oscuro nunca cambia
    }

    /* ═══════════════════ Penalización de máscaras ═══════════════════ */

    private static function penalizacion(array $m, int $n): int
    {
        $total = 0;

        /* Regla 1: cinco o más módulos iguales seguidos */
        foreach ([true, false] as $porFilas) {
            for ($a = 0; $a < $n; $a++) {
                $racha = 1;

                for ($b = 1; $b < $n; $b++) {
                    $act = $porFilas ? $m[$a][$b] : $m[$b][$a];
                    $ant = $porFilas ? $m[$a][$b - 1] : $m[$b - 1][$a];

                    if ($act === $ant) {
                        $racha++;
                    } else {
                        if ($racha >= 5) {
                            $total += $racha - 2;
                        }

                        $racha = 1;
                    }
                }

                if ($racha >= 5) {
                    $total += $racha - 2;
                }
            }
        }

        /* Regla 2: bloques de 2×2 del mismo color */
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                if ($m[$y][$x] === $m[$y][$x + 1]
                    && $m[$y][$x] === $m[$y + 1][$x]
                    && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $total += 3;
                }
            }
        }

        /* Regla 3: el patrón que se confunde con el ojo de una esquina */
        $patron  = [true, false, true, true, true, false, true];
        $blancos = [false, false, false, false];

        foreach ([true, false] as $porFilas) {
            for ($a = 0; $a < $n; $a++) {
                $linea = [];

                for ($b = 0; $b < $n; $b++) {
                    $linea[] = $porFilas ? $m[$a][$b] : $m[$b][$a];
                }

                for ($b = 0; $b <= $n - 7; $b++) {
                    if (array_slice($linea, $b, 7) !== $patron) {
                        continue;
                    }

                    $antes   = array_slice($linea, max(0, $b - 4), min(4, $b));
                    $despues = array_slice($linea, $b + 7, 4);

                    if ((count($antes) === 4 && $antes === $blancos)
                        || (count($despues) === 4 && $despues === $blancos)) {
                        $total += 40;
                    }
                }
            }
        }

        /* Regla 4: desequilibrio entre negro y blanco */
        $negros = 0;

        foreach ($m as $fila) {
            foreach ($fila as $v) {
                if ($v) {
                    $negros++;
                }
            }
        }

        $porcentaje = $negros * 100 / ($n * $n);
        $total += (int) (abs($porcentaje - 50) / 5) * 10;

        return $total;
    }
}
