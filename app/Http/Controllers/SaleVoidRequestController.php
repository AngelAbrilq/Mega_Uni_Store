<?php

namespace App\Http\Controllers;

use App\Http\Requests\MotivoRequest;
use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Services\AnulacionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * VD-VTA-002 · Anular con aprobación (RN-43).
 *
 *   store    el Vendedor/Cajero pide la anulación (`ventas.solicitar_anulacion`)
 *   index    bandeja de solicitudes (`ventas.anular`)
 *   aprobar  anula la venta (`ventas.anular`, desde el local de la venta)
 *   rechazar con motivo
 */
class SaleVoidRequestController extends Controller implements HasMiddleware
{
    public function __construct(private AnulacionService $anulaciones)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ventas.solicitar_anulacion', only: ['store']),
            new Middleware('permission:ventas.anular', only: ['index', 'aprobar', 'rechazar']),
        ];
    }

    public function index(Request $request)
    {
        $estado = in_array($request->query('estado'), [SaleVoidRequest::APROBADA, SaleVoidRequest::RECHAZADA], true)
            ? $request->query('estado')
            : SaleVoidRequest::PENDIENTE;

        $solicitudes = SaleVoidRequest::query()
            ->where('status', $estado)
            ->whereHas('sale')
            ->with(['sale:id,tienda_id,number,total,sold_at,status,user_id', 'sale.user:id,name,last_name', 'requester:id,name,last_name', 'reviewer:id,name,last_name'])
            ->orderBy($estado === SaleVoidRequest::PENDIENTE ? 'created_at' : 'reviewed_at', $estado === SaleVoidRequest::PENDIENTE ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('void_requests.index', compact('solicitudes', 'estado'));
    }

    public function store(MotivoRequest $request, Sale $sale)
    {
        Gate::authorize('solicitarAnulacion', $sale);

        try {
            $this->anulaciones->solicitar($sale, $request->user(), $request->validated('motivo'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('sales.show', $sale)
            ->with('success', 'Solicitud enviada. Un supervisor debe aprobar la anulación de ' . $sale->number . '.');
    }

    public function aprobar(Request $request, SaleVoidRequest $solicitud)
    {
        Gate::authorize('anular', $solicitud->sale);

        try {
            $this->anulaciones->aprobar($solicitud, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Anulación de ' . $solicitud->sale->number . ' aprobada. La mercancía volvió al inventario.');
    }

    public function rechazar(MotivoRequest $request, SaleVoidRequest $solicitud)
    {
        Gate::authorize('anular', $solicitud->sale);

        try {
            $this->anulaciones->rechazar($solicitud, $request->user(), $request->validated('motivo'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Solicitud rechazada. La venta ' . $solicitud->sale->number . ' sigue vigente.');
    }
}
