<?php

namespace App\Support\Exportes;

/**
 * Lo que se exporta: un título, columnas tipadas, filas y totales.
 *
 * Es el contrato entre los reportes y los formatos (RP-EXP-001): el
 * reporte arma una Tabla y cada escritor (XLSX, PDF) decide cómo pintarla.
 * Así agregar un formato no toca los reportes, y agregar un reporte no
 * toca los formatos.
 */
final class Tabla
{
    public const TEXTO  = 'texto';
    public const MONEDA = 'moneda';
    public const NUMERO = 'numero';
    public const ENTERO = 'entero';

    /**
     * @param  array<int, array{etiqueta: string, tipo: string, ancho?: float}>  $columnas
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<int, mixed>|null  $totales  Una fila más, en negrita; null si no aplica.
     */
    public function __construct(
        public readonly string $titulo,
        public readonly string $subtitulo,
        public readonly array $columnas,
        public readonly array $filas,
        public readonly ?array $totales = null,
        public readonly string $negocio = '',
    ) {}

    public function esNumerica(int $columna): bool
    {
        return in_array($this->columnas[$columna]['tipo'] ?? self::TEXTO, [self::MONEDA, self::NUMERO, self::ENTERO], true);
    }

    /** Cómo se ve el valor en un PDF o en pantalla (en Excel va el número crudo). */
    public function formatear(int $columna, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return match ($this->columnas[$columna]['tipo'] ?? self::TEXTO) {
            self::MONEDA => '$' . number_format((float) $valor, 0, ',', '.'),
            self::ENTERO => number_format((float) $valor, 0, ',', '.'),
            self::NUMERO => rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ','),
            default      => (string) $valor,
        };
    }
}
