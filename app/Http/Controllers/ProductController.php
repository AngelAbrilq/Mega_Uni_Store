<?php

namespace App\Http\Controllers;

use App\Support\Contexto;
use App\Models\Existencia;
use App\Models\Category;
use App\Http\Controllers\Concerns\GuardaImagen;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Services\LimiteService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ProductController extends Controller implements HasMiddleware
{
    use GuardaImagen;

    public function __construct(private StockService $stock, private LimiteService $limites)
    {
    }

    /**
     * Cada acción exige su permiso. Los roles y permisos se crean en
     * database/seeders/RolePermissionSeeder.php.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:productos.ver',      only: ['index', 'show']),
            new Middleware('permission:productos.crear',    only: ['create', 'store']),
            new Middleware('permission:productos.editar',   only: ['edit', 'update']),
            new Middleware('permission:productos.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $consulta = Product::query()
            // with(...) trae categoría, unidad y proveedor en la misma
            // consulta: sin esto serían 3 consultas por cada fila (N+1).
            ->with(['category:id,name', 'unit:id,name,symbol', 'supplier:id,name'])
            ->search($request->query('q'));

        if ($categoria = $request->query('categoria')) {
            $consulta->where('category_id', $categoria);
        }

        if ($request->query('estado') === 'activos') {
            $consulta->where('is_active', true);
        } elseif ($request->query('estado') === 'inactivos') {
            $consulta->where('is_active', false);
        }

        if ($request->boolean('bajo')) {
            $consulta->lowStock();
        }

        $orden = match ($request->query('orden')) {
            'nombre' => ['name', 'asc'],
            'precio' => ['price', 'desc'],
            'stock'  => ['__existencia', 'asc'],
            default  => ['created_at', 'desc'],
        };

        // Ordenar por existencia no es un `order by` normal: la columna vive
        // en otra tabla y hay que unirse con ella. Se marca aparte para que
        // el resto de los órdenes sigan siendo el caso simple.
        $products = ($orden[0] === '__existencia'
                ? $consulta->ordenPorExistencia($orden[1])
                : $consulta->orderBy($orden[0], $orden[1]))
            ->paginate(12)
            ->withQueryString();

        return view('products.index', [
            'products'   => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'resumen'    => $this->resumen(),
            'filtros'    => [
                'q'         => (string) $request->query('q', ''),
                'categoria' => (string) $request->query('categoria', ''),
                'estado'    => (string) $request->query('estado', ''),
                'orden'     => (string) $request->query('orden', ''),
                'bajo'      => $request->boolean('bajo'),
            ],
        ]);
    }

    public function create()
    {
        return view('products.create', $this->listas());
    }

    public function store(Request $request)
    {
        // Antes de validar nada más: si el plan no da para otro producto,
        // no tiene sentido pedirle los datos y después decirle que no.
        $this->limites->exigir('productos');

        $data = $this->validar($request);

        $data['is_active']  = $request->boolean('is_active');
        $data['is_public']  = $request->boolean('is_public');
        $data['created_by'] = $request->user()?->id;
        $data['image_url']  = $this->guardarImagen($request, 'productos');

        unset($data['imagen']);

        // El stock ya no es una columna del producto: es una fila por local.
        // Se saca de aquí y se aplica aparte, para que quede en el kardex.
        $inicial = (float) ($data['stock'] ?? 0);
        $minimo  = (float) ($data['min_stock'] ?? 0);
        unset($data['stock'], $data['min_stock']);

        $product = Product::create($data);

        Existencia::create([
            'tienda_id'  => Contexto::tiendaId(),
            'product_id' => $product->id,
            'stock'      => 0,
            'min_stock'  => $minimo,
            'activo'     => true,
        ]);

        // La existencia inicial entra como movimiento, no como un número
        // puesto a mano: así el kardex arranca cuadrado desde el primer día
        // y siempre se puede explicar de dónde salió cada unidad.
        if ($inicial > 0) {
            $this->stock->ingresar(
                $product, $inicial, 'inicial', null, $request->user()?->id,
                'Existencia inicial al crear el producto'
            );
        }

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Producto «' . $product->name . '» creado.');
    }

    public function show(Product $product)
    {
        $product->load(['category.parent', 'unit', 'tax', 'supplier', 'creator', 'editor']);

        // Otros productos de la misma categoría, para navegar rápido.
        $relacionados = Product::query()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->orderBy('name')
            ->take(5)
            ->get(['id', 'name', 'sku', 'price']);

        return view('products.show', compact('product', 'relacionados'));
    }

    public function edit(Product $product)
    {
        return view('products.edit', array_merge($this->listas(), ['product' => $product]));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validar($request, $product);

        $data['is_active']  = $request->boolean('is_active');
        $data['is_public']  = $request->boolean('is_public');
        $data['updated_by'] = $request->user()?->id;

        $nueva = $this->guardarImagen($request, 'productos', $product->image_url);

        if ($nueva !== false) {
            $data['image_url'] = $nueva;
        }

        unset($data['imagen']);

        $stockPedido = array_key_exists('stock', $data) ? (float) $data['stock'] : null;
        $minimoPedido = array_key_exists('min_stock', $data) ? (float) $data['min_stock'] : null;
        unset($data['stock'], $data['min_stock']);

        $product->update($data);

        $this->cuadrarExistencia($product, $stockPedido, $minimoPedido, $request->user()?->id);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Producto «' . $product->name . '» actualizado.');
    }

    public function destroy(Product $product)
    {
        $nombre = $product->name;
        $product->delete();   // borrado lógico: queda registrado en deleted_at

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto «' . $nombre . '» eliminado.');
    }

    /* ───────────────────────── Apoyo ───────────────────────── */



    /**
     * Reglas de validación compartidas por store() y update().
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'sku'         => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'barcode'     => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($product?->id)],
            'imagen'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'unit_id'     => ['nullable', 'exists:units,id'],
            'tax_id'      => ['nullable', 'exists:taxes,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'price'       => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cost'        => ['required', 'numeric', 'min:0', 'max:99999999.99', 'lte:price'],
            // Numérico y no entero: hay productos que se venden por peso.
            'stock'       => ['required', 'numeric', 'min:0', 'max:999999'],
            'min_stock'   => ['required', 'numeric', 'min:0', 'max:999999'],
        ], [
            'name.required'      => 'El producto necesita un nombre.',
            'sku.unique'         => 'Ya existe otro producto con ese SKU.',
            'barcode.unique'     => 'Ese código de barras ya está registrado.',
            'imagen.image'       => 'El archivo debe ser una imagen.',
            'imagen.mimes'       => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'         => 'La imagen no puede pesar más de 2 MB.',
            'price.required'     => 'Indica el precio de venta.',
            'cost.required'      => 'Indica cuánto te cuesta el producto.',
            'cost.lte'           => 'El costo no puede ser mayor que el precio de venta.',
            'stock.required'     => 'Indica cuántas unidades hay disponibles.',
            'min_stock.required' => 'Indica a partir de cuántas unidades hay que reponer.',
        ]);
    }

    /**
     * Listas para los selectores del formulario.
     *
     * @return array<string, mixed>
     */
    private function listas(): array
    {
        return [
            'categories' => Category::with('parent:id,name')->orderBy('name')->get(),
            'units'      => Unit::orderBy('name')->get(),
            'taxes'      => Tax::orderBy('name')->get(),
            'suppliers'  => Supplier::orderBy('name')->get(),
        ];
    }

    /**
     * Cifras de cabecera del listado.
     *
     * @return array<string, mixed>
     */
    /**
     * Aplica en la existencia del local lo que el formulario pidió.
     *
     * Antes, editar un producto reescribía el stock en silencio y el kardex
     * no se enteraba: quedaban unidades que aparecían de la nada. Ahora un
     * cambio de existencia entra como ajuste, con su renglón y su autor.
     * El mínimo sí se puede escribir directo: es un parámetro, no mercancía.
     */
    private function cuadrarExistencia(Product $product, ?float $stock, ?float $minimo, ?int $userId): void
    {
        $tiendaId = Contexto::tiendaId();

        if (! $tiendaId) {
            return;
        }

        $existencia = Existencia::firstOrCreate(
            ['tienda_id' => $tiendaId, 'product_id' => $product->id],
            ['stock' => 0, 'min_stock' => 0, 'activo' => true]
        );

        if ($minimo !== null && abs((float) $existencia->min_stock - $minimo) > 0.0001) {
            $existencia->forceFill(['min_stock' => $minimo])->save();
        }

        if ($stock !== null) {
            $this->stock->ajustarA(
                $product, $stock, $userId,
                'Ajuste hecho al editar el producto', $tiendaId
            );
        }
    }

    /**
     * Cuánta plata hay parada en este local, al costo o al precio de venta.
     *
     * Va contra `existencias` y no contra `products`: el valor del
     * inventario de la ferretería del centro no incluye lo que hay en el
     * norte, por más que sea el mismo negocio.
     */
    private function valorInventario(string $columna): float
    {
        return (float) Existencia::deTienda(Contexto::tiendaId())
            ->join('products', 'products.id', '=', 'existencias.product_id')
            ->selectRaw("COALESCE(SUM(products.{$columna} * existencias.stock), 0) as t")
            ->value('t');
    }

    private function resumen(): array
    {
        return [
            'total'      => Product::count(),
            'activos'    => Product::where('is_active', true)->count(),
            'bajos'      => Product::lowStock()->count(),
            'agotados'   => Product::agotados()->count(),
            // Al costo y al precio de venta, contando solo lo de este local.
            'inventario' => (float) $this->valorInventario('cost'),
            'venta'      => (float) $this->valorInventario('price'),
        ];
    }
}
