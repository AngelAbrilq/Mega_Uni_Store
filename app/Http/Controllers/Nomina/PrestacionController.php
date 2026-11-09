<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SocialBenefit;
use App\Services\Nomina\PrestacionesService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** NR-NOM-007 · Prestaciones sociales: calcular, liquidación final y pago. */
class PrestacionController extends Controller implements HasMiddleware
{
    public function __construct(private PrestacionesService $prestaciones) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:nomina.ver', only: ['index']),
            new Middleware('permission:nomina.gestionar', only: ['store', 'final', 'destroy']),
            new Middleware('permission:nomina.aprobar', only: ['pagar']),
        ];
    }

    public function index(Request $request)
    {
        $tipo   = array_key_exists((string) $request->query('tipo'), SocialBenefit::TIPOS) ? $request->query('tipo') : null;
        $estado = in_array($request->query('estado'), [SocialBenefit::CALCULADA, SocialBenefit::PAGADA], true) ? $request->query('estado') : null;

        $base = SocialBenefit::query()->deTienda(null);

        return view('nomina.prestaciones.index', [
            'prestaciones' => (clone $base)->when($tipo, fn ($q) => $q->where('type', $tipo))->when($estado, fn ($q) => $q->where('status', $estado))
                ->with('employee:id,first_name,last_name,code,deleted_at')->orderByDesc('period_end')->orderBy('employee_id')->paginate(30)->withQueryString(),
            'tipo'      => $tipo,
            'estado'    => $estado,
            'porPagar'  => (float) (clone $base)->where('status', SocialBenefit::CALCULADA)->sum('amount'),
            'porTipo'   => (clone $base)->where('status', SocialBenefit::CALCULADA)->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type'),
            'empleados' => Employee::withTrashed()->deTienda(null)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code', 'deleted_at']),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'tipo'         => ['required', Rule::in(['cesantias', 'prima'])],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $desde = Carbon::parse($d['period_start']);
        $hasta = Carbon::parse($d['period_end']);
        $creadas = $d['tipo'] === 'prima' ? $this->prestaciones->prima($desde, $hasta) : $this->prestaciones->cesantias($desde, $hasta);

        return back()->with($creadas->isEmpty() ? 'error' : 'success', $creadas->isEmpty()
            ? 'No había nada nuevo por calcular en ese período (ya estaba calculado o no hay contratos).'
            : $creadas->count() . ' prestaciones calculadas por $' . number_format((float) $creadas->sum('amount'), 0, ',', '.') . '.');
    }

    /** E4 · Liquidación final de un empleado que se retira. */
    public function final(Request $request)
    {
        $d = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->whereIn('tienda_id', \App\Support\Contexto::tiendaIds() ?? [])],
            'fecha'       => ['required', 'date'],
        ]);

        $empleado = Employee::withTrashed()->findOrFail($d['employee_id']);
        $creadas  = $this->prestaciones->liquidacionFinal($empleado, Carbon::parse($d['fecha']));

        return back()->with('success', "Liquidación final de {$empleado->nombre_completo}: " . $creadas->count()
            . ' conceptos por $' . number_format((float) $creadas->sum('amount'), 0, ',', '.') . '.');
    }

    /** E5 */
    public function pagar(Request $request)
    {
        $d = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']], ['ids.required' => 'Selecciona al menos una prestación.']);

        $n = $this->prestaciones->pagar(SocialBenefit::deTienda(null)->whereKey($d['ids'])->get(), $request->user());

        return back()->with('success', "{$n} prestaciones pagadas.");
    }

    public function destroy(SocialBenefit $prestacion)
    {
        $this->prestaciones->eliminar($prestacion);

        return back()->with('success', 'Prestación eliminada.');
    }
}
