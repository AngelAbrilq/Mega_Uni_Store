<?php

namespace App\Support\Exportes;

/**
 * Lienzo PDF libre (A4 vertical), sin dependencias.
 *
 * Hermano de PdfWriter: aquel pinta tablas de reportes; este deja dibujar
 * documentos con forma propia —el desprendible de nómina (NR-NOM-008)—
 * con texto, rectángulos, líneas y un código QR hecho de módulos.
 * Coordenadas en puntos desde la esquina SUPERIOR izquierda (más natural
 * para maquetar); se convierten al sistema PDF al escribir.
 */
final class PdfLienzo
{
    public const ANCHO = 595.0;
    public const ALTO  = 842.0;

    private const METRICAS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var array<int, string> */
    private array $paginas = [];

    private string $actual = '';

    public function nuevaPagina(): self
    {
        if ($this->actual !== '') {
            $this->paginas[] = $this->actual;
        }

        $this->actual = ' ';

        return $this;
    }

    public function texto(float $x, float $y, string $s, float $tam = 9, bool $negrita = false, array $color = [0.1, 0.12, 0.16], string $alinear = 'izq'): self
    {
        $ancho = $this->ancho($s, $tam, $negrita);
        $x = match ($alinear) { 'der' => $x - $ancho, 'centro' => $x - $ancho / 2, default => $x };

        $this->actual .= sprintf("BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $this->rgb($color), $negrita ? 'F2' : 'F1', $tam, $x, self::ALTO - $y, $this->escapar($s));

        return $this;
    }

    public function rect(float $x, float $y, float $w, float $h, array $relleno): self
    {
        $this->actual .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $this->rgb($relleno), $x, self::ALTO - $y - $h, $w, $h);

        return $this;
    }

    public function borde(float $x, float $y, float $w, float $h, array $color, float $grosor = 0.6): self
    {
        $this->actual .= sprintf("%s RG %.2F w %.2F %.2F %.2F %.2F re S\n", $this->rgb($color), $grosor, $x, self::ALTO - $y - $h, $w, $h);

        return $this;
    }

    public function linea(float $x1, float $y1, float $x2, float $y2, array $color, float $grosor = 0.6): self
    {
        $this->actual .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $this->rgb($color), $grosor, $x1, self::ALTO - $y1, $x2, self::ALTO - $y2);

        return $this;
    }

    /** @param array<int, array<int, bool>> $matriz módulos de CodigoQr::matriz() */
    public function qr(float $x, float $y, float $lado, array $matriz): self
    {
        $n = count($matriz);
        $m = $lado / $n;
        $c = "0 0 0 rg\n";

        foreach ($matriz as $f => $fila) {
            foreach ($fila as $k => $negro) {
                if ($negro) {
                    $c .= sprintf("%.2F %.2F %.2F %.2F re\n", $x + $k * $m, self::ALTO - $y - ($f + 1) * $m, $m + 0.02, $m + 0.02);
                }
            }
        }

        $this->actual .= $c . "f\n";

        return $this;
    }

    public function ancho(string $s, float $tam, bool $negrita = false): float
    {
        $total = 0;

        foreach (mb_str_split($s) as $ch) {
            $o = ord(strlen($ch) === 1 ? $ch : 'a');
            $total += ($o >= 32 && $o <= 126) ? self::METRICAS[$o - 32] : 556;
        }

        return $total * $tam / 1000 * ($negrita ? 1.06 : 1);
    }

    public function recortar(string $s, float $max, float $tam, bool $negrita = false): string
    {
        if ($this->ancho($s, $tam, $negrita) <= $max) {
            return $s;
        }

        while ($s !== '' && $this->ancho($s . '...', $tam, $negrita) > $max) {
            $s = mb_substr($s, 0, -1);
        }

        return rtrim($s) . '...';
    }

    public function render(string $titulo): string
    {
        if ($this->actual !== '') {
            $this->paginas[] = $this->actual;
            $this->actual = '';
        }

        $objetos = [];
        $n = count($this->paginas);
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

        return $pdf . "trailer\n<< /Size {$total} /Root 1 0 R /Info 5 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function rgb(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', $c[0], $c[1], $c[2]);
    }

    private function escapar(string $s): string
    {
        $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');

        return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }
}
