<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Http\Requests\Compras\GuardarCompraRequest;
use App\Http\Requests\AnularDocumentoRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller implements HasMiddleware
{
    public function __construct(private PurchaseService $compras) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:compras.ver',      only: ['index', 'show']),
            new Middleware('permission:compras.crear',    only: ['create', 'store']),
            new Middleware('permission:compras.editar',   only: ['edit', 'update']),
            new Middleware('permission:compras.eliminar', only: ['anular']),
            new Middleware('permission:compras.recibir',  only: ['recibir']),
        ];
    }

    public function index(Request $request)
    {
        $consulta = Purchase::query()
            ->with(['supplier:id,name', 'user:id,name'])
            ->withCount('items')
            ->search($request->query('q'));

        if ($estado = $request->query('estado')) {
            $consulta->where('status', $estado);
        }

        if ($proveedor = $request->query('proveedor')) {
            $consulta->where('supplier_id', $proveedor);
        }

        $purchases = $consulta->orderByDesc('ordered_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('purchases.index', [
            'purchases'  => $purchases,
            'proveedores'=> Supplier::orderBy('name')->get(['id', 'name']),
            'resumen'    => [
                'borradores' => Purchase::where('status', Purchase::BORRADOR)->count(),
                'recibidas'  => Purchase::where('status', Purchase::RECIBIDA)->count(),
                'pendiente'  => (float) Purchase::where('status', Purchase::BORRADOR)->sum('total'),
                'mes'        => (float) Purchase::where('status', Purchase::RECIBIDA)
                                    ->whereBetween('received_at', [now()->startOfMonth(), now()])
                                    ->sum('total'),
            ],
            'filtros' => [
                'q'         => (string) $request->query('q', ''),
                'estado'    => (string) $request->query('estado', ''),
                'proveedor' => (string) $request->query('proveedor', ''),
            ],
        ]);
    }

    public function create()
    {
        return view('purchases.create', $this->listas());
    }

    public function store(GuardarCompraRequest $request)
    {
        $datos = $request->validated();

        try {
            $compra = $this->compras->registrar(
                supplierId: $datos['supplier_id'],
                lineas: $datos['items'],
                userId: $request->user()->id,
                factura: $datos['invoice_number'] ?? null,
                fecha: $datos['ordered_at'] ?? null,
                notas: $datos['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('purchases.show', $compra)
            ->with('success', 'Compra ' . $compra->number . ' registrada. Dale entrada cuando llegue la mercancía.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['items.product:id,name,sku', 'supplier', 'user:id,name', 'receiver:id,name']);

        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        Gate::authorize('update', $purchase); // H-04

        if (! $purchase->editable) {
            return redirect()
                ->route('purchases.show', $purchase)
                ->with('error', 'Una compra ya recibida no se puede modificar.');
        }

        $purchase->load('items');

        return view('purchases.edit', array_merge($this->listas(), ['purchase' => $purchase]));
    }

    public function update(GuardarCompraRequest $request, Purchase $purchase)
    {
        $datos = $request->validated();

        try {
            $this->compras->actualizar(
                $purchase,
                $datos['supplier_id'],
                $datos['items'],
                $datos['invoice_number'] ?? null,
                $datos['ordered_at'] ?? null,
                $datos['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'Compra ' . $purchase->number . ' actualizada.');
    }

    /** Da entrada a la mercancía: aquí es donde sube el inventario. */
    public function recibir(Request $request, Purchase $purchase)
    {
        Gate::authorize('recibir', $purchase); // H-04 · H-F12

        try {
            $this->compras->recibir($purchase, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'Mercancía recibida. El inventario y los costos quedaron actualizados.');
    }

    public function anular(AnularDocumentoRequest $request, Purchase $purchase)
    {
        $datos = $request->validated();

        try {
            $this->compras->anular($purchase, $request->user()->id, $datos['motivo']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'Compra ' . $purchase->number . ' anulada.');
    }

    /* ───────────────────────── Apoyo ───────────────────────── */

    /** @return array<string, mixed> */
    private function listas(): array
    {
        return [
            'proveedores' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'productos'   => Product::orderBy('name')
                ->with('suppliers:id')
                ->get(['id', 'name', 'sku', 'cost'])
                ->map(fn (Product $p) => [
                    'id'     => $p->id,
                    'nombre' => $p->name,
                    'sku'    => $p->sku,
                    'costo'  => (float) $p->cost,
                    'stock'  => (float) $p->stock,
                    // TBL-TPL-001: lo que cobra cada proveedor por este producto.
                    'precios' => $p->suppliers
                        ->filter(fn ($s) => $s->pivot->supplier_price !== null)
                        ->mapWithKeys(fn ($s) => [$s->id => (float) $s->pivot->supplier_price])
                        ->all(),
                ])
                ->values(),
        ];
    }
}
