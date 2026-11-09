<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Tienda;
use App\Models\Unit;
use App\Models\User;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La prueba que decide si el multitienda está bien hecho.
 *
 * Se arman dos negocios completos —dos empresas, con su local, su catálogo
 * y sus documentos— y se comprueba, modelo por modelo, que trabajando en
 * uno no se ve NI UNA fila del otro.
 *
 * ── Por qué así y no «revisando el código» ──
 *
 * Una consulta a la que se le olvidó el filtro no da error: devuelve de
 * más. No hay pantalla roja, no hay excepción en el log, no hay nada que
 * avise. El día que pase, lo que se ve es la ferretería de William con
 * clientes del supermercado de Diego adentro, y para entonces ya se
 * enseñó.
 *
 * Por eso el aislamiento no se comprueba leyendo: se comprueba contando.
 * Si un modelo nuevo se agrega mañana sin el trait, esta prueba es la que
 * lo dice.
 */
class AislamientoTest extends TestCase
{
    use RefreshDatabase;

    private array $ferreteria;
    private array $supermercado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ferreteria   = $this->montarNegocio('Ferretería El Tornillo', 'ferreteria');
        $this->supermercado = $this->montarNegocio('Supermercado La Esquina', 'supermercado');

        Contexto::olvidar();
    }

    /* ═══════════════ Las pruebas ═══════════════ */

    /**
     * La prueba central: dentro de un negocio no existe nada del otro.
     *
     * Se recorren TODOS los modelos aislados. La lista está escrita a mano
     * a propósito: si alguien agrega un modelo y no lo pone aquí, esta
     * prueba no lo cubre — y es más fácil acordarse de una lista que de un
     * trait, porque la lista está en la prueba que uno corre.
     */
    public function test_dentro_de_un_negocio_no_se_ve_nada_del_otro(): void
    {
        $modelos = [
            Product::class, Category::class, Unit::class, Tax::class,
            PaymentMethod::class, Attribute::class, Supplier::class, Customer::class,
            Sale::class, Purchase::class, SaleReturn::class, CashSession::class,
            StockMovement::class, Existencia::class,
            SaleItem::class, SalePayment::class, PurchaseItem::class, SaleReturnItem::class,
        ];

        foreach ([$this->ferreteria, $this->supermercado] as $negocio) {
            $this->trabajandoEn($negocio, function () use ($modelos, $negocio) {
                foreach ($modelos as $modelo) {
                    $total = $modelo::query()->count();

                    $this->assertSame(
                        1,
                        $total,
                        "Trabajando en «{$negocio['empresa']->nombre}», {$modelo} devolvió {$total} "
                        . 'fila(s) en vez de 1. O le falta el trait de aislamiento, o el filtro no está entrando.'
                    );
                }
            });
        }
    }

    /** Buscar por id el registro del vecino tiene que dar «no existe». */
    public function test_no_se_puede_abrir_un_registro_del_otro_negocio(): void
    {
        $ajeno = $this->supermercado['producto']->id;

        $this->trabajandoEn($this->ferreteria, function () use ($ajeno) {
            $this->assertNull(
                Product::find($ajeno),
                'Se pudo cargar por id un producto de otra empresa. El filtro no está cubriendo find().'
            );
        });
    }

    /**
     * Lo que se crea, se crea en el negocio donde uno está.
     *
     * Es la otra mitad: filtrar al leer no sirve de nada si al escribir la
     * fila queda sin dueño o con el dueño equivocado.
     */
    public function test_lo_que_se_crea_queda_en_el_negocio_activo(): void
    {
        $this->trabajandoEn($this->ferreteria, function () {
            $cliente = Customer::create(['first_name' => 'Cliente nuevo']);

            $this->assertSame(
                $this->ferreteria['empresa']->id,
                $cliente->empresa_id,
                'Un registro creado no quedó marcado con la empresa activa.'
            );
        });
    }

    /** Un documento nuevo cae en el local donde se está trabajando. */
    public function test_un_documento_nuevo_cae_en_el_local_activo(): void
    {
        $this->trabajandoEn($this->supermercado, function () {
            $venta = Sale::create([
                'number'  => 'V-PRUEBA',
                'user_id' => $this->supermercado['usuario']->id,
            ]);

            $this->assertSame(
                $this->supermercado['tienda']->id,
                $venta->tienda_id,
                'Una venta nueva no quedó marcada con el local activo.'
            );
        });
    }

    /**
     * Sumar no puede colarse por debajo del filtro.
     *
     * Un `count()` filtrado y un `sum()` sin filtrar sería lo peor de los
     * dos mundos: la pantalla se ve bien y los totales están inflados con
     * plata de otro cliente.
     */
    public function test_los_totales_tampoco_se_mezclan(): void
    {
        $this->trabajandoEn($this->ferreteria, function () {
            $this->assertEquals(
                100_000,
                (float) Sale::query()->sum('total'),
                'La suma de ventas incluyó ventas de otra empresa.'
            );

            $this->assertEquals(
                2,
                (float) SaleItem::query()->sum('quantity'),
                'La suma de unidades vendidas incluyó líneas de otra empresa.'
            );
        });
    }

    /** Las relaciones tampoco se saltan el filtro. */
    public function test_las_relaciones_no_traen_datos_del_vecino(): void
    {
        $this->trabajandoEn($this->ferreteria, function () {
            $tiendas = Tienda::where('empresa_id', $this->supermercado['empresa']->id)->pluck('id');

            $this->assertSame(
                0,
                Sale::whereIn('tienda_id', $tiendas)->count(),
                'Pidiendo explícitamente las ventas del otro local, el filtro las dejó pasar.'
            );
        });
    }

    /**
     * `sinFiltro()` sí ve todo — y lo devuelve todo.
     *
     * Se comprueba porque el panel de superadministrador va a depender de
     * esto: si el escape no funcionara, el día que se necesite alguien lo
     * resolvería quitando el filtro de otra manera, y ahí sí se rompe todo.
     */
    public function test_el_escape_a_proposito_si_ve_las_dos_empresas(): void
    {
        $this->trabajandoEn($this->ferreteria, function () {
            $this->assertSame(2, Contexto::sinFiltro(fn () => Product::query()->count()));

            // Y al salir del bloque, el filtro vuelve a estar puesto.
            $this->assertSame(1, Product::query()->count());
        });
    }

    /** Si el bloque sin filtro revienta, el filtro tiene que volver igual. */
    public function test_el_escape_se_cierra_aunque_lo_de_adentro_falle(): void
    {
        $this->trabajandoEn($this->ferreteria, function () {
            try {
                Contexto::sinFiltro(function () {
                    throw new \RuntimeException('algo falló adentro');
                });
            } catch (\RuntimeException $e) {
                // Se esperaba.
            }

            $this->assertFalse(
                Contexto::sinFiltroActivo(),
                'Una excepción dejó el aislamiento levantado para el resto de la petición.'
            );

            $this->assertSame(1, Product::query()->count());
        });
    }

    /**
     * La bitácora es la fuga más silenciosa: guarda copia de los nombres y
     * de los precios, y nadie la mira a diario.
     */
    public function test_la_bitacora_no_deja_ver_lo_del_vecino(): void
    {
        [$empresas, $mias, $todas] = $this->trabajandoEn($this->ferreteria, fn () => [
            AuditLog::query()->pluck('empresa_id')->unique()->values()->all(),
            AuditLog::query()->count(),
            Contexto::sinFiltro(fn () => AuditLog::query()->count()),
        ]);

        $this->assertGreaterThan(0, $mias,
            'No se escribió ni una línea de bitácora: la prueba no está probando nada.');

        $this->assertLessThan($todas, $mias,
            'La bitácora del otro negocio no se está filtrando.');

        $this->assertSame(
            [$this->ferreteria['empresa']->id],
            $empresas,
            'La pantalla de Auditoría está mostrando movimientos de otro negocio.'
        );
    }

    /* ═══════════════ El guardián de las peticiones ═══════════════ */

    public function test_un_usuario_sin_negocio_no_entra(): void
    {
        $huerfano = User::factory()->create();

        // El gancho del modelo lo metió en la empresa que hubiera activa;
        // aquí se le quita a propósito para simular al usuario que quedó
        // sin negocio.
        $huerfano->empresas()->detach();

        $this->actingAs($huerfano)->get('/dashboard')->assertForbidden();
    }

    public function test_no_se_puede_cambiar_a_un_negocio_ajeno(): void
    {
        $this->actingAs($this->ferreteria['usuario'])
            ->from('/dashboard')
            ->post('/contexto/cambiar', [
                'empresa_id' => $this->supermercado['empresa']->id,
                'tienda_id'  => $this->supermercado['tienda']->id,
            ])
            ->assertSessionHasErrors('empresa_id');
    }

    public function test_una_pestana_vieja_no_puede_escribir(): void
    {
        $usuario = $this->ferreteria['usuario'];

        $this->actingAs($usuario)
            ->from('/customers')
            ->post('/customers', [
                'first_name' => 'Escrito desde otra pestaña',
                '_ctx'       => '999:999',
            ])
            ->assertStatus(409);
    }

    public function test_con_la_firma_al_dia_si_deja_escribir(): void
    {
        $negocio = $this->ferreteria;
        $usuario = $negocio['usuario'];
        $firma   = $negocio['empresa']->id . ':' . $negocio['tienda']->id;

        $respuesta = $this->actingAs($usuario)
            ->from('/customers')
            ->post('/customers', [
                'first_name' => 'Cliente de verdad',
                '_ctx'       => $firma,
            ]);

        $this->assertNotSame(409, $respuesta->getStatusCode(),
            'La firma correcta fue rechazada: el guardián está bloqueando trabajo legítimo.');
    }

    /* ═══════════════ Montaje ═══════════════ */

    /**
     * Arma un negocio completo: empresa, local, usuario y una fila de cada
     * cosa que el sistema guarda.
     *
     * Se escribe dentro de `comoEmpresa()` para que los ganchos de creación
     * pongan `empresa_id` y `tienda_id` solos — que es exactamente como va
     * a pasar en producción.
     */
    private function montarNegocio(string $nombre, string $slug): array
    {
        $empresa = Empresa::create([
            'nombre' => $nombre,
            'slug'   => $slug,
            'rubro'  => 'general',
            'estado' => 'activa',
        ]);

        $tienda = Tienda::create([
            'empresa_id'   => $empresa->id,
            'nombre'       => 'Principal',
            'slug'         => 'principal',
            'es_principal' => true,
            'activa'       => true,
        ]);

        $usuario = User::factory()->create(['name' => 'Dueño de ' . $nombre]);

        // `sync` y no `attach`: el gancho del modelo ya lo metió en la
        // empresa que estuviera activa al crearlo, y para esta prueba
        // tiene que pertenecer a una sola.
        $usuario->empresas()->sync([$empresa->id => ['es_dueno' => true]]);
        $tienda->usuarios()->attach($usuario->id);

        $piezas = Contexto::comoEmpresa($empresa->id, $tienda->id, function () use ($usuario, $slug) {
            $unidad    = Unit::create(['name' => 'Unidad', 'symbol' => 'und']);
            $categoria = Category::create(['name' => 'General']);
            $impuesto  = Tax::create(['name' => 'IVA 19', 'rate' => 19]);
            $medio     = PaymentMethod::create(['name' => 'Efectivo']);
            Attribute::create(['name' => 'Color', 'type' => 'texto']);
            $proveedor = Supplier::create(['name' => 'Proveedor ' . $slug]);
            $cliente   = Customer::create(['first_name' => 'Cliente ' . $slug]);

            $producto = Product::create([
                'name'        => 'Producto ' . $slug,
                'category_id' => $categoria->id,
                'unit_id'     => $unidad->id,
                'price'       => 50_000,
                'cost'        => 30_000,
            ]);

            // El producto puede haber creado ya su existencia por el
            // controlador; aquí se escribe directo, sin pasar por él.
            Existencia::firstOrCreate(
                ['tienda_id' => Contexto::tiendaId(), 'product_id' => $producto->id],
                ['stock' => 10, 'min_stock' => 2, 'activo' => true]
            );

            StockMovement::create([
                'product_id'    => $producto->id,
                'type'          => 'entrada',
                'reason'        => 'inicial',
                'quantity'      => 10,
                'balance_after' => 10,
                'user_id'       => $usuario->id,
            ]);

            $turno = CashSession::create([
                'user_id'        => $usuario->id,
                'opening_amount' => 0,
                'status'         => 'abierta',
            ]);

            $venta = Sale::create([
                'number'          => 'V-' . $slug,
                'customer_id'     => $cliente->id,
                'user_id'         => $usuario->id,
                'cash_session_id' => $turno->id,
                'subtotal'        => 100_000,
                'total'           => 100_000,
                'paid_total'      => 100_000,
                'status'          => 'pagada',
            ]);

            $linea = SaleItem::create([
                'sale_id'    => $venta->id,
                'product_id' => $producto->id,
                'name'       => $producto->name,
                'quantity'   => 2,
                'unit_price' => 50_000,
                'subtotal'   => 100_000,
                'total'      => 100_000,
            ]);

            SalePayment::create([
                'sale_id'           => $venta->id,
                'payment_method_id' => $medio->id,
                'method_name'       => 'Efectivo',
                'amount'            => 100_000,
            ]);

            $compra = Purchase::create([
                'number'      => 'C-' . $slug,
                'supplier_id' => $proveedor->id,
                'user_id'     => $usuario->id,
                'subtotal'    => 30_000,
                'total'       => 30_000,
                'status'      => 'recibida',
                'ordered_at'  => now()->toDateString(),
            ]);

            PurchaseItem::create([
                'purchase_id' => $compra->id,
                'product_id'  => $producto->id,
                'name'        => $producto->name,
                'quantity'    => 1,
                'unit_cost'   => 30_000,
                'subtotal'    => 30_000,
                'total'       => 30_000,
            ]);

            $devolucion = SaleReturn::create([
                'number'   => 'D-' . $slug,
                'sale_id'  => $venta->id,
                'user_id'  => $usuario->id,
                'subtotal' => 50_000,
                'total'    => 50_000,
                'reason'   => 'Salió defectuoso',
            ]);

            SaleReturnItem::create([
                'sale_return_id' => $devolucion->id,
                'sale_item_id'   => $linea->id,
                'product_id'     => $producto->id,
                'name'           => $producto->name,
                'quantity'       => 1,
                'unit_price'     => 50_000,
                'subtotal'       => 50_000,
                'total'          => 50_000,
            ]);

            return ['producto' => $producto, 'venta' => $venta];
        });

        return [
            'empresa'  => $empresa,
            'tienda'   => $tienda,
            'usuario'  => $usuario,
            'producto' => $piezas['producto'],
            'venta'    => $piezas['venta'],
        ];
    }

    /** Corre algo parado dentro de ese negocio, y deja el contexto limpio. */
    private function trabajandoEn(array $negocio, callable $fn): mixed
    {
        $resultado = Contexto::comoEmpresa($negocio['empresa']->id, $negocio['tienda']->id, $fn);

        Contexto::olvidar();

        return $resultado;
    }
}
