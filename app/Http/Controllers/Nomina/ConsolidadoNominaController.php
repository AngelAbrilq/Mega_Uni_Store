<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Services\Nomina\ReporteNominaService;
use App\Support\Exportes\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

/** NR-NOM-009 · Consolidado de nómina de todas las tiendas (y NR-INT-012 E5 por área). */
class ConsolidadoNominaController extends Controller implements HasMiddleware
{
    public function __construct(private ReporteNominaService $reportes) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:nomina.consolidado')];
    }

    public function index(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $concepto = preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string) $request->query('concepto', 'HEXTRA'))) ?: 'HEXTRA';

        return view('nomina.consolidado', [
            'desde'      => $desde,
            'hasta'      => $hasta,
            'filas'      => $this->reportes->consolidado($desde, $hasta),
            'comparacion' => $this->reportes->comparacion($desde, $hasta),
            'concepto'   => $concepto,
            'porConcepto' => $this->reportes->porConcepto($desde, $hasta, $concepto),
            'areas'      => $this->reportes->porArea($desde, $hasta),
            'alerta'     => (float) \App\Models\Setting::obtener('nomina.ratio_alerta', '30'),
            'conceptos'  => \App\Models\PayrollConcept::whereIn('type', ['devengado', 'deduccion'])->orderBy('name')->pluck('name', 'code'),
        ]);
    }

    public function excel(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        return response((new XlsxWriter())->renderLibro($this->reportes->libroExcel($desde, $hasta)), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="consolidado_nomina_' . $desde->format('Ym') . '_' . $hasta->format('Ym') . '.xlsx"',
        ]);
    }

    /** Por defecto, los últimos 6 meses. */
    private function rango(Request $request): array
    {
        try {
            $desde = Carbon::parse($request->query('desde', today()->subMonthsNoOverflow(5)->startOfMonth()->toDateString()))->startOfMonth();
            $hasta = Carbon::parse($request->query('hasta', today()->toDateString()))->endOfMonth();
        } catch (\Throwable) {
            $desde = today()->subMonthsNoOverflow(5)->startOfMonth();
            $hasta = today()->endOfMonth();
        }

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta->copy()->startOfMonth(), $desde->copy()->endOfMonth()];
        }

        if ($desde->diffInMonths($hasta) > 24) {
            $desde = $hasta->copy()->subMonthsNoOverflow(23)->startOfMonth();
        }

        return [$desde, $hasta];
    }
}
