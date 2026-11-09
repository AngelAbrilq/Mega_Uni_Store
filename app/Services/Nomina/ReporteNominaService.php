<?php

namespace App\Services\Nomina;

use App\Models\Department;
use App\Models\OvertimeEntry;
use App\Models\Payroll;
use App\Models\PayrollEmployee;
use App\Models\PayrollItem;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Tienda;
use App\Support\Exportes\Tabla;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reportes de nómina.
 *
 *   NR-NOM-005 E5  horas extra del período por empleado (exporta a Excel)
 *   NR-NOM-009     consolidado de todas las tiendas: resumen, comparación
 *                  mes a mes, desglose por concepto, costo vs ingresos
 *   NR-INT-012 E5  costo de personal por área (centro de costo)
 *
 * Cuenta solo nóminas aprobadas o pagadas (normales y de ajuste): un
 * borrador todavía puede cambiar. Costo total = devengado + aportes patronales.
 */
class ReporteNominaService
{
    /* ═════════════ Horas extra (NR-NOM-005 E5) ═════════════ */

    public function horasExtra(Carbon $desde, Carbon $hasta, ?string $estado = null): Collection
    {
        return OvertimeEntry::query()->deTienda(null)
            ->whereDate('date', '>=', $desde)->whereDate('date', '<=', $hasta)
            ->when($estado, fn ($q) => $q->where('status', $estado))
            ->with('employee:id,first_name,last_name,code,deleted_at')
            ->orderBy('date')->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $g) => [
                'empleado' => $g->first()->employee,
                'horas'    => (float) $g->sum('hours'),
                'valor'    => (float) $g->sum('amount'),
                'por_tipo' => $g->groupBy('type')->map(fn ($t) => ['horas' => (float) $t->sum('hours'), 'valor' => (float) $t->sum('amount')]),
                'estados'  => $g->countBy('status'),
                'filas'    => $g,
            ])
            ->sortByDesc('valor')->values();
    }

    public function tablaHorasExtra(Carbon $desde, Carbon $hasta, ?string $estado = null): Tabla
    {
        $filas = [];

        foreach ($this->horasExtra($desde, $hasta, $estado) as $g) {
            foreach ($g['filas'] as $h) {
                $filas[] = [$g['empleado']->nombre_completo, $h->date->format('Y-m-d'), $h->tipoTexto(), (float) $h->hours,
                            (float) $h->factor, (float) $h->amount, OvertimeEntry::ESTADOS[$h->status] ?? $h->status];
            }
        }

        return new Tabla('Horas extra', $desde->format('d/m/Y') . ' – ' . $hasta->format('d/m/Y'), [
            ['etiqueta' => 'Empleado', 'tipo' => Tabla::TEXTO], ['etiqueta' => 'Fecha', 'tipo' => Tabla::TEXTO],
            ['etiqueta' => 'Tipo', 'tipo' => Tabla::TEXTO], ['etiqueta' => 'Horas', 'tipo' => Tabla::NUMERO],
            ['etiqueta' => 'Factor', 'tipo' => Tabla::NUMERO], ['etiqueta' => 'Valor', 'tipo' => Tabla::MONEDA],
            ['etiqueta' => 'Estado', 'tipo' => Tabla::TEXTO],
        ], $filas, ['Total', '', '', array_sum(array_column($filas, 3)), '', array_sum(array_column($filas, 5)), ''],
            (string) Setting::obtener('negocio.nombre'));
    }

    /* ═════════════ Consolidado (NR-NOM-009) ═════════════ */

    /** E1 + E5 · por tienda en el rango: empleados, devengado, deducciones, neto, aportes, costo, ingresos, ratio. */
    public function consolidado(Carbon $desde, Carbon $hasta): Collection
    {
        $nominas = $this->definitivas($desde, $hasta)->get();
        $alerta  = (float) Setting::obtener('nomina.ratio_alerta', '30');

        $ingresos = Sale::query()->where('status', Sale::PAGADA)
            ->whereBetween('sold_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->selectRaw('tienda_id, SUM(total) as total')->groupBy('tienda_id')->pluck('total', 'tienda_id');

        $empleados = PayrollEmployee::whereIn('payroll_id', $nominas->pluck('id'))
            ->join('payrolls', 'payrolls.id', '=', 'payroll_employees.payroll_id')
            ->selectRaw('payrolls.tienda_id, COUNT(DISTINCT payroll_employees.employee_id) as n')
            ->groupBy('payrolls.tienda_id')->pluck('n', 'tienda_id');

        return $this->tiendas()->map(function (Tienda $t) use ($nominas, $ingresos, $empleados, $alerta) {
            $propias = $nominas->where('tienda_id', $t->id);
            $costo   = (float) $propias->sum('total_earned') + (float) $propias->sum('total_employer');
            $ingreso = (float) ($ingresos[$t->id] ?? 0);
            $ratio   = $ingreso > 0 ? round($costo / $ingreso * 100, 1) : null;

            return [
                'tienda'      => $t,
                'nominas'     => $propias->count(),
                'empleados'   => (int) ($empleados[$t->id] ?? 0),
                'devengado'   => (float) $propias->sum('total_earned'),
                'deducciones' => (float) $propias->sum('total_deductions'),
                'neto'        => (float) $propias->sum('total_net'),
                'aportes'     => (float) $propias->sum('total_employer'),
                'costo'       => $costo,
                'ingresos'    => $ingreso,
                'ratio'       => $ratio,
                'alerta'      => $ratio !== null && $ratio > $alerta,
            ];
        });
    }

    /**
     * E2 · Costo total por tienda y mes, con variación % contra el mes anterior.
     * @return array{meses: array<int, string>, filas: Collection, totales: array<string, float>}
     */
    public function comparacion(Carbon $desde, Carbon $hasta): array
    {
        $meses = [];

        for ($m = $desde->copy()->startOfMonth(); $m->lte($hasta); $m->addMonth()) {
            $meses[] = $m->format('Y-m');
        }

        $nominas = $this->definitivas($desde->copy()->startOfMonth(), $hasta->copy()->endOfMonth())->get();
        $totales = array_fill_keys($meses, 0.0);

        $filas = $this->tiendas()->map(function (Tienda $t) use ($nominas, $meses, &$totales) {
            $valores = [];
            $anterior = null;

            foreach ($meses as $mes) {
                $del = $nominas->where('tienda_id', $t->id)->filter(fn ($n) => $n->period_start->format('Y-m') === $mes);
                $costo = (float) $del->sum('total_earned') + (float) $del->sum('total_employer');
                $valores[$mes] = [
                    'costo'     => $costo,
                    'variacion' => $anterior ? round(($costo - $anterior) / $anterior * 100, 1) : null,
                ];
                $totales[$mes] += $costo;
                $anterior = $costo ?: null;
            }

            return ['tienda' => $t, 'meses' => $valores];
        });

        return ['meses' => $meses, 'filas' => $filas, 'totales' => $totales];
    }

    /** E3 · Total pagado por un concepto, por tienda y por nombre (p. ej. tipo de hora extra), con ranking. */
    public function porConcepto(Carbon $desde, Carbon $hasta, string $codigo): array
    {
        $items = PayrollItem::query()
            ->join('payroll_employees as pe', 'pe.id', '=', 'payroll_items.payroll_employee_id')
            ->join('payrolls as p', 'p.id', '=', 'pe.payroll_id')
            ->whereIn('p.id', $this->definitivas($desde, $hasta)->pluck('id'))
            ->where('payroll_items.code', $codigo)
            ->selectRaw('p.tienda_id, payroll_items.name, SUM(payroll_items.amount) as total, SUM(payroll_items.quantity) as cantidad')
            ->groupBy('p.tienda_id', 'payroll_items.name')->get();

        $tiendas = $this->tiendas()->pluck('name', 'id');

        $ranking = $items->groupBy('tienda_id')
            ->map(fn ($g, $id) => ['tienda' => $tiendas[$id] ?? "#{$id}", 'total' => (float) $g->sum('total'),
                                   'detalle' => $g->map(fn ($i) => ['nombre' => $i->name, 'total' => (float) $i->total, 'cantidad' => (float) $i->cantidad])->values()])
            ->sortByDesc('total')->values();

        return ['ranking' => $ranking, 'total' => (float) $items->sum('total')];
    }

    /** NR-INT-012 E5 · Costo de personal por área (centro de costo). */
    public function porArea(Carbon $desde, Carbon $hasta): Collection
    {
        $filas = PayrollEmployee::query()
            ->whereIn('payroll_id', $this->definitivas($desde, $hasta)->pluck('id'))
            ->with('contribution:id,payroll_employee_id,total_employer')
            ->get(['id', 'payroll_id', 'department_id', 'employee_id', 'earned']);

        $areas = Department::withoutGlobalScopes()->pluck('name', 'id');

        return $filas->groupBy(fn ($f) => $f->department_id ?? 0)->map(fn ($g, $id) => [
            'area'      => $id ? ($areas[$id] ?? "#{$id}") : 'Sin área asignada',
            'sin_area'  => ! $id,
            'empleados' => $g->pluck('employee_id')->unique()->count(),
            'costo'     => (float) $g->sum('earned') + (float) $g->sum(fn ($f) => $f->contribution?->total_employer),
        ])->sortByDesc('costo')->values();
    }

    /** E4 · Libro de Excel: Resumen general · Detalle por tienda · Detalle por empleado · Tendencia. */
    public function libroExcel(Carbon $desde, Carbon $hasta): array
    {
        $negocio = (string) Setting::obtener('negocio.nombre');
        $rango   = $desde->format('d/m/Y') . ' – ' . $hasta->format('d/m/Y');
        $c       = $this->consolidado($desde, $hasta);

        $resumen = new Tabla('Resumen general', $rango, [
            ['etiqueta' => 'Tienda', 'tipo' => Tabla::TEXTO], ['etiqueta' => 'Empleados', 'tipo' => Tabla::ENTERO],
            ['etiqueta' => 'Devengado', 'tipo' => Tabla::MONEDA], ['etiqueta' => 'Deducciones', 'tipo' => Tabla::MONEDA],
            ['etiqueta' => 'Neto pagado', 'tipo' => Tabla::MONEDA], ['etiqueta' => 'Aportes patronales', 'tipo' => Tabla::MONEDA],
            ['etiqueta' => 'Costo total', 'tipo' => Tabla::MONEDA], ['etiqueta' => 'Ingresos', 'tipo' => Tabla::MONEDA],
            ['etiqueta' => 'Costo / ingresos %', 'tipo' => Tabla::NUMERO],
        ], $c->map(fn ($r) => [$r['tienda']->name, $r['empleados'], $r['devengado'], $r['deducciones'], $r['neto'], $r['aportes'], $r['costo'], $r['ingresos'], $r['ratio']])->all(),
            ['Total', $c->sum('empleados'), $c->sum('devengado'), $c->sum('deducciones'), $c->sum('neto'), $c->sum('aportes'), $c->sum('costo'), $c->sum('ingresos'), null], $negocio);

        $nominas = $this->definitivas($desde, $hasta)->with('tienda:id,name')->orderBy('period_start')->get();
        $porTienda = new Tabla('Detalle por tienda', $rango, [
            ['etiqueta' => 'Tienda', 'tipo' => Tabla::TEXTO], ['etiqueta' => 'Nómina', 'tipo' => Tabla::TEXTO],
            ['etiqueta' => 'Período', 'tipo' => Tabla::TEXTO], ['etiqueta' => 'Tipo', 'tipo' => Tabla::TEXTO],
            ['etiqueta' => 'Empleados', 'tipo' => Tabla::ENTERO], ['etiqueta' => 'Devengado', 'tipo' => Tabla::MONEDA],
            ['etiqueta' => 'Deducciones', 'tipo' => Tabla::MONEDA], ['etiqueta' => 'Neto', 'tipo' => Tabla::MONEDA],
            ['etiqueta' => 'Aportes', 'tipo' => Tabla::MONEDA], ['etiqueta' => 'Estado', 'tipo' => Tabla::TEXTO],
        ], $nominas->map(fn ($n) => [$n->tienda?->name, $n->number, $n->period_start->format('Y-m-d') . ' a ' . $n->period_end->format('Y-m-d'),
            $n->esAjuste() ? 'Ajuste' : 'Normal', $n->employees_count, (float) $n->total_earned, (float) $n->total_deductions,
            (float) $n->total_net, (float) $n->total_employer, Payroll::ESTADOS[$n->status]])->all(), null, $negocio);

        $filas = PayrollEmployee::whereIn('payroll_id', $nominas->pluck('id'))
            ->with(['employee:id,first_name,last_name,document_number,deleted_at', 'payroll:id,number,tienda_id', 'payroll.tienda:id,name', 'contribution', 'department:id,name'])
            ->get();

        $porEmpleado = new Tabla('Detalle por empleado', $rango, [
            ['etiqueta' => 'Tienda', 'tipo' => Tabla::TEXTO], ['etiqueta' => 'Nómina', 'tipo' => Tabla::TEXTO],
            ['etiqueta' => 'Empleado', 'tipo' => Tabla::TEXTO], ['etiqueta' => 'Documento', 'tipo' => Tabla::TEXTO],
            ['etiqueta' => 'Área', 'tipo' => Tabla::TEXTO], ['etiqueta' => 'Días', 'tipo' => Tabla::ENTERO],
            ['etiqueta' => 'IBC', 'tipo' => Tabla::MONEDA], ['etiqueta' => 'Devengado', 'tipo' => Tabla::MONEDA],
            ['etiqueta' => 'Deducciones', 'tipo' => Tabla::MONEDA], ['etiqueta' => 'Neto', 'tipo' => Tabla::MONEDA],
            ['etiqueta' => 'Aportes', 'tipo' => Tabla::MONEDA],
        ], $filas->map(fn ($f) => [$f->payroll->tienda?->name, $f->payroll->number, $f->employee->nombre_completo, $f->employee->document_number,
            $f->department?->name, $f->days_worked, (float) $f->ibc, (float) $f->earned, (float) $f->deductions, (float) $f->net,
            (float) $f->contribution?->total_employer])->all(), null, $negocio);

        $comp = $this->comparacion($desde, $hasta);
        $columnas = [['etiqueta' => 'Tienda', 'tipo' => Tabla::TEXTO]];

        foreach ($comp['meses'] as $mes) {
            $columnas[] = ['etiqueta' => $mes, 'tipo' => Tabla::MONEDA];
            $columnas[] = ['etiqueta' => 'Var. %', 'tipo' => Tabla::NUMERO];
        }

        $tendencia = new Tabla('Tendencia mensual', 'Costo total por mes y variación', $columnas,
            $comp['filas']->map(function ($r) {
                $fila = [$r['tienda']->name];
                foreach ($r['meses'] as $v) {
                    $fila[] = $v['costo'];
                    $fila[] = $v['variacion'];
                }

                return $fila;
            })->all(), null, $negocio);

        return [$resumen, $porTienda, $porEmpleado, $tendencia];
    }

    /** Los locales de la empresa en contexto (Tienda no tiene filtro global). */
    private function tiendas(): Collection
    {
        return Tienda::where('empresa_id', \App\Support\Contexto::empresaId())->orderBy('name')->get(['id', 'name']);
    }

    private function definitivas(Carbon $desde, Carbon $hasta)
    {
        return Payroll::query()->whereIn('status', [Payroll::APROBADA, Payroll::PAGADA])
            ->whereDate('period_start', '>=', $desde)->whereDate('period_start', '<=', $hasta);
    }
}
