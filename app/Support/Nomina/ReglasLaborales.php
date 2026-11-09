<?php

namespace App\Support\Nomina;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Parámetros legales de la nómina colombiana (RN-45).
 *
 * Todo sale de «Ajustes» (grupo `nomina.*`) con valores de fábrica de 2026,
 * así que un cambio normativo se resuelve configurando, no programando:
 *
 *   SMLMV 2026 ............ $1.750.905  (Decreto 1469 de 2025)
 *   Auxilio de transporte .. $249.095   (Decreto 1470 de 2025)
 *   Horas al mes ........... 220 hasta el 14-jul-2026, 210 desde el 15-jul-2026
 *                            (Ley 2101 de 2021: jornada de 44 h → 42 h semanales)
 *   Recargo dominical/fest.  80 % hasta el 30-jun-2026, 90 % desde el 1-jul-2026
 *                            (Ley 2466 de 2025)
 *   Extra diurna 25 % · extra nocturna 75 % · recargo nocturno 35 %
 *
 * Los valores «auto» aplican el calendario de la ley según la FECHA del
 * hecho (una hora extra del 10 de julio usa 220; una del 20, 210). Un
 * número fijo en el ajuste pisa el calendario.
 *
 * La HU NR-NOM-005 trae 240 h y recargos de 2019 (25/75/75/100 %). Se
 * documenta como desviación consciente: la ley vigente manda.
 */
final class ReglasLaborales
{
    public const TIPOS_HORA = [
        'diurna'             => 'Extra diurna',
        'nocturna'           => 'Extra nocturna',
        'dominical_diurna'   => 'Extra dominical/festiva diurna',
        'dominical_nocturna' => 'Extra dominical/festiva nocturna',
        'recargo_nocturno'   => 'Recargo nocturno',
        'recargo_dominical'  => 'Recargo dominical/festivo',
    ];

    /** Tarifa ARL por clase de riesgo (Decreto 1772 de 1994), en %. */
    public const ARL = [1 => 0.522, 2 => 1.044, 3 => 2.436, 4 => 4.350, 5 => 6.960];

    public static function smlmv(): float
    {
        return (float) Setting::obtener('nomina.smlmv', '1750905');
    }

    public static function auxilioTransporte(): float
    {
        return (float) Setting::obtener('nomina.auxilio_transporte', '249095');
    }

    public static function horasMes(?CarbonInterface $fecha = null): int
    {
        $ajuste = (string) Setting::obtener('nomina.horas_mes', 'auto');

        if ($ajuste !== 'auto' && (int) $ajuste > 0) {
            return (int) $ajuste;
        }

        $fecha ??= today();

        return match (true) {
            $fecha->lt(Carbon::create(2025, 7, 15)) => 230,  // 46 h
            $fecha->lt(Carbon::create(2026, 7, 15)) => 220,  // 44 h
            default                                 => 210,  // 42 h
        };
    }

    /** Recargo dominical/festivo como fracción (0,80 · 0,90 · 1,00). */
    public static function recargoDominical(?CarbonInterface $fecha = null): float
    {
        $ajuste = (string) Setting::obtener('nomina.recargo_dominical', 'auto');

        if ($ajuste !== 'auto' && is_numeric($ajuste)) {
            return (float) $ajuste / 100;
        }

        $fecha ??= today();

        return match (true) {
            $fecha->lt(Carbon::create(2025, 7, 1)) => 0.75,
            $fecha->lt(Carbon::create(2026, 7, 1)) => 0.80,
            $fecha->lt(Carbon::create(2027, 7, 1)) => 0.90,
            default                                => 1.00,
        };
    }

    /**
     * Multiplicador sobre la hora ordinaria.
     * Las horas extra incluyen la hora (1 + recargo); los recargos puros
     * pagan solo el adicional porque la hora ya está en el salario.
     */
    public static function factor(string $tipo, ?CarbonInterface $fecha = null): float
    {
        $dom = self::recargoDominical($fecha);

        return round(match ($tipo) {
            'diurna'             => 1.25,
            'nocturna'           => 1.75,
            'dominical_diurna'   => 1 + $dom + 0.25,
            'dominical_nocturna' => 1 + $dom + 0.75,
            'recargo_nocturno'   => 0.35,
            'recargo_dominical'  => $dom,
            default              => throw new \InvalidArgumentException("Tipo de hora desconocido: {$tipo}"),
        }, 4);
    }

    public static function valorHora(float $salario, ?CarbonInterface $fecha = null): float
    {
        return round($salario / self::horasMes($fecha), 2);
    }

    /** ¿Aplica la exoneración del art. 114-1 E.T. (salud 8,5 %, SENA, ICBF)? */
    public static function exonerado(float $salario): bool
    {
        return Setting::activo('nomina.exoneracion_114') && $salario < 10 * self::smlmv();
    }

    /** Fondo de solidaridad pensional, fracción según IBC en SMLMV. */
    public static function fondoSolidaridad(float $ibc): float
    {
        $veces = $ibc / self::smlmv();

        return match (true) {
            $veces < 4  => 0.0,
            $veces < 16 => 0.010,
            $veces < 17 => 0.012,
            $veces < 18 => 0.014,
            $veces < 19 => 0.016,
            $veces < 20 => 0.018,
            default     => 0.020,
        };
    }

    /**
     * Días comerciales (mes de 30) entre dos fechas del mismo mes.
     * El día 31 no cuenta y el último día de febrero vale como 30.
     */
    public static function diasComerciales(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        if ($hasta->lt($desde)) {
            return 0;
        }

        $total = 0;
        $cursor = $desde->copy()->startOfDay();

        while ($cursor->lte($hasta)) {
            $finMes = $cursor->copy()->endOfMonth()->startOfDay();
            $fin    = $hasta->lt($finMes) ? $hasta->copy() : $finMes;

            $d1 = min($cursor->day, 30);
            $d2 = $fin->isSameDay($finMes) ? 30 : min($fin->day, 30);
            $total += max(0, $d2 - $d1 + 1);

            $cursor = $finMes->copy()->addDay();
        }

        return $total;
    }

    /** Días hábiles (lunes a sábado) entre dos fechas, ambas incluidas. */
    public static function diasHabiles(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        $n = 0;

        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            if (! $d->isSunday()) {
                $n++;
            }
        }

        return $n;
    }

    public static function redondear(float $valor): float
    {
        return round($valor, 0);
    }
}
