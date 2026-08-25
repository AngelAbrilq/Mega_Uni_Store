<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Product;
use App\Models\Tienda;
use App\Models\Traslado;
use App\Services\StockService;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mover mercancía de un local a otro.
 *
 * ── Por qué esto no es «un ajuste de inventario dos veces» ──
 *
 * Porque un ajuste dice «aquí hay veinte menos» y no dice a dónde fueron.
 * Un traslado dice las dos mitades a la vez y las amarra con un número.
 *
 * La diferencia se nota el día que la mercancía no llega: con dos ajustes,
 * el sistema muestra veinte de menos en una sede y nadie sabe si se
 * perdieron o si están en camino. Con un traslado, hay un documento con un
 * número, quién lo hizo y cuándo — que es por donde empieza la
 * conversación.
 */
class TrasladoController extends Controller implements HasMiddleware
{
    public function __construct(private StockService $stock) {}

    public static function middleware(): array
    {
        return [
            // Se apoya en el permiso de inventario y no en uno nuevo: quien
            // puede ajustar existencias a mano ya puede hacer cualquier cosa
            // con el inventario. Un permiso aparte daría la ilusión de un
            // control que no existe.
            new Middleware('permission:inventario.ver',     only: ['index']),
            new Middleware('permission:inventario.ajustar', only: ['create', 'store']),
        ];
    }

    public function index(Request $request)
    {
        $tienda = $request->query('tienda');

        $traslados = Traslado::query()
            ->with('product:id,name,sku', 'desde:id,nombre', 'hacia:id,nombre', 'user:id,name')
            ->when($tienda, fn ($c) => $c->deTienda((int) $tienda))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('traslados.index', [
            'traslados' => $traslados,
            'locales'   => $this->locales(),
            'tienda'    => (string) $tienda,
        ]);
    }

    public function create()
    {
        $locales = $this->locales();

        return view('traslados.create', [
            'locales'  => $locales,
            'unLocal'  => $locales->count() < 2,
            'origen'   => Contexto::tiendaId(),
        ]);
    }

    public function store(Request $request)
    {
        $locales = $this->locales()->pluck('id')->all();

        $datos = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'desde'      => ['required', 'integer', Rule::in($locales)],
            'hacia'      => ['required', 'integer', Rule::in($locales), 'different:desde'],
            'cantidad'   => ['required', 'numeric', 'gt:0'],
            'notas'      => ['nullable', 'string', 'max:255'],
        ], [
            'product_id.required' => 'Escoge qué producto se va a mover.',
            'desde.required'      => 'Di de qué local sale.',
            'hacia.required'      => 'Di a qué local llega.',
            'hacia.different'     => 'El origen y el destino no pueden ser el mismo local.',
            'hacia.in'            => 'Ese local no es de este negocio.',
            'desde.in'            => 'Ese local no es de este negocio.',
            'cantidad.gt'         => 'La cantidad tiene que ser mayor que cero.',
        ]);

        $producto = Product::findOrFail($datos['product_id']);

        $this->hayConQue($producto, (int) $datos['desde'], (float) $datos['cantidad']);

        $traslado = $this->stock->trasladar(
            $producto,
            (float) $datos['cantidad'],
            (int) $datos['desde'],
            (int) $datos['hacia'],
            $request->user()?->id,
            $datos['notas'] ?? null,
        );

        return redirect()->route('traslados.index')
            ->with('success', 'Traslado ' . $traslado->numero . ' registrado: '
                . $traslado->cantidad . ' × ' . $producto->name . '.');
    }

    /* ═══════════════ Apoyo ═══════════════ */

    /**
     * Que el local de origen tenga la mercancía.
     *
     * ── Por qué se comprueba aquí y no se deja al servicio ──
     *
     * Porque el servicio lanzaría una excepción técnica y esta pantalla
     * necesita devolver al formulario con el número exacto que sí hay. La
     * diferencia entre «error de existencias» y «en el Centro hay 12, no
     * 20» es la diferencia entre llamar a soporte y arreglarlo solo.
     */
    private function hayConQue(Product $producto, int $desde, float $cantidad): void
    {
        $existencia = Existencia::deTienda($desde)
            ->where('product_id', $producto->id)
            ->first();

        $hay = (float) ($existencia?->stock ?? 0);

        if ($hay >= $cantidad) {
            return;
        }

        $local = Tienda::find($desde)?->nombre ?? 'ese local';

        throw ValidationException::withMessages([
            'cantidad' => "En {$local} hay " . rtrim(rtrim(number_format($hay, 3, ',', '.'), '0'), ',')
                . ' de «' . $producto->name . '». No se puede mover más de lo que hay.',
        ]);
    }

    /** Los locales de esta empresa. El filtro global ya los acota. */
    private function locales()
    {
        return Tienda::where('activa', true)
            ->whereIn('id', Contexto::tiendaIds() ?? [])
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);
    }
}
