<?php

namespace App\Http\Controllers;

use App\Support\Contexto;
use App\Models\Existencia;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Inventario: el kardex completo, el kardex de un producto y los ajustes
 * manuales de existencias.
 */
class InventoryController extends Controller implements HasMiddleware
{
    public function __construct(private StockService $stock) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.ver',     only: ['index', 'kardex']),
            new Middleware('permission:inventario.ajustar', only: ['ajustar', 'guardarAjuste']),
        ];
    }

    /** Kardex general: todos los movimientos, filtrables. */
    public function index(Request $request)
    {
        $consulta = StockMovement::query()
            ->with(['product:id,name,sku', 'user:id,name'])
            ->latest('id');

        if ($producto = $request->query('producto')) {
            $consulta->where('product_id', $producto);
        }

        if ($motivo = $request->query('motivo')) {
            $consulta->where('reason', $motivo);
        }

        if ($desde = $request->date('desde')) {
            $consulta->where('created_at', '>=', $desde->startOfDay());
        }

        if ($hasta = $request->date('hasta')) {
            $consulta->where('created_at', '<=', $hasta->endOfDay());
        }

        return view('inventory.index', [
            'movimientos' => $consulta->paginate(20)->withQueryString(),
            'productos'   => Product::orderBy('name')->get(['id', 'name', 'sku']),
            'motivos'     => [
                'venta'         => 'Venta',
                'compra'        => 'Compra',
                'ajuste_manual' => 'Ajuste manual',
                'conteo'        => 'Conteo físico',
                'anulacion'     => 'Anulación de venta',
                'devolucion'    => 'Devolución',
                'inicial'       => 'Saldo inicial',
            ],
            'filtros' => [
                'producto' => (string) $request->query('producto', ''),
                'motivo'   => (string) $request->query('motivo', ''),
                'desde'    => (string) $request->query('desde', ''),
                'hasta'    => (string) $request->query('hasta', ''),
            ],
            'resumen' => [
                // El valor y las unidades son de ESTE local: sumar todas las
                // tiendas daría un número que no le sirve a nadie que esté
                // parado en un mostrador.
                'valor'    => (float) Existencia::deTienda(Contexto::tiendaId())
                                        ->join('products', 'products.id', '=', 'existencias.product_id')
                                        ->selectRaw('COALESCE(SUM(products.cost * existencias.stock),0) as t')->value('t'),
                'unidades' => (float) Existencia::deTienda(Contexto::tiendaId())->sum('stock'),
                'bajos'    => Product::lowStock()->count(),
                'agotados' => Product::agotados()->count(),
            ],
        ]);
    }

    /** Kardex de un solo producto, con su saldo corriendo. */
    public function kardex(Product $product)
    {
        $movimientos = $product->movements()
            ->with('user:id,name')
            ->paginate(25);

        return view('inventory.kardex', compact('product', 'movimientos'));
    }

    public function ajustar(Product $product)
    {
        return view('inventory.ajustar', compact('product'));
    }

    public function guardarAjuste(Request $request, Product $product)
    {
        $datos = $request->validate([
            'modo'     => ['required', 'in:conteo,entrada,salida'],
            'cantidad' => ['required', 'numeric', 'min:0', 'max:999999'],
            'motivo'   => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'cantidad.required' => 'Indica la cantidad.',
            'motivo.required'   => 'Explica por qué se ajusta el inventario.',
            'motivo.min'        => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $cantidad = (float) $datos['cantidad'];
        $usuario  = $request->user()->id;
        $antes    = (float) $product->stock;

        $movimiento = match ($datos['modo']) {
            'conteo'  => $this->stock->ajustarA($product, $cantidad, $usuario, $datos['motivo']),
            'entrada' => $this->stock->ingresar($product, $cantidad, 'ajuste_manual', null, $usuario, $datos['motivo']),
            'salida'  => $this->stock->descontar($product, $cantidad, 'ajuste_manual', null, $usuario, $datos['motivo']),
        };

        if ($movimiento === null) {
            return back()->with('error', 'El conteo coincide con el saldo actual: no había nada que ajustar.');
        }

        return redirect()
            ->route('inventory.kardex', $product)
            ->with('success', 'Existencias de «' . $product->name . '»: '
                            . rtrim(rtrim(number_format($antes, 2, ',', '.'), '0'), ',') . ' → '
                            . rtrim(rtrim(number_format((float) $movimiento->balance_after, 2, ',', '.'), '0'), ','));
    }
}
