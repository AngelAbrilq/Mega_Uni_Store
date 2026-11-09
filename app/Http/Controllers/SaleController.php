<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Support\CajonDelLocal;
use App\Services\ReciboEscPos;
use App\Http\Requests\AnularDocumentoRequest;
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
            new Middleware('permission:ventas.ver',    only: ['index', 'show', 'recibo', 'reciboEscPos']),
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
                     'returns:id,sale_id,number,total,reason,returned_at,status',
                     'voidRequests.requester:id,name,last_name', 'voidRequests.reviewer:id,name,last_name']);

        // RN-28 · Con agente de caja y papel térmico, el recibo sale por el agente.
        $termica = CajonDelLocal::configuracion()['agente'] && ReciboEscPos::aplica()
            ? CajonDelLocal::configuracion()['agente'] + ['recibo' => route('sales.recibo.escpos', $sale)]
            : null;

        return view('sales.show', compact('sale', 'termica'));
    }

    /**
     * RN-28 · Recibo en bytes ESC/POS (base64) para que el POS se lo pase al
     * agente de caja. El servidor no imprime: solo arma los bytes.
     */
    public function reciboEscPos(Sale $sale, ReciboEscPos $recibo): JsonResponse
    {
        if (! ReciboEscPos::aplica()) {
            return response()->json(['error' => 'Este negocio imprime en A4: usa el recibo del navegador.'], 422);
        }

        return response()->json(['datos' => base64_encode($recibo->generar($sale))]);
    }

    /** Recibo listo para imprimir, en formato de tirilla. */
    public function recibo(Sale $sale)
    {
        $sale->load(['items', 'payments', 'customer', 'user:id,name']);

        return view('sales.recibo', compact('sale'));
    }

    public function anular(AnularDocumentoRequest $request, Sale $sale)
    {
        $datos = $request->validated();

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
