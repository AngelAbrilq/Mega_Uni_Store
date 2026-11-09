<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;
use App\Support\Formato;
use Illuminate\Support\Str;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\Printer;

/**
 * Recibo de venta en bytes ESC/POS (RN-28, fase 2).
 *
 * El servidor arma los bytes en memoria (MemoryPrintConnector: no toca
 * hardware) y el POS se los pasa al agente de caja, que los manda a la
 * térmica. Así el mismo recibo sale igual en cualquier local, sin que la
 * nube tenga que alcanzar la impresora.
 *
 * ── Por qué en ASCII ──
 * Cada térmica trae su propia tabla de caracteres (CP437, CP850, PC858…) y
 * una tilde mal mapeada sale como basura en el papel. Str::ascii() deja
 * «Lápiz» como «Lapiz»: legible en TODAS las impresoras.
 */
final class ReciboEscPos
{
    /** Columnas por ancho de papel (fuente A). */
    private const COLUMNAS = ['80' => 48, '58' => 32];

    /** ¿Este negocio imprime en térmica? (A4 se imprime desde el navegador.) */
    public static function aplica(): bool
    {
        return array_key_exists((string) Setting::obtener('recibo.formato', '80'), self::COLUMNAS);
    }

    /**
     * @return string bytes ESC/POS (una o varias copias), listos para el agente
     */
    public function generar(Sale $venta): string
    {
        $cfg     = Setting::todos();
        $formato = (string) ($cfg['recibo.formato'] ?? '80');
        $ancho   = self::COLUMNAS[$formato] ?? self::COLUMNAS['80'];
        $copias  = max(1, min(5, (int) ($cfg['recibo.copias'] ?? 1)));

        $venta->loadMissing(['items', 'payments', 'customer', 'user:id,name', 'tienda']);

        $conector = new MemoryPrintConnector();
        $p        = new Printer($conector);

        try {
            $this->encabezado($p, $venta, $cfg, $ancho);
            $this->renglones($p, $venta, $ancho);
            $this->totales($p, $venta, $cfg, $ancho);
            $this->pie($p, $cfg, $ancho);
            $p->cut();

            $bytes = $conector->getData();
        } finally {
            $p->close();
        }

        return str_repeat($bytes, $copias);
    }

    /* ═══════════════ Partes del recibo ═══════════════ */

    private function encabezado(Printer $p, Sale $venta, array $cfg, int $ancho): void
    {
        $local = $venta->tienda;

        $p->setJustification(Printer::JUSTIFY_CENTER);
        $p->setEmphasis(true);
        $nombre = $this->a($local?->razon_social ?: ($cfg['negocio.nombre'] ?? 'Mega Uni Store'));
        $doble  = mb_strlen($nombre) * 2 <= $ancho; // doble ancho solo si cabe en una línea

        $p->setTextSize($doble ? 2 : 1, 1);
        $p->text($this->ajustar($nombre, $doble ? intdiv($ancho, 2) : $ancho));
        $p->setTextSize(1, 1);
        $p->setEmphasis(false);

        foreach ([
            ($local?->nit ?: ($cfg['negocio.nit'] ?? null)) ? 'NIT ' . ($local?->nit ?: $cfg['negocio.nit']) : null,
            $local?->direccion ?: ($cfg['negocio.direccion'] ?? null),
            $local?->telefono ?: ($cfg['negocio.telefono'] ?? null),
        ] as $linea) {
            if ($linea) {
                $p->text($this->ajustar($this->a($linea), $ancho));
            }
        }

        $p->feed();
        $p->setEmphasis(true);
        $p->text($this->ajustar('Venta ' . $this->a($venta->number), $ancho));
        $p->setEmphasis(false);
        $p->text($this->ajustar($this->a(Formato::fechaHora($venta->sold_at)), $ancho));

        if (($cfg['recibo.mostrar_cajero'] ?? '1') === '1' && $venta->user) {
            $p->text($this->ajustar('Atendio: ' . $this->a($venta->user->name), $ancho));
        }

        if ($venta->customer) {
            $p->text($this->ajustar('Cliente: ' . $this->a(trim($venta->customer->first_name . ' ' . $venta->customer->last_name)), $ancho));
        }

        $p->setJustification(Printer::JUSTIFY_LEFT);
    }

    private function renglones(Printer $p, Sale $venta, int $ancho): void
    {
        $p->text(str_repeat('-', $ancho) . "\n");

        foreach ($venta->items as $item) {
            // Nombre completo (partido en varias líneas si no cabe)…
            $p->text($this->ajustar($this->a($item->name), $ancho));

            // …y debajo: «  2 x $12.000            $24.000»
            $detalle = '  ' . Formato::cantidad($item->quantity) . ' x ' . $this->a(Formato::moneda($item->unit_price));
            // Valor del renglón SIN impuesto: así los renglones suman el Subtotal
            // y el IVA aparece una sola vez abajo (antes salía $9.282 junto a un Subtotal de $7.800).
            $p->text($this->fila($detalle, $this->a(Formato::moneda($item->subtotal)), $ancho));

            if ((float) $item->discount > 0) {
                $p->text($this->fila('  Descuento', '-' . $this->a(Formato::moneda($item->discount)), $ancho));
            }
        }

        $p->text(str_repeat('-', $ancho) . "\n");
    }

    private function totales(Printer $p, Sale $venta, array $cfg, int $ancho): void
    {
        $p->text($this->fila('Subtotal', $this->a(Formato::moneda($venta->subtotal)), $ancho));

        if ((float) $venta->discount_total > 0 && ($cfg['recibo.mostrar_ahorro'] ?? '1') === '1') {
            $p->text($this->fila('Ahorraste', $this->a(Formato::moneda($venta->discount_total)), $ancho));
        }

        if ((float) $venta->tax_total > 0) {
            $p->text($this->fila('Impuestos', $this->a(Formato::moneda($venta->tax_total)), $ancho));
        }

        $p->setEmphasis(true);
        $p->setTextSize(1, 2);
        $p->text($this->fila('TOTAL', $this->a(Formato::moneda($venta->total)), $ancho));
        $p->setTextSize(1, 1);
        $p->setEmphasis(false);

        foreach ($venta->payments as $pago) {
            $p->text($this->fila($this->a($pago->method_name), $this->a(Formato::moneda($pago->amount)), $ancho));
        }

        if ((float) $venta->change_amount > 0) {
            $p->text($this->fila('Cambio', $this->a(Formato::moneda($venta->change_amount)), $ancho));
        }
    }

    private function pie(Printer $p, array $cfg, int $ancho): void
    {
        $p->feed();
        $p->setJustification(Printer::JUSTIFY_CENTER);

        foreach ([$cfg['recibo.mensaje'] ?? null, $cfg['recibo.pie'] ?? null] as $texto) {
            if (filled($texto)) {
                $p->text($this->ajustar($this->a($texto), $ancho));
            }
        }

        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->feed(2);
    }

    /* ═══════════════ Apoyo ═══════════════ */

    /** «Texto a la izquierda ........ valor a la derecha», ajustado al ancho. */
    private function fila(string $izquierda, string $derecha, int $ancho): string
    {
        $espacio = $ancho - mb_strlen($derecha) - 1;
        $izquierda = mb_strlen($izquierda) > $espacio ? mb_substr($izquierda, 0, max(1, $espacio)) : $izquierda;

        return str_pad($izquierda, $ancho - mb_strlen($derecha)) . $derecha . "\n";
    }

    /** Parte el texto en líneas que caben en el papel (corta palabras solo si una sola no cabe). */
    private function ajustar(string $texto, int $ancho): string
    {
        return wordwrap($texto, $ancho, "\n", true) . "\n";
    }

    /** ASCII seguro para cualquier tabla de caracteres (ver encabezado). */
    private function a(?string $texto): string
    {
        // Los signos de apertura (¡ ¿) no existen en ASCII: se quitan en vez de volverlos «!».
        return Str::ascii(str_replace(['¡', '¿'], '', (string) $texto));
    }
}
