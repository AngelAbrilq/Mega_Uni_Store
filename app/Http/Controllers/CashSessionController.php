<?php

namespace App\Http\Controllers;

use App\Http\Requests\Caja\CerrarCajaRequest;
use App\Http\Requests\Caja\AbrirCajaRequest;
use App\Http\Requests\Caja\RegistrarMovimientoCajaRequest;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            new Middleware('permission:caja.cerrar|caja.cerrar_ajena', only: ['cerrar']), // H-04: CashSessionPolicy decide cuál aplica
        ];
    }

    public function index(Request $request)
    {
        $sessions = CashSession::query()
            ->with(['user:id,name', 'closer:id,name', 'register:id,name,code'])
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

        // AT-CAJ-001: las cajas del local, marcando las que ya están ocupadas.
        $cajas = CashRegister::deTienda(null)
            ->activas()
            ->with(['sessions' => fn ($q) => $q->abierta()->with('user:id,name,last_name')])
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        return view('cash.create', compact('cajas'));
    }

    public function store(AbrirCajaRequest $request)
    {
        if (CashSession::abiertaDe($request->user()->id)) {
            return back()->with('error', 'Ya tienes un turno de caja abierto.');
        }

        $datos = $request->validated();

        $turno = DB::transaction(function () use ($request, $datos) {
            $cajaId = $datos['cash_register_id'] ?? null;

            if ($cajaId) {
                // RN-31: una caja física, un turno abierto. El bloqueo evita
                // que dos cajeros la tomen con el mismo clic.
                $caja = CashRegister::whereKey($cajaId)->lockForUpdate()->firstOrFail();

                if ($ocupada = $caja->turnoAbierto()) {
                    throw ValidationException::withMessages([
                        'cash_register_id' => 'La caja «' . $caja->etiqueta . '» ya tiene un turno abierto de '
                            . ($ocupada->user?->nombre_completo ?? 'otra persona') . '.',
                    ]);
                }
            }

            return CashSession::create([
                'cash_register_id' => $cajaId,
                'user_id'          => $request->user()->id,
                'opening_amount'   => $datos['opening_amount'],
                'notes'            => $datos['notes'] ?? null,
                'status'           => CashSession::ABIERTA,
                'opened_at'        => now(),
            ]);
        });

        return redirect()
            ->route('pos.index')
            ->with('success', 'Turno de caja abierto con base de $'
                            . number_format((float) $turno->opening_amount, 0, ',', '.') . '.');
    }

    public function show(CashSession $cash)
    {
        $cash->load(['user:id,name', 'closer:id,name', 'register:id,name,code', 'movements.user:id,name,last_name']);

        $ventas = $cash->sales()
            ->with('customer:id,first_name,last_name')
            ->orderByDesc('sold_at')
            ->get();

        // Desglose por medio de pago, que es lo que se compara al cerrar.
        $porMedio = $this->porMedio($cash);

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
        $cash->load(['user:id,name', 'closer:id,name', 'register:id,name,code', 'movements']);

        $ventas = $cash->sales()->where('status', Sale::PAGADA)->get();

        $porMedio = $this->porMedio($cash);

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

    public function cerrar(CerrarCajaRequest $request, CashSession $cash)
    {
        if ($cash->status === CashSession::CERRADA) {
            return back()->with('error', 'Este turno ya estaba cerrado.');
        }

        $datos = $request->validated();

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

    /**
     * Ingreso o retiro de efectivo durante el turno (AT-CAJ-001).
     *
     * Un retiro no puede dejar el cajón en negativo: no se saca plata que
     * el sistema dice que no hay.
     */
    public function movimiento(RegistrarMovimientoCajaRequest $request, CashSession $cash)
    {
        $datos = $request->validated();

        DB::transaction(function () use ($request, $cash, $datos) {
            $turno = CashSession::whereKey($cash->getKey())->lockForUpdate()->firstOrFail();

            if ($turno->status !== CashSession::ABIERTA) {
                throw ValidationException::withMessages(['amount' => 'Este turno ya está cerrado.']);
            }

            $monto = round((float) $datos['amount'], 2);

            if ($datos['type'] === CashMovement::RETIRO && $monto > $turno->calcularEsperado() + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'No puedes retirar más del efectivo que hay en caja ($'
                        . number_format($turno->calcularEsperado(), 0, ',', '.') . ').',
                ]);
            }

            $turno->movements()->create([
                'user_id' => $request->user()->id,
                'type'    => $datos['type'],
                'amount'  => $monto,
                'reason'  => trim($datos['reason']),
            ]);
        });

        return redirect()->route('cash.show', $cash)->with('success',
            ($datos['type'] === CashMovement::RETIRO ? 'Retiro' : 'Ingreso')
            . ' de $' . number_format((float) $datos['amount'], 0, ',', '.') . ' registrado.');
    }

    /**
     * Lo cobrado en el turno, por medio de pago, con el cambio ya restado
     * del efectivo.
     *
     * ── Por qué se resta aquí también ──
     *
     * Este desglose es, literalmente, contra lo que el cajero compara el
     * cajón: la pantalla lo dice —«lo que hay que comparar al arquear»—.
     * `sale_payments.amount` guarda lo que el cliente entregó, así que sin
     * restar el vuelto esta tabla decía «Efectivo $20.000» al lado de un
     * «efectivo esperado $109.282» calculado bien. Dos números que no
     * cuadran entre sí, en la misma pantalla, en el momento del día en que
     * más importa que cuadren.
     *
     * El vuelto sale siempre del cajón y siempre en efectivo, así que se
     * descuenta entero del renglón de efectivo.
     */
    private function porMedio(CashSession $cash)
    {
        $cambio = (float) Sale::query()
            ->where('cash_session_id', $cash->id)
            ->where('status', Sale::PAGADA)
            ->sum('change_amount');

        return SalePayment::query()
            ->vigentes() // RN-32
            ->selectRaw('method_name, SUM(amount) as total, COUNT(*) as veces')
            ->whereHas('sale', fn ($q) => $q->where('cash_session_id', $cash->id)
                                            ->where('status', Sale::PAGADA))
            ->groupBy('method_name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($fila) use (&$cambio) {
                if ($cambio > 0 && str_contains(mb_strtolower((string) $fila->method_name), 'efectivo')) {
                    $fila->total = max(0, (float) $fila->total - $cambio);
                    $cambio      = 0;
                }

                return $fila;
            });
    }
}
