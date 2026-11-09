<?php

namespace App\Support\Exportes;

/**
 * Escritor PDF mínimo, sin dependencias (RP-EXP-001).
 *
 * Genera un PDF 1.4 con las fuentes estándar Helvetica (no hay que
 * incrustar nada) en codificación WinAnsi, que cubre tildes y eñes.
 * Pinta lo que un reporte necesita: encabezado con el negocio y el rango,
 * la tabla con encabezado repetido en cada página, filas cebra, cifras
 * alineadas a la derecha, fila de totales y «Página X de Y».
 *
 * Los anchos de texto salen de las métricas reales de Helvetica, así que
 * las columnas numéricas quedan alineadas y lo que no cabe se recorta con
 * «…» en vez de montarse sobre la columna vecina.
 */
final class PdfWriter
{
    private const ANCHO = 842.0;   // A4 horizontal
    private const ALTO  = 595.0;
    private const MARGEN = 36.0;
    private const FILA = 17.0;
    private const CUERPO = 8.5;

    /** Anchos de Helvetica (1/1000 em) para ASCII 32–126. */
    private const METRICAS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var array<int, string> contenido de cada página */
    private array $paginas = [];

    public function render(Tabla $tabla, string $generado): string
    {
        $anchos = $this->anchosDeColumna($tabla);
        $porPagina = (int) floor((self::ALTO - 2 * self::MARGEN - 110) / self::FILA);
        $bloques = array_chunk($tabla->filas, max(1, $porPagina)) ?: [[]];
        $total   = count($bloques);

        foreach ($bloques as $i => $filas) {
            $ultima = $i === $total - 1;
            $this->paginas[] = $this->pagina($tabla, $anchos, $filas, $ultima, $i + 1, $total, $generado);
        }

        return $this->documento($tabla->titulo);
    }

    /* ═════════════ Página ═════════════ */

    /** @param array<int, float> $anchos @param array<int, array<int, mixed>> $filas */
    private function pagina(Tabla $tabla, array $anchos, array $filas, bool $ultima, int $n, int $de, string $generado): string
    {
        $c = '';
        $y = self::ALTO - self::MARGEN;

        // Franja superior
        $c .= $this->rect(0, self::ALTO - 6, self::ANCHO, 6, [0.18, 0.43, 0.66]);
        $c .= $this->texto(self::MARGEN, $y - 12, $tabla->titulo, 16, true, [0.11, 0.28, 0.44]);
        $c .= $this->texto(self::MARGEN, $y - 30, trim($tabla->negocio . '  ·  ' . $tabla->subtitulo, ' ·'), 9.5, false, [0.40, 0.46, 0.56]);
        $c .= $this->textoDerecha(self::ANCHO - self::MARGEN, $y - 12, 'Mega_Uni_Store', 9, true, [0.40, 0.46, 0.56]);

        $y -= 58;

        // Encabezado de la tabla
        $c .= $this->rect(self::MARGEN, $y - 5, self::ANCHO - 2 * self::MARGEN, self::FILA, [0.18, 0.43, 0.66]);
        $c .= $this->filaTexto($tabla, $anchos, array_column($tabla->columnas, 'etiqueta'), $y, true, [1, 1, 1], true);
        $y -= self::FILA;

        foreach ($filas as $k => $fila) {
            if ($k % 2 === 1) {
                $c .= $this->rect(self::MARGEN, $y - 5, self::ANCHO - 2 * self::MARGEN, self::FILA, [0.96, 0.97, 0.99]);
            }
            $c .= $this->filaTexto($tabla, $anchos, $this->formateada($tabla, $fila), $y, false, [0.13, 0.17, 0.24]);
            $y -= self::FILA;
        }

        if (! $filas) {
            $c .= $this->texto(self::MARGEN + 6, $y, 'Sin datos en el periodo elegido.', self::CUERPO, false, [0.40, 0.46, 0.56]);
            $y -= self::FILA;
        }

        if ($ultima && $tabla->totales !== null) {
            $c .= $this->rect(self::MARGEN, $y - 5, self::ANCHO - 2 * self::MARGEN, self::FILA, [0.93, 0.96, 0.98]);
            $c .= $this->linea(self::MARGEN, $y + self::FILA - 5, self::ANCHO - self::MARGEN, [0.18, 0.43, 0.66]);
            $c .= $this->filaTexto($tabla, $anchos, $this->formateada($tabla, $tabla->totales), $y, true, [0.11, 0.17, 0.24]);
        }

        // Pie
        $c .= $this->linea(self::MARGEN, self::MARGEN + 4, self::ANCHO - self::MARGEN, [0.85, 0.88, 0.92]);
        $c .= $this->texto(self::MARGEN, self::MARGEN - 8, 'Generado el ' . $generado, 7.5, false, [0.50, 0.55, 0.62]);
        $c .= $this->textoDerecha(self::ANCHO - self::MARGEN, self::MARGEN - 8, "Página {$n} de {$de}", 7.5, false, [0.50, 0.55, 0.62]);

        return $c;
    }

    /** @param array<int, mixed> $fila */
    private function formateada(Tabla $tabla, array $fila): array
    {
        $salida = [];
        foreach ($tabla->columnas as $i => $_) {
            $salida[$i] = $tabla->formatear($i, $fila[$i] ?? null);
        }

        return $salida;
    }

    /** @param array<int, float> $anchos @param array<int, string> $celdas */
    private function filaTexto(Tabla $tabla, array $anchos, array $celdas, float $y, bool $negrita, array $color, bool $encabezado = false): string
    {
        $c = '';
        $x = self::MARGEN;

        foreach ($anchos as $i => $ancho) {
            $texto = $this->recortar((string) ($celdas[$i] ?? ''), $ancho - 12, self::CUERPO, $negrita);

            $c .= $tabla->esNumerica($i)
                ? $this->textoDerecha($x + $ancho - 6, $y, $texto, self::CUERPO, $negrita, $color)
                : $this->texto($x + 6, $y, $texto, self::CUERPO, $negrita, $color);

            $x += $ancho;
        }

        return $c;
    }

    /** Reparte el ancho útil: las numéricas fijas y las de texto según su peso. */
    private function anchosDeColumna(Tabla $tabla): array
    {
        $util = self::ANCHO - 2 * self::MARGEN;
        $fijo = 0.0;
        $pesos = [];

        foreach ($tabla->columnas as $i => $col) {
            if ($tabla->esNumerica($i)) {
                $fijo += 92;
            } else {
                $pesos[$i] = (float) ($col['ancho'] ?? 1);
            }
        }

        $resto = max(80, $util - $fijo);
        $suma  = array_sum($pesos) ?: 1;
        $anchos = [];

        foreach ($tabla->columnas as $i => $_) {
            $anchos[$i] = $tabla->esNumerica($i) ? 92.0 : $resto * $pesos[$i] / $suma;
        }

        // Si no hay columnas de texto, las numéricas se estiran.
        $total = array_sum($anchos);

        return array_map(fn ($a) => $a * $util / $total, $anchos);
    }

    /* ═════════════ Primitivas ═════════════ */

    private function texto(float $x, float $y, string $s, float $tam, bool $negrita, array $color): string
    {
        return sprintf("BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $this->rgb($color), $negrita ? 'F2' : 'F1', $tam, $x, $y, $this->escapar($s));
    }

    private function textoDerecha(float $x, float $y, string $s, float $tam, bool $negrita, array $color): string
    {
        return $this->texto($x - $this->ancho($s, $tam, $negrita), $y, $s, $tam, $negrita, $color);
    }

    private function rect(float $x, float $y, float $w, float $h, array $color): string
    {
        return sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $this->rgb($color), $x, $y, $w, $h);
    }

    private function linea(float $x1, float $y, float $x2, array $color): string
    {
        return sprintf("%s RG 0.6 w %.2F %.2F m %.2F %.2F l S\n", $this->rgb($color), $x1, $y, $x2, $y);
    }

    private function rgb(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', $c[0], $c[1], $c[2]);
    }

    /** Ancho del texto en puntos, con las métricas de Helvetica. */
    private function ancho(string $s, float $tam, bool $negrita): float
    {
        $total = 0;
        foreach (mb_str_split($s) as $ch) {
            $base = $this->sinTilde($ch);
            $o = ord($base);
            $total += ($o >= 32 && $o <= 126) ? self::METRICAS[$o - 32] : 556;
        }

        return $total * $tam / 1000 * ($negrita ? 1.06 : 1);
    }

    private function recortar(string $s, float $max, float $tam, bool $negrita): string
    {
        if ($this->ancho($s, $tam, $negrita) <= $max) {
            return $s;
        }

        while ($s !== '' && $this->ancho($s . '…', $tam, $negrita) > $max) {
            $s = mb_substr($s, 0, -1);
        }

        return rtrim($s) . '…';
    }

    private function sinTilde(string $ch): string
    {
        $mapa = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
                 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U', '…' => '.'];

        return strlen($ch) === 1 ? $ch : ($mapa[$ch] ?? 'a');
    }

    /** UTF-8 → WinAnsi y escape de la sintaxis de cadenas PDF. */
    private function escapar(string $s): string
    {
        $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');

        return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }

    /* ═════════════ Documento ═════════════ */

    private function documento(string $titulo): string
    {
        $objetos = [];
        $n = count($this->paginas);

        // 1 catálogo · 2 árbol de páginas · 3 y 4 fuentes · 5 info · luego página + contenido
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (6 + 2 * $i) . ' 0 R';
        }

        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objetos[5] = '<< /Title (' . $this->escapar($titulo) . ') /Producer (Mega_Uni_Store) >>';

        foreach ($this->paginas as $i => $contenido) {
            $pag = 6 + 2 * $i;
            $objetos[$pag] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::ANCHO . ' ' . self::ALTO . ']'
                . ' /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pag + 1) . ' 0 R >>';
            $objetos[$pag + 1] = '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "endstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        ksort($objetos);
        foreach ($objetos as $id => $cuerpo) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $cuerpo . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";
        for ($id = 1; $id < $total; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R /Info 5 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }
}
