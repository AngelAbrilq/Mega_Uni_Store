<?php

namespace App\Http\Controllers\Nomina;

use App\Exceptions\NominaInmutable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nomina\LiquidarNominaRequest;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\LeaveRequest;
use App\Models\OvertimeEntry;
use App\Models\Payroll;
use App\Models\PayrollConcept;
use App\Models\PayrollEmployee;
use App\Models\PayrollItem;
use App\Models\Setting;
use App\Services\Nomina\DesprendibleService;
use App\Services\Nomina\NominaService;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * NR-NOM-003/004/008/010 · Nóminas del local activo.
 *
 * Toda acción que cambia una nómina pasa por NominaService; si la nómina ya
 * no es borrador el modelo lanza NominaInmutable y aquí se convierte en el
 * mensaje de la HU.
 */
class NominaController extends Controller implements HasMiddleware
{
    public function __construct(private NominaService $nomina, private DesprendibleService $desprendibles) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:nomina.ver', only: ['index', 'show', 'desprendible', 'desprendibles', 'pila']),
            new Middleware('permission:nomina.gestionar', only: ['store', 'recalcular', 'destroy', 'ajuste', 'agregarConcepto', 'quitarConcepto', 'enviar', 'parametros', 'guardarParametros']),
            new Middleware('permission:nomina.aprobar', only: ['aprobar', 'pagar']),
        ];
    }

    public function index(Request $request)
    {
        $estado = array_key_exists((string) $request->query('estado'), Payroll::ESTADOS) ? $request->query('estado') : null;

        $nominas = Payroll::query()->deTienda(null)
            ->when($estado, fn ($q) => $q->where('status', $estado))
            ->with('creator:id,name,last_name')
            ->orderByDesc('period_start')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        $mes = today()->subMonthNoOverflow();
        $ultima = Payroll::query()->deTienda(null)->where('kind', 'normal')->orderByDesc('period_end')->first();
        $sugerido = $ultima ? $ultima->period_end->copy()->addDay() : $mes->copy()->startOfMonth();

        return view('nomina.nominas.index', [
            'nominas'   => $nominas,
            'estado'    => $estado,
            'sugerido'  => ['desde' => $sugerido->copy()->startOfMonth()->max($sugerido), 'hasta' => $sugerido->copy()->endOfMonth()],
            'pendientes' => [
                'horas'      => OvertimeEntry::deTienda(null)->where('status', 'pendiente')->count(),
                'ausencias'  => LeaveRequest::deTienda(null)->where('status', 'pendiente')->count(),
                'sinContrato' => Employee::activos()->deTienda(null)->whereDoesntHave('contracts', fn ($q) => $q->activos())->count(),
                'vencidos'   => EmploymentContract::deTienda(null)->activos()->whereNotNull('end_date')->whereDate('end_date', '<', today())->count(),
            ],
            'resumen' => [
                'ultimoNeto'  => (float) Payroll::deTienda(null)->whereIn('status', [Payroll::APROBADA, Payroll::PAGADA])->orderByDesc('period_start')->value('total_net'),
                'anio'        => (float) Payroll::deTienda(null)->whereIn('status', [Payroll::APROBADA, Payroll::PAGADA])->whereYear('period_start', today()->year)->selectRaw('SUM(total_earned + total_employer) as c')->value('c'),
                'borradores'  => Payroll::deTienda(null)->where('status', Payroll::BORRADOR)->count(),
            ],
        ]);
    }

    public function store(LiquidarNominaRequest $request)
    {
        $n = $this->nomina->liquidar(Carbon::parse($request->input('period_start')), Carbon::parse($request->input('period_end')),
            $request->user(), $request->input('notes'));

        return redirect()->route('payrolls.show', $n)->with('success', "Nómina {$n->number} calculada en borrador: revísala antes de aprobar.");
    }

    public function show(Payroll $payroll)
    {
        $payroll->load(['employees.employee', 'employees.items', 'employees.contribution', 'employees.department',
                        'creator', 'approver', 'payer', 'adjusts', 'adjustments']);

        $conceptosManuales = PayrollConcept::activos()->where('is_system', false)
            ->whereIn('type', [PayrollConcept::DEVENGADO, PayrollConcept::DEDUCCION])->orderBy('name')->get();

        return view('nomina.nominas.show', [
            'nomina'     => $payroll,
            'conceptos'  => $conceptosManuales,
            'aportes'    => [
                'salud'    => $payroll->employees->sum(fn ($f) => (float) $f->contribution?->health_employer),
                'pension'  => $payroll->employees->sum(fn ($f) => (float) $f->contribution?->pension_employer),
                'arl'      => $payroll->employees->sum(fn ($f) => (float) $f->contribution?->arl),
                'ccf'      => $payroll->employees->sum(fn ($f) => (float) $f->contribution?->ccf),
                'icbf'     => $payroll->employees->sum(fn ($f) => (float) $f->contribution?->icbf),
                'sena'     => $payroll->employees->sum(fn ($f) => (float) $f->contribution?->sena),
                'topados'  => $payroll->employees->filter(fn ($f) => $f->contribution?->ibc_capped)->count(),
            ],
        ]);
    }

    public function recalcular(Payroll $payroll)
    {
        return $this->intentar(function () use ($payroll) {
            $this->nomina->recalcular($payroll);

            return back()->with('success', 'Nómina recalculada con las novedades y contratos de hoy.');
        });
    }

    public function aprobar(Request $request, Payroll $payroll)
    {
        return $this->intentar(function () use ($request, $payroll) {
            $this->nomina->aprobar($payroll, $request->user());

            return back()->with('success', "Nómina {$payroll->number} aprobada: quedó bloqueada y lista para pagar.");
        });
    }

    public function pagar(Request $request, Payroll $payroll)
    {
        $this->nomina->registrarPago($payroll, $request->user());

        return back()->with('success', 'Pago registrado.');
    }

    public function destroy(Payroll $payroll)
    {
        return $this->intentar(function () use ($payroll) {
            $this->nomina->eliminarBorrador($payroll);

            return redirect()->route('payrolls.index')->with('success', "Borrador {$payroll->number} eliminado.");
        });
    }

    public function ajuste(Request $request, Payroll $payroll)
    {
        $ajuste = $this->nomina->crearAjuste($payroll, $request->user(), $request->input('notes'));

        return redirect()->route('payrolls.show', $ajuste)->with('success', "Nómina de ajuste {$ajuste->number} creada: agrega los conceptos a corregir.");
    }

    /** NR-NOM-002 E4 · concepto no obligatorio con monto variable por empleado. */
    public function agregarConcepto(Request $request, Payroll $payroll)
    {
        $datos = $request->validate([
            'employee_id'        => ['required', Rule::exists('payroll_employees', 'employee_id')->where('payroll_id', $payroll->id)],
            'payroll_concept_id' => ['required', 'integer'],
            'amount'             => ['required', 'numeric', 'min:1', 'max:999999999'],
            'quantity'           => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ], ['employee_id.exists' => 'El empleado no está en esta nómina.'], ['amount' => 'valor', 'payroll_concept_id' => 'concepto']);

        return $this->intentar(function () use ($payroll, $datos) {
            $this->nomina->agregarConcepto($payroll, Employee::withTrashed()->findOrFail($datos['employee_id']),
                PayrollConcept::findOrFail($datos['payroll_concept_id']), (float) $datos['amount'], isset($datos['quantity']) ? (float) $datos['quantity'] : null);

            return back()->with('success', 'Concepto agregado y nómina recalculada.');
        });
    }

    public function quitarConcepto(Payroll $payroll, PayrollItem $item)
    {
        abort_unless($item->payrollEmployee?->payroll_id === $payroll->id, 404);

        return $this->intentar(function () use ($item) {
            $this->nomina->quitarConcepto($item);

            return back()->with('success', 'Concepto quitado.');
        });
    }

    /* ─────────── PILA y desprendibles ─────────── */

    public function pila(Payroll $payroll)
    {
        $contenido = $this->nomina->archivoPila($payroll);

        return response($contenido, 200, [
            'Content-Type'        => 'text/plain; charset=US-ASCII',
            'Content-Disposition' => 'attachment; filename="PILA_' . $payroll->periodoCorto() . '.txt"',
        ]);
    }

    public function desprendible(Payroll $payroll, PayrollEmployee $fila)
    {
        abort_unless($fila->payroll_id === $payroll->id, 404);
        $fila->setRelation('payroll', $payroll);

        return response($this->desprendibles->pdf($fila), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->desprendibles->nombreArchivo($fila) . '"',
        ]);
    }

    public function desprendibles(Payroll $payroll)
    {
        $ruta = $this->desprendibles->zip($payroll);

        return response()->download($ruta, 'desprendibles_' . $payroll->periodoCorto() . '.zip')->deleteFileAfterSend();
    }

    public function enviar(Payroll $payroll)
    {
        $r = $this->desprendibles->enviar($payroll);
        $msg = "Desprendibles enviados: {$r['enviados']}.";

        if ($r['sin_correo']) {
            $msg .= ' Sin correo: ' . implode(', ', $r['sin_correo']) . '.';
        }

        return back()->with($r['enviados'] ? 'success' : 'error', $msg);
    }

    /* ─────────── Parámetros legales (RN-45) ─────────── */

    public function parametros()
    {
        return view('nomina.parametros', [
            'ajustes' => Setting::todos(),
            'hoy'     => ['horas' => ReglasLaborales::horasMes(), 'dominical' => ReglasLaborales::recargoDominical()],
        ]);
    }

    public function guardarParametros(Request $request)
    {
        $d = $request->validate([
            'smlmv'              => ['required', 'numeric', 'min:1'],
            'auxilio_transporte' => ['required', 'numeric', 'min:0'],
            'horas_mes'          => ['nullable', 'integer', 'between:150,240'],
            'recargo_dominical'  => ['nullable', 'numeric', 'between:0,200'],
            'ratio_alerta'       => ['required', 'numeric', 'between:1,100'],
        ], [], ['smlmv' => 'SMLMV', 'horas_mes' => 'horas al mes']);

        Setting::guardar([
            'nomina.smlmv'              => $d['smlmv'],
            'nomina.auxilio_transporte' => $d['auxilio_transporte'],
            'nomina.horas_mes'          => $d['horas_mes'] ?? 'auto',
            'nomina.recargo_dominical'  => $d['recargo_dominical'] ?? 'auto',
            'nomina.exoneracion_114'    => $request->boolean('exoneracion_114') ? '1' : '0',
            'nomina.ratio_alerta'       => $d['ratio_alerta'],
        ]);

        return back()->with('success', 'Parámetros de nómina guardados. Aplican a las próximas liquidaciones.');
    }

    private function intentar(callable $fn)
    {
        try {
            return $fn();
        } catch (NominaInmutable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
