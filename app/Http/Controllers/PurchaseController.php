<?php

namespace App\Http\Controllers;

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

    public function store(Request $request)
    {
        $datos = $this->validar($request);

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
        $purchase->load(['items.product:id,name,sku,stock', 'supplier', 'user:id,name', 'receiver:id,name']);

        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        if (! $purchase->editable) {
            return redirect()
                ->route('purchases.show', $purchase)
                ->with('error', 'Una compra ya recibida no se puede modificar.');
        }

        $purchase->load('items');

        return view('purchases.edit', array_merge($this->listas(), ['purchase' => $purchase]));
    }

    public function update(Request $request, Purchase $purchase)
    {
        $datos = $this->validar($request);

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
        try {
            $this->compras->recibir($purchase, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'Mercancía recibida. El inventario y los costos quedaron actualizados.');
    }

    public function anular(Request $request, Purchase $purchase)
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'motivo.required' => 'Explica por qué se anula la compra.',
        ]);

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
    private function validar(Request $request): array
    {
        return $request->validate([
            'supplier_id'         => ['required', 'exists:suppliers,id'],
            'invoice_number'      => ['nullable', 'string', 'max:50'],
            'ordered_at'          => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_cost'   => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.update_cost' => ['nullable', 'boolean'],
        ], [
            'supplier_id.required' => 'Elige el proveedor.',
            'items.required'       => 'Agrega al menos un producto a la compra.',
            'items.min'            => 'Agrega al menos un producto a la compra.',
        ]);
    }

    /** @return array<string, mixed> */
    private function listas(): array
    {
        return [
            'proveedores' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'productos'   => Product::orderBy('name')
                ->get(['id', 'name', 'sku', 'cost'])
                ->map(fn (Product $p) => [
                    'id'     => $p->id,
                    'nombre' => $p->name,
                    'sku'    => $p->sku,
                    'costo'  => (float) $p->cost,
                    'stock'  => (float) $p->stock,
                ])
                ->values(),
        ];
    }
}
