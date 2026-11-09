<?php

namespace App\Support\Exportes;

use RuntimeException;
use ZipArchive;

/**
 * Escritor XLSX mínimo, sin dependencias (RP-EXP-001).
 *
 * Un .xlsx es un ZIP con unos XML. Para una hoja con encabezado, filas y
 * una fila de totales no hace falta una librería de cientos de archivos:
 * basta con escribir esos XML bien. Las cifras van como números reales
 * (con formato de miles), no como texto: en Excel se pueden sumar.
 *
 * Requiere la extensión zip de PHP (viene activa en Laragon).
 */
final class XlsxWriter
{
    /** Estilos (índices de cellXfs en styles.xml). */
    private const S_TEXTO = 0, S_ENCABEZADO = 1, S_MILES = 2, S_DECIMAL = 3, S_TITULO = 4, S_TOTAL_TEXTO = 5, S_TOTAL_MILES = 6, S_SUBTITULO = 7;

    public static function disponible(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /** Devuelve el contenido binario del .xlsx. */
    public function render(Tabla $tabla): string
    {
        return $this->renderLibro([$tabla]);
    }

    /**
     * Libro con varias hojas, una por Tabla (NR-NOM-009: Resumen, Detalle por
     * tienda, Detalle por empleado…). El nombre de cada hoja es el título.
     *
     * @param array<int, Tabla> $tablas
     */
    public function renderLibro(array $tablas): string
    {
        $tablas = array_values($tablas);

        if (! self::disponible()) {
            throw new RuntimeException('Falta la extensión zip de PHP para generar archivos de Excel.');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip  = new ZipArchive();

        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de Excel.');
        }

        $n = count($tablas);
        $zip->addFromString('[Content_Types].xml', $this->tiposDeContenido($n));
        $zip->addFromString('_rels/.rels', $this->relacionesRaiz());
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Mega_Uni_Store</Application></Properties>');
        $zip->addFromString('xl/workbook.xml', $this->libro($tablas));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relacionesLibro($n));
        $zip->addFromString('xl/styles.xml', $this->estilos());

        foreach ($tablas as $i => $tabla) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->hoja($tabla));
        }

        $zip->close();

        $contenido = (string) file_get_contents($ruta);
        @unlink($ruta);

        return $contenido;
    }

    private function hoja(Tabla $tabla): string
    {
        $n      = count($tabla->columnas);
        $ultima = $this->letra($n - 1);
        $filas  = [];
        $r      = 1;

        $filas[] = $this->fila($r++, [$this->celdaTexto('A', $r - 1, $tabla->titulo, self::S_TITULO)]);
        $filas[] = $this->fila($r++, [$this->celdaTexto('A', $r - 1, trim($tabla->negocio . ' · ' . $tabla->subtitulo, ' ·'), self::S_SUBTITULO)]);
        $r++; // fila en blanco

        $encabezado = $r;
        $celdas = [];
        foreach ($tabla->columnas as $i => $col) {
            $celdas[] = $this->celdaTexto($this->letra($i), $r, $col['etiqueta'], self::S_ENCABEZADO);
        }
        $filas[] = $this->fila($r++, $celdas);

        foreach ($tabla->filas as $datos) {
            $filas[] = $this->fila($r, $this->celdasDatos($tabla, $datos, $r, false));
            $r++;
        }

        if ($tabla->totales !== null) {
            $filas[] = $this->fila($r, $this->celdasDatos($tabla, $tabla->totales, $r, true));
        }

        $anchos = '';
        foreach ($tabla->columnas as $i => $col) {
            $ancho = $tabla->esNumerica($i) ? 16 : max(14, min(48, 10 + (int) (($col['ancho'] ?? 1) * 12)));
            $anchos .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $ancho . '" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $encabezado . '" topLeftCell="A' . ($encabezado + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $anchos . '</cols>'
            . '<sheetData>' . implode('', $filas) . '</sheetData>'
            . '<autoFilter ref="A' . $encabezado . ':' . $ultima . ($encabezado + max(1, count($tabla->filas))) . '"/>'
            . '</worksheet>';
    }

    /** @param array<int, mixed> $datos */
    private function celdasDatos(Tabla $tabla, array $datos, int $r, bool $total): array
    {
        $celdas = [];

        foreach ($tabla->columnas as $i => $col) {
            $valor = $datos[$i] ?? null;
            $ref   = $this->letra($i);

            if ($valor === null || $valor === '') {
                continue;
            }

            if ($tabla->esNumerica($i) && is_numeric($valor)) {
                $estilo   = $total ? self::S_TOTAL_MILES : ($col['tipo'] === Tabla::NUMERO ? self::S_DECIMAL : self::S_MILES);
                $celdas[] = '<c r="' . $ref . $r . '" s="' . $estilo . '"><v>' . (0 + $valor) . '</v></c>';
            } else {
                $celdas[] = $this->celdaTexto($ref, $r, (string) $valor, $total ? self::S_TOTAL_TEXTO : self::S_TEXTO);
            }
        }

        return $celdas;
    }

    private function fila(int $r, array $celdas): string
    {
        return '<row r="' . $r . '">' . implode('', $celdas) . '</row>';
    }

    private function celdaTexto(string $col, int $r, string $texto, int $estilo): string
    {
        return '<c r="' . $col . $r . '" t="inlineStr" s="' . $estilo . '"><is><t xml:space="preserve">'
            . htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private function letra(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + (($i - 1) % 26)) . $s;
        }

        return $s;
    }

    private function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0"/><numFmt numFmtId="165" formatCode="#,##0.##"/></numFmts>'
            . '<fonts count="4">'
            .   '<font><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="14"/><color rgb="FF1B4870"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FF2E6EA8"/></patternFill></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF4FB"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border><top style="thin"><color rgb="FF2E6EA8"/></top></border></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="8">'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/>'
            .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/>'
            .   '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/>'
            .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" applyFont="1"/>'
            .   '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            .   '<xf numFmtId="164" fontId="3" fillId="3" borderId="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** @param array<int, Tabla> $tablas */
    private function libro(array $tablas): string
    {
        $hojas = '';
        $usados = [];

        foreach ($tablas as $i => $tabla) {
            $nombre = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $tabla->titulo), 0, 31) ?: 'Hoja ' . ($i + 1);

            while (in_array(mb_strtolower($nombre), $usados, true)) {
                $nombre = mb_substr($nombre, 0, 28) . ' ' . ($i + 1);
            }

            $usados[] = mb_strtolower($nombre);
            $hojas .= '<sheet name="' . htmlspecialchars($nombre, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $hojas . '</sheets>'
            . '</workbook>';
    }

    private function tiposDeContenido(int $hojas = 1): string
    {
        $partes = '';

        for ($i = 1; $i <= $hojas; $i++) {
            $partes .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $partes
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function relacionesRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function relacionesLibro(int $hojas = 1): string
    {
        $rels = '';

        for ($i = 1; $i <= $hojas; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '<Relationship Id="rId' . ($hojas + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }
}
