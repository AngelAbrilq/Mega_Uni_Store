<?php

namespace App\Http\Controllers;

use App\Support\Contexto;
use App\Models\Existencia;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class DashboardController extends Controller implements HasMiddleware
{
    /** Para entrar al panel hace falta el permiso panel.ver. */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:panel.ver'),
        ];
    }

    /**
     * Definición de los módulos del panel.
     *
     * @return array<int, array<string, mixed>>
     */
    private function modulos(): array
    {
        return [
            ['key' => 'sales',           'label' => 'Ventas',         'route' => 'sales.index',           'table' => 'sales',           'model' => Sale::class,          'hint' => 'Todo lo cobrado',         'tone' => 'blue',    'icon' => 'box'],
            ['key' => 'products',        'label' => 'Productos',      'route' => 'products.index',        'table' => 'products',        'model' => Product::class,       'hint' => 'Catálogo y precios',      'tone' => 'blue',    'icon' => 'box'],
            ['key' => 'categories',      'label' => 'Categorías',     'route' => 'categories.index',      'table' => 'categories',      'model' => Category::class,      'hint' => 'Árbol de clasificación',  'tone' => 'sky',     'icon' => 'layers'],
            ['key' => 'customers',       'label' => 'Clientes',       'route' => 'customers.index',       'table' => 'customers',       'model' => Customer::class,      'hint' => 'Base de compradores',     'tone' => 'emerald', 'icon' => 'users'],
            ['key' => 'suppliers',       'label' => 'Proveedores',    'route' => 'suppliers.index',       'table' => 'suppliers',       'model' => Supplier::class,      'hint' => 'Compras y abastecimiento','tone' => 'amber',   'icon' => 'truck'],
            ['key' => 'units',           'label' => 'Unidades',       'route' => 'units.index',           'table' => 'units',           'model' => Unit::class,          'hint' => 'Medidas de venta',        'tone' => 'violet',  'icon' => 'ruler'],
            ['key' => 'taxes',           'label' => 'Impuestos',      'route' => 'taxes.index',           'table' => 'taxes',           'model' => Tax::class,           'hint' => 'Tarifas aplicables',      'tone' => 'rose',    'icon' => 'percent'],
            ['key' => 'payment_methods', 'label' => 'Medios de pago', 'route' => 'payment_methods.index', 'table' => 'payment_methods', 'model' => PaymentMethod::class, 'hint' => 'Formas de cobro',         'tone' => 'cyan',    'icon' => 'card'],
            ['key' => 'purchases',       'label' => 'Compras',        'route' => 'purchases.index',       'table' => 'purchases',       'model' => Purchase::class,      'hint' => 'Pedidos a proveedores',   'tone' => 'amber',   'icon' => 'truck'],
            ['key' => 'attributes',      'label' => 'Atributos',      'route' => 'attributes.index',      'table' => 'attributes',      'model' => Attribute::class,     'hint' => 'Variantes de producto',   'tone' => 'slate',   'icon' => 'tag'],
        ];
    }

    /**
     * Panel principal.
     */
    public function index(): View
    {
        $modulos = $this->modulos();

        foreach ($modulos as $i => $m) {
            $existe = Schema::hasTable($m['table']);

            $modulos[$i]['exists'] = $existe;
            $modulos[$i]['count']  = $existe ? $m['model']::query()->count() : null;
            $modulos[$i]['spark']  = $existe ? $this->serieSemanal($m['model']) : array_fill(0, 8, 0);
        }

        return view('dashboard', [
            'modules'      => $modulos,
            'serieMeses'   => $this->serieMensual(),
            'topCategorias'=> $this->topCategorias(),
            'ultProductos' => $this->ultimosProductos(),
            'ultClientes'  => $this->ultimosClientes(),
            'sistema'      => $this->estadoSistema($modulos),
            'inventario'   => $this->inventario(),
            'ventas'       => $this->ventas(),
        ]);
    }

    /**
     * Cifras de venta: hoy, esta semana y este mes, más las últimas
     * operaciones. Es lo primero que quiere ver quien abre el panel.
     *
     * @return array<string, mixed>
     */
    private function ventas(): array
    {
        $vacio = [
            'hoy' => 0.0, 'hoy_n' => 0, 'semana' => 0.0, 'mes' => 0.0, 'mes_n' => 0,
            'utilidad_mes' => 0.0, 'ticket' => 0.0, 'ultimas' => [], 'turno' => null,
        ];

        if (! Schema::hasTable('sales')) {
            return $vacio;
        }

        try {
            $hoy    = Sale::query()->pagadas()->entre(Carbon::today(), Carbon::now());
            $semana = Sale::query()->pagadas()->entre(Carbon::now()->startOfWeek(), Carbon::now());
            $mes    = Sale::query()->pagadas()->entre(Carbon::now()->startOfMonth(), Carbon::now());

            $ultimas = Sale::query()
                ->with('customer:id,first_name,last_name', 'user:id,name')
                ->latest('sold_at')
                ->take(5)
                ->get()
                ->map(fn (Sale $v) => [
                    'id'       => $v->id,
                    'numero'   => $v->number,
                    'cliente'  => $v->customer?->full_name ?? 'Consumidor final',
                    'vendedor' => $v->user->name ?? '—',
                    'total'    => (float) $v->total,
                    'anulada'  => $v->status === Sale::ANULADA,
                    'cuando'   => $v->sold_at?->locale('es')->diffForHumans() ?? '',
                ])
                ->all();

            return [
                'hoy'          => (float) (clone $hoy)->sum('total'),
                'hoy_n'        => (clone $hoy)->count(),
                'semana'       => (float) (clone $semana)->sum('total'),
                'mes'          => (float) (clone $mes)->sum('total'),
                'mes_n'        => (clone $mes)->count(),
                'utilidad_mes' => (float) (clone $mes)->sum('profit_total'),
                'ticket'       => (float) ((clone $mes)->avg('total') ?? 0),
                'ultimas'      => $ultimas,
                'turno'        => Schema::hasTable('cash_sessions')
                    ? \App\Models\CashSession::abiertaDe((int) auth()->id())
                    : null,
            ];
        } catch (\Throwable $e) {
            return $vacio;
        }
    }

    /**
     * Fotografía del inventario: cuánto dinero hay en bodega y qué
     * productos están por debajo de su punto de reorden.
     *
     * @return array<string, mixed>
     */
    private function inventario(): array
    {
        $vacio = [
            'valor_costo' => 0.0,
            'valor_venta' => 0.0,
            'unidades'    => 0,
            'bajos'       => 0,
            'agotados'    => 0,
            'lista'       => [],
        ];

        if (! Schema::hasTable('existencias') || ! Contexto::tiendaId()) {
            return $vacio;
        }

        try {
            // Los totales salen de las existencias de ESTE local, unidas al
            // producto para conocer su costo y su precio.
            $totales = Existencia::deTienda(Contexto::tiendaId())
                ->join('products', 'products.id', '=', 'existencias.product_id')
                ->selectRaw('COALESCE(SUM(products.cost * existencias.stock), 0) as costo')
                ->selectRaw('COALESCE(SUM(products.price * existencias.stock), 0) as venta')
                ->selectRaw('COALESCE(SUM(existencias.stock), 0) as unidades')
                ->first();

            $bajos = Product::query()->lowStock();

            $lista = (clone $bajos)
                ->with('category:id,name', 'unit:id,symbol')
                ->ordenPorExistencia('asc')
                ->take(6)
                ->get()
                ->map(fn (Product $p) => [
                    'id'        => $p->id,
                    'nombre'    => $p->name,
                    'categoria' => $p->category->name ?? 'Sin categoría',
                    'stock'     => (float) $p->stock,
                    'minimo'    => (float) $p->min_stock,
                    'simbolo'   => $p->unit->symbol ?? 'und',
                    'agotado'   => (float) $p->stock <= 0,
                ])
                ->all();

            return [
                'valor_costo' => (float) ($totales->costo ?? 0),
                'valor_venta' => (float) ($totales->venta ?? 0),
                'unidades'    => (float) ($totales->unidades ?? 0),
                'bajos'       => (clone $bajos)->count(),
                'agotados'    => Product::query()->agotados()->count(),
                'lista'       => $lista,
            ];
        } catch (\Throwable $e) {
            return $vacio;
        }
    }

    /**
     * Conteo de registros creados en cada una de las últimas 8 semanas.
     *
     * @param  class-string<Model>  $modelo
     * @return array<int, int>
     */
    private function serieSemanal(string $modelo): array
    {
        $baldes  = array_fill(0, 8, 0);
        $inicio  = Carbon::now()->startOfWeek()->subWeeks(7);
        $semanaA = Carbon::now()->startOfWeek();

        try {
            $fechas = $modelo::query()
                ->where('created_at', '>=', $inicio)
                ->orderBy('created_at')
                ->pluck('created_at');
        } catch (\Throwable $e) {
            return $baldes;
        }

        foreach ($fechas as $fecha) {
            if (! $fecha instanceof Carbon) {
                continue;
            }
            $delta = (int) abs($semanaA->diffInWeeks($fecha->copy()->startOfWeek()));
            $idx   = 7 - $delta;
            if ($idx >= 0 && $idx <= 7) {
                $baldes[$idx]++;
            }
        }

        return $baldes;
    }

    /**
     * Productos y clientes creados en cada uno de los últimos 6 meses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serieMensual(): array
    {
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $ref = Carbon::now()->startOfMonth()->subMonths($i);
            $meses[$ref->format('Y-m')] = [
                'clave'     => $ref->format('Y-m'),
                'etiqueta'  => mb_convert_case($ref->locale('es')->isoFormat('MMM'), MB_CASE_TITLE),
                'productos' => 0,
                'clientes'  => 0,
            ];
        }

        $desde = Carbon::now()->startOfMonth()->subMonths(5);

        foreach ([['products', Product::class, 'productos'], ['customers', Customer::class, 'clientes']] as [$tabla, $modelo, $campo]) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            try {
                $fechas = $modelo::query()->where('created_at', '>=', $desde)->pluck('created_at');
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($fechas as $fecha) {
                if (! $fecha instanceof Carbon) {
                    continue;
                }
                $clave = $fecha->format('Y-m');
                if (isset($meses[$clave])) {
                    $meses[$clave][$campo]++;
                }
            }
        }

        return array_values($meses);
    }

    /**
     * Categorías con más productos asociados.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topCategorias(): array
    {
        if (! Schema::hasTable('categories') || ! Schema::hasTable('products')) {
            return [];
        }

        try {
            return Category::query()
                ->withCount('products')
                ->orderByDesc('products_count')
                ->orderBy('name')
                ->take(6)
                ->get()
                ->map(fn (Category $c) => [
                    'nombre' => $c->name,
                    'total'  => (int) $c->products_count,
                ])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ultimosProductos(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        try {
            $consulta = Product::query()->latest('created_at')->take(5);

            if (Schema::hasTable('categories')) {
                $consulta->with('category:id,name');
            }

            return $consulta->get()->map(fn (Product $p) => [
                'nombre'    => $p->name,
                'categoria' => $p->category->name ?? 'Sin categoría',
                'precio'    => (float) ($p->price ?? 0),
                'activo'    => (bool) ($p->is_active ?? true),
                'fecha'     => $p->created_at?->locale('es')->diffForHumans() ?? '',
            ])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ultimosClientes(): array
    {
        if (! Schema::hasTable('customers')) {
            return [];
        }

        try {
            return Customer::query()
                ->latest('created_at')
                ->take(5)
                ->get()
                ->map(function (Customer $c) {
                    $nombre = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));

                    return [
                        'nombre'   => $nombre !== '' ? $nombre : 'Sin nombre',
                        'contacto' => $c->email ?: ($c->phone ?: 'Sin contacto'),
                        'doc'      => trim(($c->document_type ?? '') . ' ' . ($c->document_number ?? '')),
                        'fecha'    => $c->created_at?->locale('es')->diffForHumans() ?? '',
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Estado general del sistema.
     *
     * @param  array<int, array<string, mixed>>  $modulos
     * @return array<string, mixed>
     */
    private function estadoSistema(array $modulos): array
    {
        $migradas = 0;
        foreach ($modulos as $m) {
            if (! empty($m['exists'])) {
                $migradas++;
            }
        }

        $driver = '—';
        $base   = '—';
        $viva   = false;

        try {
            $conexion = DB::connection();
            $driver   = $conexion->getDriverName();
            $base     = $conexion->getDatabaseName();
            $conexion->getPdo();
            $viva = true;
        } catch (\Throwable $e) {
            $viva = false;
        }

        return [
            'db_viva'    => $viva,
            'db_driver'  => strtoupper((string) $driver),
            'db_nombre'  => is_string($base) ? basename($base) : '—',
            'migradas'   => $migradas,
            'total_mod'  => count($modulos),
            'laravel'    => app()->version(),
            'php'        => PHP_VERSION,
            'entorno'    => app()->environment(),
            'depuracion' => (bool) config('app.debug'),
        ];
    }
}
