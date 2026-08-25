<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller implements HasMiddleware
{
    public function __construct(private SaleService $ventas) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ventas.ver',    only: ['index', 'show', 'recibo']),
            new Middleware('permission:ventas.anular', only: ['anular']),
        ];
    }

    public function index(Request $request)
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = ($request->date('hasta') ?? now())->endOfDay();

        $consulta = Sale::query()
            ->with(['customer:id,first_name,last_name', 'user:id,name'])
            ->withCount('items')
            ->search($request->query('q'))
            ->entre($desde, $hasta);

        if ($estado = $request->query('estado')) {
            $consulta->where('status', $estado);
        }

        if ($vendedor = $request->query('vendedor')) {
            $consulta->where('user_id', $vendedor);
        }

        $sales = $consulta->orderByDesc('sold_at')->paginate(15)->withQueryString();

        // Cifras del periodo consultado, no solo de la página visible.
        $base = Sale::query()->pagadas()->entre($desde, $hasta);

        return view('sales.index', [
            'sales'      => $sales,
            'vendedores' => User::deLaEmpresa()->orderBy('name')->get(['id', 'name']),
            'resumen'    => [
                'ventas'    => (clone $base)->count(),
                'total'     => (float) (clone $base)->sum('total'),
                'utilidad'  => (float) (clone $base)->sum('profit_total'),
                'anuladas'  => Sale::query()->where('status', Sale::ANULADA)->entre($desde, $hasta)->count(),
                'ticket'    => (float) ((clone $base)->avg('total') ?? 0),
            ],
            'filtros' => [
                'q'        => (string) $request->query('q', ''),
                'desde'    => $desde->format('Y-m-d'),
                'hasta'    => $hasta->format('Y-m-d'),
                'estado'   => (string) $request->query('estado', ''),
                'vendedor' => (string) $request->query('vendedor', ''),
            ],
        ]);
    }

    public function show(Sale $sale)
    {
        $sale->load(['items.product:id,sku', 'payments.paymentMethod:id,name',
                     'customer', 'user:id,name', 'voider:id,name', 'cashSession',
                     'returns:id,sale_id,number,total,reason,returned_at']);

        return view('sales.show', compact('sale'));
    }

    /** Recibo listo para imprimir, en formato de tirilla. */
    public function recibo(Sale $sale)
    {
        $sale->load(['items', 'payments', 'customer', 'user:id,name']);

        return view('sales.recibo', compact('sale'));
    }

    public function anular(Request $request, Sale $sale)
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'motivo.required' => 'Explica por qué se anula la venta.',
            'motivo.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        try {
            $this->ventas->anular($sale, $request->user()->id, $datos['motivo']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', 'Venta ' . $sale->number . ' anulada. La mercancía volvió al inventario.');
    }
}
