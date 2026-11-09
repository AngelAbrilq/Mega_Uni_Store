<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Http\Requests\Ventas\RechazarDevolucionRequest;
use App\Http\Requests\Ventas\RegistrarDevolucionRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

class SaleReturnController extends Controller implements HasMiddleware
{
    public function __construct(private ReturnService $devoluciones) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ventas.ver',      only: ['index', 'show']),
            new Middleware('permission:ventas.devolver', only: ['create', 'store']),
            new Middleware('permission:ventas.aprobar_devolucion', only: ['aprobar', 'rechazar']),
        ];
    }

    public function index(Request $request)
    {
        $estado = (string) $request->query('estado', '');

        $returns = SaleReturn::query()
            ->with(['sale:id,number', 'user:id,name'])
            ->withCount('items')
            ->search($request->query('q'))
            ->when(in_array($estado, [SaleReturn::PENDIENTE, SaleReturn::APROBADA, SaleReturn::RECHAZADA], true),
                fn ($c) => $c->where('status', $estado))
            ->latest('returned_at')
            ->paginate(15)
            ->withQueryString();

        // Solo lo aprobado es dinero y mercancía que de verdad volvió.
        $mes = SaleReturn::aprobadas()->whereBetween('returned_at', [now()->startOfMonth(), now()])->get();

        return view('returns.index', [
            'returns' => $returns,
            'q'       => (string) $request->query('q', ''),
            'estado'  => $estado,
            'resumen' => [
                'mes'        => $mes->count(),
                'valor'      => (float) $mes->sum('total'),
                'total'      => SaleReturn::aprobadas()->count(),
                'pendientes' => SaleReturn::pendientes()->count(),
                'unidades'   => (float) SaleReturn::query()
                                ->join('sale_return_items', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                                ->where('sale_returns.status', SaleReturn::APROBADA)
                                ->whereBetween('sale_returns.returned_at', [now()->startOfMonth(), now()])
                                ->sum('sale_return_items.quantity'),
            ],
        ]);
    }

    /** Formulario: qué renglones de la venta se devuelven y cuántos. */
    public function create(Sale $sale)
    {
        Gate::authorize('devolver', $sale); // H-04

        if ($sale->status === Sale::ANULADA) {
            return redirect()
                ->route('sales.show', $sale)
                ->with('error', 'Esta venta está anulada: no admite devoluciones.');
        }

        $sale->load('items.product:id,name,sku', 'customer');

        $pendientes = $sale->items->filter(fn ($i) => $i->devolvible > 0);

        if ($pendientes->isEmpty()) {
            return redirect()
                ->route('sales.show', $sale)
                ->with('error', 'Todos los productos de esta venta ya fueron devueltos.');
        }

        return view('returns.create', compact('sale', 'pendientes'));
    }

    public function store(RegistrarDevolucionRequest $request, Sale $sale)
    {
        $datos = $request->validated();

        try {
            $devolucion = $this->devoluciones->registrar(
                venta: $sale,
                lineas: $datos['items'],
                userId: $request->user()->id,
                motivo: $datos['motivo'],
                reingresar: $request->boolean('restock'),
                // RN-30: quien puede aprobar la aplica de una vez; los demás la dejan pendiente.
                aprobar: $request->user()->can('ventas.aprobar_devolucion'),
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $monto = '$' . number_format((float) $devolucion->total, 0, ',', '.');

        return redirect()
            ->route('returns.show', $devolucion)
            ->with('success', $devolucion->pendiente
                ? 'Devolución ' . $devolucion->number . ' por ' . $monto . ' registrada. Queda pendiente hasta que un supervisor la apruebe.'
                : 'Devolución ' . $devolucion->number . ' registrada por ' . $monto . '.');
    }

    /** RN-30 · La mercancía vuelve al inventario. */
    public function aprobar(Request $request, SaleReturn $return)
    {
        Gate::authorize('revisar', $return);

        try {
            $this->devoluciones->aprobar($return, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('returns.show', $return)
            ->with('success', 'Devolución ' . $return->number . ' aprobada.');
    }

    /** RN-30 · Se libera la cantidad reservada; el inventario no se tocó. */
    public function rechazar(RechazarDevolucionRequest $request, SaleReturn $return)
    {
        try {
            $this->devoluciones->rechazar($return, $request->user()->id, $request->validated('motivo_rechazo'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('returns.show', $return)
            ->with('success', 'Devolución ' . $return->number . ' rechazada.');
    }

    public function show(SaleReturn $return)
    {
        $return->load(['items.product:id,name,sku', 'sale.customer', 'user:id,name', 'reviewer:id,name']);

        return view('returns.show', ['devolucion' => $return]);
    }
}
