<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SV-VTA-001 · Las ventas del día, en vivo (RN-42).
 *
 * Lo que el supervisor mira con la tienda abierta: cuánto va, cuántas
 * ventas, el ticket promedio, por qué medios entró la plata, las anuladas
 * con su motivo, y qué venta se sale de lo normal.
 *
 * «Alto valor» = el total supera ALTO_VALOR_FACTOR veces el ticket promedio
 * de los últimos 30 días del local. Se compara contra el histórico y no
 * contra el día: a las 8 a. m., con dos ventas, el promedio del día no dice
 * nada.
 *
 * Todo pasa por los global scopes: el supervisor ve SU local (o los suyos).
 */
class MonitorVentasService
{
    public const ALTO_VALOR_FACTOR = 3;

    public function __construct(private ReporteService $reportes)
    {
    }

    /** @return array<string, mixed> */
    public function resumen(Carbon $dia): array
    {
        [$desde, $hasta] = [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()];

        $pagadas = Sale::query()->pagadas()->entre($desde, $hasta);
        $total   = (float) (clone $pagadas)->sum('total');
        $numero  = (int) (clone $pagadas)->count();

        $anuladas = Sale::query()->where('status', Sale::ANULADA)->entre($desde, $hasta);

        return [
            'total'          => round($total, 2),
            'ventas'         => $numero,
            'promedio'       => $numero ? round($total / $numero, 2) : 0.0,
            'utilidad'       => round((float) (clone $pagadas)->sum('profit_total'), 2),
            'anuladas'       => (int) (clone $anuladas)->count(),
            'anulado_total'  => round((float) (clone $anuladas)->sum('total'), 2),
            'medios'         => $this->reportes->porMedio($desde, $hasta),
            'umbral'         => $this->umbralAltoValor($dia),
            'ultima_hora'    => (int) Sale::query()->pagadas()->entre(now()->subHour(), $hasta)->count(),
        ];
    }

    /**
     * Ventas del día, las más nuevas primero.
     *
     * @param string $estado todas | pagadas | anuladas | alto
     * @return Collection<int, Sale>
     */
    public function ventas(Carbon $dia, string $estado = 'todas', int $limite = 100, int $despuesDe = 0): Collection
    {
        $umbral = $this->umbralAltoValor($dia);

        return Sale::query()
            ->with(['customer:id,first_name,last_name', 'user:id,name,last_name', 'voider:id,name', 'payments:id,sale_id,method_name,amount,status'])
            ->entre($dia->copy()->startOfDay(), $dia->copy()->endOfDay())
            ->when($estado === 'pagadas', fn ($q) => $q->pagadas())
            ->when($estado === 'anuladas', fn ($q) => $q->where('status', Sale::ANULADA))
            ->when($estado === 'alto', fn ($q) => $q->pagadas()->where('total', '>=', $umbral ?? PHP_INT_MAX))
            ->when($despuesDe > 0, fn ($q) => $q->where('id', '>', $despuesDe))
            ->orderByDesc('sold_at')->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /** Desde qué total una venta es «de alto valor». Null si no hay historia. */
    public function umbralAltoValor(Carbon $dia): ?float
    {
        $hasta = $dia->copy()->startOfDay();
        $q     = Sale::query()->pagadas()->entre($hasta->copy()->subDays(30), $hasta);
        $n     = (int) (clone $q)->count();

        return $n >= 5 ? round((float) $q->avg('total') * self::ALTO_VALOR_FACTOR, 2) : null;
    }

    /** Fila lista para JSON (lo que pinta el monitor). */
    public function fila(Sale $v, ?float $umbral): array
    {
        $medios = $v->payments->pluck('method_name')->unique()->values()->all();

        return [
            'id'        => $v->id,
            'numero'    => $v->number,
            'hora'      => $v->sold_at?->format('H:i'),
            'cliente'   => $v->customer?->full_name,
            'vendedor'  => trim(($v->user?->name ?? '') . ' ' . ($v->user?->last_name ?? '')) ?: '—',
            'medios'    => $medios,
            'total'     => (float) $v->total,
            'anulada'   => $v->status === Sale::ANULADA,
            'motivo'    => $v->void_reason,
            'anulo'     => $v->voider?->name,
            'alto'      => $v->status !== Sale::ANULADA && $umbral !== null && (float) $v->total >= $umbral,
            'url'       => route('sales.show', $v),
        ];
    }
}
