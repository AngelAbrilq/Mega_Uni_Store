<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

/**
 * Punto de venta: la pantalla donde se atiende al cliente.
 */
class PosController extends Controller implements HasMiddleware
{
    public function __construct(private SaleService $ventas) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ventas.crear'),
        ];
    }

    public function index(Request $request)
    {
        $turno = CashSession::abiertaDe($request->user()->id);

        return view('pos.index', [
            'turno'     => $turno,
            'medios'    => PaymentMethod::active()->orderBy('name')->get(['id', 'name']),
            'clientes'  => Customer::orderBy('first_name')->take(300)
                                   ->get(['id', 'first_name', 'last_name', 'document_number']),
            // enTienda(): solo lo que ESTE local vende. Un producto sin
            // fila de existencia aquí no existe para este mostrador — es lo
            // que permite que la ferretería del centro maneje cemento y la
            // del norte no, sin duplicar el catálogo.
            'productos' => Product::query()
                ->active()
                ->enTienda()
                ->with('tax:id,name,rate,type', 'unit:id,symbol')
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'barcode', 'price', 'cost', 'unit_id', 'tax_id', 'category_id', 'image_url'])
                ->map(fn (Product $p) => [
                    'id'     => $p->id,
                    'nombre' => $p->name,
                    'sku'    => $p->sku,
                    'codigo' => $p->barcode,
                    // El precio de este local, que puede pisar el del catálogo.
                    'precio' => (float) $p->precio_tienda,
                    'stock'  => (float) $p->stock,
                    'unidad' => $p->unit->symbol ?? 'und',
                    'imp'    => $p->tax ? [
                        'nombre' => $p->tax->name,
                        'tasa'   => (float) $p->tax->rate,
                        'fijo'   => $p->tax->type === 'fixed',
                    ] : null,
                    'foto'   => $p->imagen,
                ])
                ->values(),
        ]);
    }

    /** Registra la venta y manda al recibo. */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'customer_id'               => ['nullable', 'exists:customers,id'],
            'notes'                     => ['nullable', 'string', 'max:500'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.product_id'        => ['required', 'exists:products,id'],
            'items.*.quantity'          => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'        => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'          => ['nullable', 'numeric', 'min:0'],
            'pagos'                     => ['required', 'array', 'min:1'],
            'pagos.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'pagos.*.amount'            => ['required', 'numeric', 'min:0'],
            'pagos.*.reference'         => ['nullable', 'string', 'max:100'],
        ], [
            'items.required' => 'Agrega al menos un producto antes de cobrar.',
            'pagos.required' => 'Indica cómo se pagó la venta.',
        ]);

        try {
            $venta = $this->ventas->registrar(
                lineas: $datos['items'],
                pagos: $datos['pagos'],
                userId: $request->user()->id,
                customerId: $datos['customer_id'] ?? null,
                notas: $datos['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        // imprimir=1 hace que el detalle abra el recibo apenas carga. Se
        // respeta lo que diga Configuración › Recibo: hay negocios que casi
        // nunca imprimen y para ellos esa ventana es un estorbo cada venta.
        $parametros = [$venta];

        if (\App\Models\Setting::activo('recibo.auto')) {
            $parametros['imprimir'] = 1;
        }

        return redirect()
            ->route('sales.show', $parametros)
            ->with('success', 'Venta ' . $venta->number . ' registrada por '
                            . \App\Support\Formato::moneda($venta->total) . '.');
    }
}
