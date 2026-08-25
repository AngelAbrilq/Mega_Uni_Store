<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Apertura, consulta y cierre (arqueo) de los turnos de caja.
 */
class CashSessionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:caja.ver',   only: ['index', 'show', 'cierreZ']),
            new Middleware('permission:caja.abrir', only: ['create', 'store']),
            new Middleware('permission:caja.cerrar', only: ['cerrar']),
        ];
    }

    public function index(Request $request)
    {
        $sessions = CashSession::query()
            ->with(['user:id,name', 'closer:id,name'])
            ->withCount('sales')
            ->withSum(['sales as ventas_total' => fn ($q) => $q->where('status', Sale::PAGADA)], 'total')
            ->orderByDesc('opened_at')
            ->paginate(12);

        return view('cash.index', [
            'sessions' => $sessions,
            'abierta'  => CashSession::abiertaDe($request->user()->id),
        ]);
    }

    public function create(Request $request)
    {
        if ($abierta = CashSession::abiertaDe($request->user()->id)) {
            return redirect()
                ->route('cash.show', $abierta)
                ->with('error', 'Ya tienes un turno abierto. Ciérralo antes de abrir otro.');
        }

        return view('cash.create');
    }

    public function store(Request $request)
    {
        if (CashSession::abiertaDe($request->user()->id)) {
            return back()->with('error', 'Ya tienes un turno de caja abierto.');
        }

        $datos = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ], [
            'opening_amount.required' => 'Indica con cuánta base inicia la caja.',
        ]);

        $turno = CashSession::create([
            'user_id'        => $request->user()->id,
            'opening_amount' => $datos['opening_amount'],
            'notes'          => $datos['notes'] ?? null,
            'status'         => CashSession::ABIERTA,
            'opened_at'      => now(),
        ]);

        return redirect()
            ->route('pos.index')
            ->with('success', 'Turno de caja abierto con base de $'
                            . number_format((float) $turno->opening_amount, 0, ',', '.') . '.');
    }

    public function show(CashSession $cash)
    {
        $cash->load(['user:id,name', 'closer:id,name']);

        $ventas = $cash->sales()
            ->with('customer:id,first_name,last_name')
            ->orderByDesc('sold_at')
            ->get();

        // Desglose por medio de pago, que es lo que se compara al cerrar.
        $porMedio = SalePayment::query()
            ->selectRaw('method_name, SUM(amount) as total, COUNT(*) as veces')
            ->whereHas('sale', fn ($q) => $q->where('cash_session_id', $cash->id)
                                            ->where('status', Sale::PAGADA))
            ->groupBy('method_name')
            ->orderByDesc('total')
            ->get();

        return view('cash.show', [
            'cash'     => $cash,
            'ventas'   => $ventas,
            'porMedio' => $porMedio,
            'esperado' => $cash->status === CashSession::ABIERTA
                ? $cash->calcularEsperado()
                : (float) $cash->expected_amount,
        ]);
    }

    /**
     * Cierre Z: el informe imprimible que firma el cajero al entregar
     * el turno. Formato tirilla, igual que el recibo de venta.
     */
    public function cierreZ(CashSession $cash)
    {
        $cash->load(['user:id,name', 'closer:id,name']);

        $ventas = $cash->sales()->where('status', Sale::PAGADA)->get();

        $porMedio = SalePayment::query()
            ->selectRaw('method_name, SUM(amount) as total, COUNT(*) as veces')
            ->whereHas('sale', fn ($q) => $q->where('cash_session_id', $cash->id)
                                            ->where('status', Sale::PAGADA))
            ->groupBy('method_name')
            ->orderByDesc('total')
            ->get();

        // Lo más vendido durante el turno, útil para la reposición.
        $top = \App\Models\SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('cash_session_id', $cash->id)
                                            ->where('status', Sale::PAGADA))
            ->selectRaw('name, SUM(quantity) as unidades, SUM(total) as total')
            ->groupBy('name')
            ->orderByDesc('unidades')
            ->take(8)
            ->get();

        return view('cash.z', [
            'cash'      => $cash,
            'ventas'    => $ventas,
            'porMedio'  => $porMedio,
            'top'       => $top,
            'anuladas'  => $cash->sales()->where('status', Sale::ANULADA)->get(),
            'esperado'  => $cash->status === CashSession::ABIERTA
                ? $cash->calcularEsperado()
                : (float) $cash->expected_amount,
        ]);
    }

    public function cerrar(Request $request, CashSession $cash)
    {
        if ($cash->status === CashSession::CERRADA) {
            return back()->with('error', 'Este turno ya estaba cerrado.');
        }

        $datos = $request->validate([
            'counted_amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ], [
            'counted_amount.required' => 'Escribe cuánto efectivo hay realmente en la caja.',
        ]);

        $esperado = $cash->calcularEsperado();
        $contado  = (float) $datos['counted_amount'];

        $cash->forceFill([
            'expected_amount' => $esperado,
            'counted_amount'  => $contado,
            'difference'      => round($contado - $esperado, 2),
            'status'          => CashSession::CERRADA,
            'closed_by'       => $request->user()->id,
            'closed_at'       => now(),
            'notes'           => trim(($cash->notes ? $cash->notes . "\n" : '') . ($datos['notes'] ?? '')) ?: null,
        ])->save();

        $dif = (float) $cash->difference;

        $mensaje = match (true) {
            abs($dif) < 0.01 => 'Turno cerrado. La caja cuadró exactamente.',
            $dif > 0         => 'Turno cerrado con un sobrante de $' . number_format($dif, 0, ',', '.') . '.',
            default          => 'Turno cerrado con un faltante de $' . number_format(abs($dif), 0, ',', '.') . '.',
        };

        return redirect()->route('cash.show', $cash)->with('success', $mensaje);
    }
}
