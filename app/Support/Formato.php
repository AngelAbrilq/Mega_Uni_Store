<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Un solo lugar donde se decide cómo se escribe un número.
 *
 * El problema que resuelve: hoy el sistema tiene
 * `number_format($v, 0, ',', '.')` repetido en decenas de vistas. Mientras
 * todo sea pesos colombianos eso funciona. El día que alguien venda en
 * dólares —o simplemente quiera ver centavos— hay que ir a buscar cada
 * llamada a mano, y siempre se escapa una: queda un total en el recibo con
 * el formato de otro país.
 *
 * Con esto, la vista escribe `Formato::moneda($venta->total)` y la decisión
 * de cuántos decimales, qué separador y dónde va el símbolo vive en
 * Configuración › Regional.
 *
 * También hay directivas de Blade, que es como se ve en las vistas:
 *     @dinero($venta->total)      →  $1.500
 *     @cantidad($item->quantity)  →  2,5
 *     @fechahora($venta->sold_at) →  21/08/2026 14:30
 */
class Formato
{
    /** Separadores disponibles, del nombre guardado al carácter real. */
    private const SEPARADORES = [
        'punto'   => '.',
        'coma'    => ',',
        'espacio' => ' ',
        'ninguno' => '',
    ];

    /** Formatos de fecha ofrecidos, del nombre guardado al patrón de PHP. */
    public const FECHAS = [
        'dmy_slash' => 'd/m/Y',
        'dmy_guion' => 'd-m-Y',
        'ymd_guion' => 'Y-m-d',
        'mdy_slash' => 'm/d/Y',
        'largo'     => 'j \d\e F \d\e Y',
    ];

    /* ─────────────────────── Dinero ─────────────────────── */

    /**
     * «1500» → «$1.500». Es lo que va en todas las tablas y recibos.
     *
     * @param  int|null  $decimales  Fuerza los decimales; por defecto usa
     *                               los de Configuración.
     */
    public static function moneda(float|int|string|null $valor, ?int $decimales = null): string
    {
        $cfg = self::cfg();
        $n   = self::numero($valor, $decimales ?? (int) ($cfg['regional.decimales'] ?? 0));
        $sim = (string) ($cfg['regional.moneda_simbolo'] ?? '$');

        if ($sim === '') {
            return $n;
        }

        /**
         * El signo va por FUERA del símbolo: −$20.000, no $−20.000.
         *
         * Con el símbolo delante, «$-20.000» se lee como si el guion fuera
         * parte del número y no de la cantidad. En una columna de
         * movimientos, donde lo único que importa es de un vistazo si algo
         * suma o resta, esa confusión es justo la que no se puede permitir.
         */
        $negativo = str_starts_with($n, '-');
        $n        = $negativo ? substr($n, 1) : $n;

        $texto = ($cfg['regional.simbolo_posicion'] ?? 'antes') === 'despues'
            ? $n . ' ' . $sim
            : $sim . $n;

        return $negativo ? '-' . $texto : $texto;
    }

    /**
     * Igual que moneda(), pero el símbolo va envuelto en <i class="moneda">
     * para que el CSS lo pinte más pequeño y gris. Devuelve HTML, así que
     * en la vista se imprime con {!! !!}.
     */
    public static function monedaHtml(float|int|string|null $valor, ?int $decimales = null): string
    {
        $cfg = self::cfg();
        $n   = e(self::numero($valor, $decimales ?? (int) ($cfg['regional.decimales'] ?? 0)));
        $sim = e((string) ($cfg['regional.moneda_simbolo'] ?? '$'));

        if ($sim === '') {
            return $n;
        }

        // Mismo criterio que `moneda()`: el signo por fuera del símbolo.
        $negativo = str_starts_with($n, '-');
        $n        = $negativo ? substr($n, 1) : $n;

        $texto = ($cfg['regional.simbolo_posicion'] ?? 'antes') === 'despues'
            ? $n . '<i class="moneda">' . $sim . '</i>'
            : '<i class="moneda">' . $sim . '</i>' . $n;

        return $negativo ? '-' . $texto : $texto;
    }

    /* ─────────────────────── Números ─────────────────────── */

    public static function numero(float|int|string|null $valor, int $decimales = 0): string
    {
        $cfg = self::cfg();

        return number_format(
            (float) ($valor ?? 0),
            max(0, $decimales),
            self::SEPARADORES[$cfg['regional.sep_decimal'] ?? 'coma'] ?? ',',
            self::SEPARADORES[$cfg['regional.sep_miles'] ?? 'punto'] ?? '.'
        );
    }

    /**
     * Cantidades de inventario: hasta 3 decimales, pero sin ceros de relleno.
     * 2,000 → «2».  0,500 → «0,5».  1,250 → «1,25».
     *
     * Se hace así porque una tabla llena de «2,000» se lee mal cuando casi
     * todo se vende por unidad; los decimales solo importan cuando existen.
     */
    public static function cantidad(float|int|string|null $valor, int $maxDecimales = 3): string
    {
        $texto = self::numero($valor, $maxDecimales);
        $dec   = self::SEPARADORES[self::cfg()['regional.sep_decimal'] ?? 'coma'] ?? ',';

        if ($dec === '' || ! str_contains($texto, $dec)) {
            return $texto;
        }

        return rtrim(rtrim($texto, '0'), $dec);
    }

    public static function porcentaje(float|int|string|null $valor, int $decimales = 0): string
    {
        return self::numero($valor, $decimales) . '%';
    }

    /* ─────────────────────── Fechas ─────────────────────── */

    public static function fecha($fecha): string
    {
        if (! $fecha) {
            return '—';
        }

        return self::aCarbon($fecha)->format(self::patronFecha());
    }

    public static function fechaHora($fecha): string
    {
        if (! $fecha) {
            return '—';
        }

        return self::aCarbon($fecha)->format(self::patronFecha() . ' H:i');
    }

    public static function hora($fecha): string
    {
        return $fecha ? self::aCarbon($fecha)->format('H:i') : '—';
    }

    /**
     * Una fecha escrita con palabras, en el idioma en que está el panel.
     *
     * ── Por qué hace falta ──
     *
     * Las vistas venían escribiendo `->locale('es')->isoFormat(...)` a mano,
     * 53 veces. Mientras el sistema estuvo solo en español eso no se notaba;
     * al traducirlo se vuelve un error que no falla: el panel en inglés
     * imprime «24 ago» y «lunes» en mitad de una pantalla en inglés.
     *
     * Aquí el idioma sale de la aplicación y la zona horaria de la
     * configuración del negocio. Una sola línea que cambiar el día que entre
     * un tercer idioma.
     *
     * @param  string  $patron  patrón de isoFormat, no de date()
     */
    public static function enPalabras($fecha, string $patron = 'D MMM YYYY, HH:mm', string $siNo = '—'): string
    {
        if (! $fecha) {
            return $siNo;
        }

        return self::aCarbon($fecha)->locale(app()->getLocale())->isoFormat($patron);
    }

    /** «hace 2 horas» / «2 hours ago», también en el idioma del panel. */
    public static function haceCuanto($fecha, bool $corto = false, string $siNo = '—'): string
    {
        if (! $fecha) {
            return $siNo;
        }

        return self::aCarbon($fecha)->locale(app()->getLocale())->diffForHumans(null, $corto);
    }

    public static function patronFecha(): string
    {
        $clave = self::cfg()['regional.formato_fecha'] ?? 'dmy_slash';

        return self::FECHAS[$clave] ?? 'd/m/Y';
    }

    /** Pasa la fecha a la zona horaria configurada antes de escribirla. */
    private static function aCarbon($fecha): Carbon
    {
        $c = $fecha instanceof Carbon ? $fecha->copy() : Carbon::parse($fecha);

        return $c->setTimezone(self::zona());
    }

    public static function zona(): string
    {
        return (self::cfg()['regional.zona_horaria'] ?? '') ?: 'America/Bogota';
    }

    /* ─────────────────────── Redondeo del total ─────────────────────── */

    /**
     * Redondea el total de la venta al múltiplo configurado.
     *
     * En Colombia la moneda de $50 ya casi no circula y muchos negocios
     * redondean el total. Se hace sobre el TOTAL, nunca sobre cada línea:
     * redondear línea por línea acumula error y el recibo no cuadra con la
     * suma de sus renglones.
     */
    public static function redondear(float $total): float
    {
        $paso = (int) (self::cfg()['venta.redondeo'] ?? 0);

        if ($paso <= 1) {
            return round($total, 2);
        }

        return (float) (round($total / $paso) * $paso);
    }

    /* ─────────────────────── Interno ─────────────────────── */

    /**
     * Los ajustes, ya con los valores de fábrica mezclados.
     *
     * Aun así, cada lectura de abajo lleva su `?? valor`. Es cinturón y
     * tirantes a propósito: esta clase la llama CADA número que se pinta en
     * el sistema, y si le falta una clave no se rompe una pantalla — se
     * rompen todas. Un `??` cuesta nada; un 500 en la caja cuesta una venta.
     *
     * @return array<string,string>
     */
    private static function cfg(): array
    {
        return Setting::todos();
    }
}
