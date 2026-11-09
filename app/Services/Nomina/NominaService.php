<?php

namespace App\Services\Nomina;

use App\Events\NominaAprobada;
use App\Events\NominaPagada;
use App\Exceptions\NominaInmutable;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\LeaveRequest;
use App\Models\OvertimeEntry;
use App\Models\Payroll;
use App\Models\PayrollConcept;
use App\Models\PayrollContribution;
use App\Models\PayrollEmployee;
use App\Models\PayrollItem;
use App\Models\User;
use App\Support\Contexto;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * NR-NOM-002/003/004/010 · Liquidación de nómina (RN-45).
 *
 * Por empleado con contrato laboral vigente en el período:
 *
 *   días        = días comerciales (mes de 30) entre max(inicio período, inicio contrato)
 *                 y min(fin período, fin/terminación del contrato)
 *   diario      = salario / 30
 *   SALARIO     = diario × (días − días de vacaciones)
 *   VACACION    = diario × días de vacaciones aprobadas dentro del período
 *   AUXTRANS    = auxilio / 30 × (días − vacaciones − no remunerados), si salario ≤ 2 SMLMV
 *   HEXTRA      = horas extra APROBADAS con fecha en el período y sin nómina (una línea por tipo)
 *   AUSENCIA    = diario × días no remunerados (licencia no remunerada + injustificada)  [deducción]
 *   obligatorios del catálogo (fijo, % salario, % SMLMV) + los manuales del borrador
 *
 *   IBC         = devengados salariales − ausencias; piso SMLMV proporcional; tope 25 SMLMV
 *   SALUD 4 % · PENSION 4 % · FSP 1–2 % si IBC ≥ 4 SMLMV            (deducciones)
 *   Empresa: salud 8,5 % · pensión 12 % · ARL por clase · caja 4 % · ICBF 3 % · SENA 2 %
 *            (salud, ICBF y SENA en 0 con exoneración art. 114-1 y salario < 10 SMLMV)
 *   Aprendiz SENA: sin deducciones; la empresa paga salud 12,5 % y ARL.
 *
 * Las tasas de SALUD, PENSION y CAJA salen del valor del concepto (parametrizable).
 * La nómina nace en borrador; aprobar la congela (NominaInmutable) y emite NominaAprobada.
 */
class NominaService
{
    /** Concepto del sistema por código, de la empresa en contexto. */
    private ?Collection $conceptos = null;

    /* ═════════════ Catálogo (NR-NOM-002 E2) ═════════════ */

    /** Crea los conceptos obligatorios si la empresa todavía no los tiene. */
    public function asegurarConceptos(?int $empresaId = null): void
    {
        $empresaId ??= Contexto::empresaId();

        if (! $empresaId) {
            return;
        }

        $tasas = ['SALUD' => 4, 'PENSION' => 4, 'CAJA' => 4];

        foreach (PayrollConcept::DEL_SISTEMA as [$code, $name, $type, $mandatory, $salary]) {
            PayrollConcept::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresaId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'calculation' => 'sistema', 'value' => $tasas[$code] ?? 0,
                 'is_mandatory' => $mandatory, 'is_salary' => $salary, 'is_system' => true, 'is_active' => true],
            );
        }

        $this->conceptos = null;
    }

    /* ═════════════ Liquidar (NR-NOM-003 E1…E5 · NR-NOM-010 E1…E3) ═════════════ */

    public function liquidar(Carbon $desde, Carbon $hasta, User $quien, ?string $notas = null): Payroll
    {
        $this->validarPeriodo($desde, $hasta);
        $this->asegurarConceptos();

        return DB::transaction(function () use ($desde, $hasta, $quien, $notas) {
            $tiendaId = Contexto::tiendaId();

            if (! $tiendaId) {
                throw ValidationException::withMessages(['period_start' => 'Elige primero el local que vas a liquidar.']);
            }

            $cruce = Payroll::deTienda($tiendaId)->where('kind', 'normal')
                ->whereDate('period_start', '<=', $hasta)->whereDate('period_end', '>=', $desde)
                ->lockForUpdate()->first();

            if ($cruce) {
                throw ValidationException::withMessages(['period_start' => "Ya existe la nómina {$cruce->number} para ese período."]);
            }

            $nomina = Payroll::create([
                'tienda_id'    => $tiendaId,
                'number'       => 'NOM-' . $desde->format('Ymd'),
                'period_start' => $desde,
                'period_end'   => $hasta,
                'parameters'   => $this->parametros($hasta),
                'created_by'   => $quien->id,
                'notes'        => $notas,
            ]);

            $this->calcular($nomina);

            return $nomina->refresh();
        });
    }

    /** Vuelve a calcular el borrador (después de aprobar horas, cambiar un contrato…). Conserva lo manual. */
    public function recalcular(Payroll $nomina): Payroll
    {
        $this->soloBorrador($nomina);

        return DB::transaction(function () use ($nomina) {
            $manuales = $this->manuales($nomina);
            $empleados = $nomina->esAjuste() ? $nomina->employees()->pluck('employee_id')->all() : null;

            OvertimeEntry::where('payroll_id', $nomina->id)->update(['payroll_id' => null]);
            $nomina->employees()->each(fn (PayrollEmployee $f) => $f->delete());

            $nomina->update(['parameters' => $this->parametros($nomina->period_end)]);
            $this->calcular($nomina, $manuales, $empleados);

            return $nomina->refresh();
        });
    }

    /* ═════════════ Novedades manuales en el borrador (NR-NOM-002 E4) ═════════════ */

    public function agregarConcepto(Payroll $nomina, Employee $empleado, PayrollConcept $concepto, float $valor, ?float $cantidad = null): Payroll
    {
        $this->soloBorrador($nomina);

        if (! $concepto->is_active || $concepto->calculation === 'sistema' || $concepto->type === PayrollConcept::APORTE) {
            throw ValidationException::withMessages(['payroll_concept_id' => 'Ese concepto no se puede agregar a mano.']);
        }

        $fila = $nomina->employees()->where('employee_id', $empleado->id)->first();

        if (! $fila) {
            throw ValidationException::withMessages(['employee_id' => 'El empleado no está en esta nómina.']);
        }

        PayrollItem::create([
            'payroll_employee_id' => $fila->id,
            'payroll_concept_id'  => $concepto->id,
            'code'                => $concepto->code,
            'name'                => $concepto->name,
            'type'                => $concepto->type,
            'quantity'            => $cantidad,
            'amount'              => ReglasLaborales::redondear(abs($valor)),
            'is_manual'           => true,
        ]);

        return $this->recalcular($nomina);
    }

    public function quitarConcepto(PayrollItem $item): Payroll
    {
        $nomina = $item->payrollEmployee->payroll;
        $this->soloBorrador($nomina);

        if (! $item->is_manual) {
            throw ValidationException::withMessages(['item' => 'Solo se quitan los conceptos agregados a mano.']);
        }

        $item->delete();

        return $this->recalcular($nomina);
    }

    /** Valor sugerido de un concepto para un empleado (el formulario lo precarga). */
    public function valorSugerido(PayrollConcept $concepto, float $salario, int $dias = 30): float
    {
        return ReglasLaborales::redondear(match ($concepto->calculation) {
            'fijo'        => (float) $concepto->value,
            'pct_salario' => $salario * (float) $concepto->value / 100 * $dias / 30,
            'pct_smlmv'   => ReglasLaborales::smlmv() * (float) $concepto->value / 100 * $dias / 30,
            default       => 0,
        });
    }

    /* ═════════════ Estados (NR-NOM-003 E6 · NR-NOM-010 E4/E5) ═════════════ */

    public function aprobar(Payroll $nomina, User $quien): Payroll
    {
        $this->soloBorrador($nomina);

        if ($nomina->employees()->count() === 0) {
            throw ValidationException::withMessages(['nomina' => 'La nómina no tiene empleados liquidados.']);
        }

        DB::transaction(fn () => $nomina->update([
            'status' => Payroll::APROBADA, 'approved_by' => $quien->id, 'approved_at' => now(),
        ]));

        NominaAprobada::dispatch($nomina->refresh());

        return $nomina;
    }

    public function registrarPago(Payroll $nomina, User $quien): Payroll
    {
        if ($nomina->status !== Payroll::APROBADA) {
            throw ValidationException::withMessages(['nomina' => 'Solo se paga una nómina aprobada.']);
        }

        $nomina->update(['status' => Payroll::PAGADA, 'paid_by' => $quien->id, 'paid_at' => now()]);

        NominaPagada::dispatch($nomina->refresh());

        return $nomina;
    }

    public function eliminarBorrador(Payroll $nomina): void
    {
        DB::transaction(function () use ($nomina) {
            OvertimeEntry::where('payroll_id', $nomina->id)->update(['payroll_id' => null]);
            $nomina->delete(); // si no es borrador, el modelo lanza NominaInmutable
        });
    }

    /** NR-NOM-010 E4 · La corrección de una nómina aprobada. */
    public function crearAjuste(Payroll $original, User $quien, ?string $notas = null): Payroll
    {
        if ($original->esBorrador()) {
            throw ValidationException::withMessages(['nomina' => 'Un borrador se corrige directamente; no necesita ajuste.']);
        }

        return DB::transaction(function () use ($original, $quien, $notas) {
            $n = $original->adjustments()->count() + 1;

            $ajuste = Payroll::create([
                'tienda_id'          => $original->tienda_id,
                'number'             => 'AJU-' . $original->period_start->format('Ymd') . '-' . $n,
                'period_start'       => $original->period_start,
                'period_end'         => $original->period_end,
                'kind'               => 'ajuste',
                'adjusts_payroll_id' => $original->id,
                'parameters'         => $original->parameters,
                'created_by'         => $quien->id,
                'notes'              => $notas ?: 'Ajuste de ' . $original->number,
            ]);

            $this->calcular($ajuste, [], $original->employees()->pluck('employee_id')->all());

            return $ajuste->refresh();
        });
    }

    /* ═════════════ PILA (NR-NOM-004 E4) ═════════════ */

    /**
     * Archivo plano simplificado inspirado en la planilla tipo E (Resolución 2388
     * de 2016): registro 01 de encabezado y un registro 02 por cotizante, en
     * campos de ancho fijo. Sirve para conciliar con el operador; no reemplaza
     * el archivo oficial que genera el operador de información.
     */
    public function archivoPila(Payroll $nomina): string
    {
        if ($nomina->esBorrador()) {
            throw ValidationException::withMessages(['nomina' => 'La PILA se genera con la nómina aprobada.']);
        }

        $nomina->loadMissing('employees.employee', 'employees.contribution', 'tienda.empresa');
        $empresa = $nomina->tienda?->empresa;
        $nit = preg_replace('/\D/', '', (string) \App\Models\Setting::obtener('negocio.nit', '')) ?: '0';

        $c = fn ($v, int $n) => str_pad(mb_substr(Str::ascii((string) $v), 0, $n), $n);
        $m = fn ($v, int $n) => str_pad((string) (int) round((float) $v), $n, '0', STR_PAD_LEFT);

        $lineas = ['01' . $c('E', 1) . $c($nit, 16) . $c($empresa?->name ?? 'EMPRESA', 40) . $nomina->period_start->format('Y-m')
            . $m($nomina->employees->count(), 5) . $m($nomina->employees->sum(fn ($f) => $f->contribution?->ibc), 12)];

        foreach ($nomina->employees as $i => $f) {
            $e = $f->employee;
            $a = $f->contribution;
            $lineas[] = '02' . $m($i + 1, 5) . $c($e->document_type ?: 'CC', 2) . $c($e->document_number, 16)
                . $c($e->last_name, 30) . $c($e->first_name, 30) . $m(min(30, $f->days_worked), 2)
                . $m($a?->ibc, 9) . $m(($a?->health_employee ?? 0) + ($a?->health_employer ?? 0), 9)
                . $m(($a?->pension_employee ?? 0) + ($a?->pension_employer ?? 0), 9) . $m($a?->solidarity_fund, 9)
                . $m($a?->arl_class, 1) . $m($a?->arl, 9) . $m($a?->ccf, 9) . $m($a?->sena, 9) . $m($a?->icbf, 9);
        }

        return implode("\r\n", $lineas) . "\r\n";
    }

    /* ═════════════ Cálculo ═════════════ */

    /**
     * @param array<int, array<int, array<string, mixed>>> $manuales  por employee_id
     * @param array<int>|null $soloEmpleados  ajuste: los mismos de la original, sin automáticos
     */
    private function calcular(Payroll $nomina, array $manuales = [], ?array $soloEmpleados = null): void
    {
        $desde = $nomina->period_start;
        $hasta = $nomina->period_end;
        $esAjuste = $nomina->esAjuste();

        $contratos = EmploymentContract::query()
            ->where('tienda_id', $nomina->tienda_id)
            ->where('type', '!=', 'prestacion')
            ->whereDate('start_date', '<=', $hasta)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $desde))
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $desde))
            ->when($soloEmpleados !== null, fn ($q) => $q->whereIn('employee_id', $soloEmpleados))
            ->with(['employee' => fn ($q) => $q->withTrashed()->with('departments')])
            ->orderByDesc('start_date')
            ->get()
            ->unique('employee_id');

        foreach ($contratos as $contrato) {
            $empleado = $contrato->employee;

            if (! $empleado || ($empleado->termination_date && $empleado->termination_date->lt($desde))) {
                continue;
            }

            $this->calcularEmpleado($nomina, $empleado, $contrato, $manuales[$empleado->id] ?? [], $esAjuste);
        }

        $filas = $nomina->employees()->with('contribution')->get();

        $nomina->update([
            'employees_count'  => $filas->count(),
            'total_earned'     => $filas->sum('earned'),
            'total_deductions' => $filas->sum('deductions'),
            'total_net'        => $filas->sum('net'),
            'total_employer'   => $filas->sum(fn ($f) => (float) $f->contribution?->total_employer),
        ]);
    }

    private function calcularEmpleado(Payroll $nomina, Employee $empleado, EmploymentContract $contrato, array $manuales, bool $esAjuste): void
    {
        $smlmv   = ReglasLaborales::smlmv();
        $salario = (float) $contrato->base_salary;
        $diario  = $salario / 30;
        $aprendiz = $contrato->type === 'aprendizaje';

        $desde = $nomina->period_start->copy()->max($contrato->start_date);
        $fin   = collect([$nomina->period_end, $contrato->ended_at, $contrato->end_date, $empleado->termination_date])->filter()->min();
        $hasta = Carbon::parse($fin);

        $dias = ReglasLaborales::diasComerciales($desde, $hasta);

        if ($dias <= 0 && ! $esAjuste) {
            return;
        }

        $lineas = [];

        if (! $esAjuste) {
            [$vacaciones, $noRemunerados] = $this->diasDeNovedades($empleado, $desde, $hasta);
            $vacaciones    = min($vacaciones, $dias);
            $noRemunerados = min($noRemunerados, $dias - $vacaciones);

            $lineas[] = $this->linea('SALARIO', $diario * ($dias - $vacaciones), $dias - $vacaciones);

            if ($vacaciones > 0) {
                $lineas[] = $this->linea('VACACION', $diario * $vacaciones, $vacaciones);
            }

            if ($salario <= 2 * $smlmv) {
                $diasAux = $dias - $vacaciones - $noRemunerados;

                if ($diasAux > 0) {
                    $lineas[] = $this->linea('AUXTRANS', ReglasLaborales::auxilioTransporte() / 30 * $diasAux, $diasAux);
                }
            }

            $horas = OvertimeEntry::where('employee_id', $empleado->id)->where('status', OvertimeEntry::APROBADA)
                ->whereNull('payroll_id')
                ->whereDate('date', '>=', $nomina->period_start)->whereDate('date', '<=', $nomina->period_end)
                ->get();

            foreach ($horas->groupBy('type') as $tipo => $grupo) {
                $lineas[] = $this->linea('HEXTRA', (float) $grupo->sum('amount'), (float) $grupo->sum('hours'),
                    'Horas extra · ' . (ReglasLaborales::TIPOS_HORA[$tipo] ?? $tipo));
            }

            if ($horas->isNotEmpty()) {
                OvertimeEntry::whereKey($horas->pluck('id'))->update(['payroll_id' => $nomina->id]);
            }

            if ($noRemunerados > 0) {
                $lineas[] = $this->linea('AUSENCIA', $diario * $noRemunerados, $noRemunerados);
            }

            foreach ($this->conceptos()->where('is_system', false)->where('is_mandatory', true)->where('is_active', true) as $c) {
                if ($c->calculation !== 'manual' && $c->type !== PayrollConcept::APORTE) {
                    $lineas[] = $this->lineaConcepto($c, $this->valorSugerido($c, $salario, $dias));
                }
            }
        }

        foreach ($manuales as $m) {
            $lineas[] = $m + ['is_manual' => true];
        }

        $lineas = array_values(array_filter($lineas, fn ($l) => $l['amount'] > 0));

        // ── IBC ──
        $salariales = collect($lineas)->where('type', PayrollConcept::DEVENGADO)->where('is_salary', true)->sum('amount');
        $ausencias  = collect($lineas)->where('code', 'AUSENCIA')->sum('amount');
        $ibc        = max(0, $salariales - $ausencias);

        if (! $esAjuste && ! $aprendiz && $ibc > 0) {
            $ibc = max($ibc, $smlmv * max(0, $dias - ($noRemunerados ?? 0)) / 30);
        }

        $tope   = 25 * $smlmv;
        $topado = $ibc > $tope;
        $ibc    = ReglasLaborales::redondear(min($ibc, $tope));

        // ── Deducciones de ley ──
        $salud = $pension = $fsp = 0.0;

        if (! $aprendiz && $ibc > 0) {
            $salud   = ReglasLaborales::redondear($ibc * $this->tasa('SALUD', 4));
            $pension = ReglasLaborales::redondear($ibc * $this->tasa('PENSION', 4));
            $fsp     = ReglasLaborales::redondear($ibc * ReglasLaborales::fondoSolidaridad($ibc));

            $lineas[] = $this->linea('SALUD', $salud, null, null, false);
            $lineas[] = $this->linea('PENSION', $pension, null, null, false);

            if ($fsp > 0) {
                $lineas[] = $this->linea('FSP', $fsp, null, null, false);
            }
        }

        $devengado = collect($lineas)->where('type', PayrollConcept::DEVENGADO)->sum('amount');
        $deducido  = collect($lineas)->where('type', PayrollConcept::DEDUCCION)->sum('amount');

        $fila = PayrollEmployee::create([
            'payroll_id'             => $nomina->id,
            'employee_id'            => $empleado->id,
            'employment_contract_id' => $contrato->id,
            'department_id'          => $empleado->areaPrincipal()?->id,
            'base_salary'            => $salario,
            'days_worked'            => max(0, $dias),
            'absence_days'           => $noRemunerados ?? 0,
            'ibc'                    => $ibc,
            'earned'                 => $devengado,
            'deductions'             => $deducido,
            'net'                    => $devengado - $deducido,
            'verification_code'      => $this->codigo(),
        ]);

        foreach ($lineas as $l) {
            PayrollItem::create([
                'payroll_employee_id' => $fila->id,
                'payroll_concept_id'  => $l['payroll_concept_id'] ?? null,
                'code'                => $l['code'],
                'name'                => $l['name'],
                'type'                => $l['type'],
                'quantity'            => $l['quantity'] ?? null,
                'amount'              => $l['amount'],
                'is_manual'           => $l['is_manual'] ?? false,
            ]);
        }

        // ── Aportes patronales (NR-NOM-004 E1/E3/E5) ──
        $exonerado = ReglasLaborales::exonerado($salario);
        $clase     = (int) $contrato->arl_class ?: 1;

        $aportes = [
            'health_employer'  => $ibc * ($aprendiz ? 0.125 : ($exonerado ? 0 : 0.085)),
            'pension_employer' => $aprendiz ? 0 : $ibc * 0.12,
            'arl'              => $ibc * (ReglasLaborales::ARL[$clase] ?? ReglasLaborales::ARL[1]) / 100,
            'ccf'              => $aprendiz ? 0 : $ibc * $this->tasa('CAJA', 4),
            'icbf'             => ($aprendiz || $exonerado) ? 0 : $ibc * 0.03,
            'sena'             => ($aprendiz || $exonerado) ? 0 : $ibc * 0.02,
        ];
        $aportes = array_map([ReglasLaborales::class, 'redondear'], $aportes);

        PayrollContribution::create($aportes + [
            'payroll_employee_id' => $fila->id,
            'ibc'                 => $ibc,
            'ibc_capped'          => $topado,
            'health_employee'     => $salud,
            'pension_employee'    => $pension,
            'solidarity_fund'     => $fsp,
            'arl_class'           => $clase,
            'total_employer'      => array_sum($aportes),
        ]);
    }

    /** @return array{0: int, 1: int} [días de vacaciones, días no remunerados] dentro del rango */
    private function diasDeNovedades(Employee $empleado, Carbon $desde, Carbon $hasta): array
    {
        $vac = $noRem = 0;

        $novedades = LeaveRequest::where('employee_id', $empleado->id)->where('status', LeaveRequest::APROBADA)
            ->whereDate('start_date', '<=', $hasta)->whereDate('end_date', '>=', $desde)->get();

        foreach ($novedades as $n) {
            $dias = ReglasLaborales::diasComerciales($n->start_date->copy()->max($desde), $n->end_date->copy()->min($hasta));

            if ($n->type === 'vacaciones') {
                $vac += $dias;
            } elseif ($n->descuentaSalario()) {
                $noRem += $dias;
            }
        }

        return [$vac, $noRem];
    }

    /** @return array<string, mixed> */
    private function linea(string $code, float $valor, ?float $cantidad = null, ?string $nombre = null, ?bool $salarial = null): array
    {
        $c = $this->conceptos()->firstWhere('code', $code);

        return [
            'payroll_concept_id' => $c?->id,
            'code'               => $code,
            'name'               => $nombre ?? $c?->name ?? $code,
            'type'               => $c?->type ?? PayrollConcept::DEVENGADO,
            'is_salary'          => $salarial ?? (bool) ($c?->is_salary ?? false),
            'quantity'           => $cantidad,
            'amount'             => ReglasLaborales::redondear($valor),
        ];
    }

    private function lineaConcepto(PayrollConcept $c, float $valor): array
    {
        return [
            'payroll_concept_id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'type' => $c->type,
            'is_salary' => $c->is_salary, 'quantity' => null, 'amount' => ReglasLaborales::redondear($valor),
        ];
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    private function manuales(Payroll $nomina): array
    {
        $porEmpleado = [];

        foreach ($nomina->employees()->with('items.concept')->get() as $fila) {
            foreach ($fila->items->where('is_manual', true) as $i) {
                $porEmpleado[$fila->employee_id][] = [
                    'payroll_concept_id' => $i->payroll_concept_id, 'code' => $i->code, 'name' => $i->name,
                    'type' => $i->type, 'quantity' => $i->quantity, 'amount' => (float) $i->amount,
                    'is_salary' => (bool) ($i->concept?->is_salary ?? false),
                ];
            }
        }

        return $porEmpleado;
    }

    private function conceptos(): Collection
    {
        return $this->conceptos ??= PayrollConcept::all();
    }

    private function tasa(string $code, float $porDefecto): float
    {
        $c = $this->conceptos()->firstWhere('code', $code);

        return (($c && (float) $c->value > 0) ? (float) $c->value : $porDefecto) / 100;
    }

    private function parametros(Carbon $fecha): array
    {
        return [
            'smlmv'              => ReglasLaborales::smlmv(),
            'auxilio_transporte' => ReglasLaborales::auxilioTransporte(),
            'horas_mes'          => ReglasLaborales::horasMes($fecha),
            'recargo_dominical'  => ReglasLaborales::recargoDominical($fecha),
            'exoneracion_114'    => \App\Models\Setting::activo('nomina.exoneracion_114'),
        ];
    }

    private function validarPeriodo(Carbon $desde, Carbon $hasta): void
    {
        if ($hasta->lt($desde)) {
            throw ValidationException::withMessages(['period_end' => 'El fin del período es anterior al inicio.']);
        }

        if (! $desde->isSameMonth($hasta)) {
            throw ValidationException::withMessages(['period_end' => 'Una nómina liquida un mes o una quincena: el período debe quedar dentro del mismo mes.']);
        }
    }

    private function soloBorrador(Payroll $nomina): void
    {
        if (! $nomina->esBorrador()) {
            $nomina->intentoBloqueado('UPDATE', []);
            throw new NominaInmutable();
        }
    }

    private function codigo(): string
    {
        do {
            $c = Str::upper(Str::random(16));
        } while (PayrollEmployee::where('verification_code', $c)->exists());

        return $c;
    }
}
