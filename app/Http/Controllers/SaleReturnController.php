<?php

namespace App\Http\Controllers;

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
        ];
    }

    public function index(Request $request)
    {
        $returns = SaleReturn::query()
            ->with(['sale:id,number', 'user:id,name'])
            ->withCount('items')
            ->search($request->query('q'))
            ->latest('returned_at')
            ->paginate(15)
            ->withQueryString();

        $mes = SaleReturn::whereBetween('returned_at', [now()->startOfMonth(), now()])->get();

        return view('returns.index', [
            'returns' => $returns,
            'q'       => (string) $request->query('q', ''),
            'resumen' => [
                'mes'      => $mes->count(),
                'valor'    => (float) $mes->sum('total'),
                'total'    => SaleReturn::count(),
                'unidades' => (float) SaleReturn::query()
                                ->join('sale_return_items', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                                ->whereBetween('sale_returns.returned_at', [now()->startOfMonth(), now()])
                                ->sum('sale_return_items.quantity'),
            ],
        ]);
    }

    /** Formulario: qué renglones de la venta se devuelven y cuántos. */
    public function create(Sale $sale)
    {
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

    public function store(Request $request, Sale $sale)
    {
        $datos = $request->validate([
            'motivo'               => ['required', 'string', 'min:5', 'max:255'],
            'restock'              => ['nullable', 'boolean'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.quantity'     => ['nullable', 'numeric', 'min:0'],
        ], [
            'motivo.required' => 'Explica por qué se devuelve la mercancía.',
            'motivo.min'      => 'El motivo debe tener al menos 5 caracteres.',
            'items.required'  => 'Indica qué productos se devuelven.',
        ]);

        try {
            $devolucion = $this->devoluciones->registrar(
                venta: $sale,
                lineas: $datos['items'],
                userId: $request->user()->id,
                motivo: $datos['motivo'],
                reingresar: $request->boolean('restock'),
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('returns.show', $devolucion)
            ->with('success', 'Devolución ' . $devolucion->number . ' registrada por $'
                            . number_format((float) $devolucion->total, 0, ',', '.') . '.');
    }

    public function show(SaleReturn $return)
    {
        $return->load(['items.product:id,name,sku,stock', 'sale.customer', 'user:id,name']);

        return view('returns.show', ['devolucion' => $return]);
    }
}
