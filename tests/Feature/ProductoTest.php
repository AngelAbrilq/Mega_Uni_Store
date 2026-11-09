<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\Product;
use App\Models\Tienda;
use App\Models\Unit;
use App\Models\User;
use App\Support\Contexto;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo, ya con la existencia por local.
 *
 * ── Por qué este archivo cambió ──
 *
 * Hasta la fase 2 del paso 4, `products` tenía una columna `stock` y el
 * inventario era un número por producto. Ahora el stock vive en
 * `existencias`, una fila por producto y por local — porque el mismo
 * tornillo puede estar sobrado en el centro y agotado en el norte.
 *
 * Estas pruebas seguían escritas para el mundo viejo: comprobaban una
 * columna que ya no existe y creaban productos con un stock que el modelo
 * ya no acepta. Pasaban a rojo sin que nada del sistema estuviera mal — y
 * eso es peor que un test que falla, porque enseña a ignorar los rojos.
 *
 * Ahora el contexto se monta explícito: empresa, local, y todo lo que se
 * crea, adentro. Es como se escribe cualquier prueba de aquí en adelante.
 */
class ProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Empresa $empresa;
    private Tienda $tienda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->empresa = Empresa::create([
            'nombre' => 'Papelería Central', 'slug' => 'papeleria-prod',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        $this->tienda = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Principal',
            'slug' => 'principal-prod', 'es_principal' => true, 'activa' => true,
        ]);

        $this->admin = $this->persona('Administrador');

        Contexto::olvidar();
    }

    /* ═══════════════ Alta ═══════════════ */

    public function test_se_puede_crear_un_producto(): void
    {
        [$categoria, $unidad] = $this->catalogoBase();

        $this->actingAs($this->admin)->post('/products', [
            'name'        => 'Cuaderno cuadriculado 100 hojas',
            'sku'         => 'PAP-0001',
            'category_id' => $categoria->id,
            'unit_id'     => $unidad->id,
            'cost'        => 5200,
            'price'       => 7800,
            'stock'       => 180,
            'min_stock'   => 40,
            'is_active'   => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['sku' => 'PAP-0001']);

        $this->trabajando(function () {
            $producto = Product::firstWhere('sku', 'PAP-0001');

            $this->assertNotNull($producto);
            $this->assertSame($this->admin->id, $producto->created_by);

            // El stock ya no es del producto: es de su fila en este local.
            $existencia = Existencia::deTienda($this->tienda->id)
                ->where('product_id', $producto->id)
                ->first();

            $this->assertNotNull($existencia, 'El producto nació sin existencia en el local activo.');
            $this->assertEqualsWithDelta(180, (float) $existencia->stock, 0.001);
            $this->assertEqualsWithDelta(40, (float) $existencia->min_stock, 0.001);
        });
    }

    /**
     * La existencia inicial entra como movimiento, no como número.
     *
     * Es lo que hace que el kardex arranque cuadrado: si alguien pregunta
     * de dónde salieron esas 180 unidades, hay una línea que lo dice.
     */
    public function test_la_existencia_inicial_queda_en_el_kardex(): void
    {
        [$categoria, $unidad] = $this->catalogoBase();

        $this->actingAs($this->admin)->post('/products', [
            'name' => 'Con kardex', 'sku' => 'PAP-0002',
            'category_id' => $categoria->id, 'unit_id' => $unidad->id,
            'cost' => 1000, 'price' => 2000, 'stock' => 25, 'min_stock' => 5,
            'is_active' => 1,
        ])->assertRedirect();

        $this->trabajando(function () {
            $producto = Product::firstWhere('sku', 'PAP-0002');

            $this->assertSame(
                1,
                $producto->movements()->count(),
                'El stock inicial se puso a mano: el kardex arrancaria sin explicacion.'
            );
        });
    }

    public function test_no_deja_guardar_un_costo_mayor_que_el_precio(): void
    {
        $this->actingAs($this->admin)
            ->post('/products', [
                'name'  => 'Producto mal calculado',
                'cost'  => 9000, 'price' => 5000,
                'stock' => 1, 'min_stock' => 0,
            ])
            ->assertSessionHasErrors('cost');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_el_sku_no_se_puede_repetir(): void
    {
        $this->trabajando(fn () => Product::create([
            'name' => 'Uno', 'sku' => 'REP-1', 'price' => 100, 'cost' => 50,
        ]));

        $this->actingAs($this->admin)
            ->post('/products', [
                'name'  => 'Dos', 'sku' => 'REP-1',
                'cost'  => 50, 'price' => 100,
                'stock' => 0, 'min_stock' => 0,
            ])
            ->assertSessionHasErrors('sku');
    }

    /* ═══════════════ Consultas ═══════════════ */

    public function test_el_filtro_de_stock_bajo_solo_trae_los_que_hay_que_reponer(): void
    {
        $this->trabajando(function () {
            $this->conExistencia('Suficiente',  90, 10);
            $this->conExistencia('Por reponer',  4, 10);
            $this->conExistencia('Agotado',      0,  5);

            $this->assertSame(2, Product::lowStock()->count());
        });
    }

    /** El stock que se ve es el del local en el que uno esta. */
    public function test_el_stock_es_el_del_local_activo(): void
    {
        $otro = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Norte',
            'slug' => 'norte-prod', 'es_principal' => false, 'activa' => true,
        ]);

        $producto = $this->trabajando(fn () => $this->conExistencia('Compartido', 40, 5));

        Existencia::create([
            'tienda_id' => $otro->id, 'product_id' => $producto->id,
            'stock' => 7, 'min_stock' => 5, 'activo' => true,
        ]);

        $enPrincipal = Contexto::comoEmpresa(
            $this->empresa->id, $this->tienda->id,
            fn () => Product::find($producto->id)->stock
        );

        $enNorte = Contexto::comoEmpresa(
            $this->empresa->id, $otro->id,
            fn () => Product::find($producto->id)->stock
        );

        $this->assertEqualsWithDelta(40, $enPrincipal, 0.001);
        $this->assertEqualsWithDelta(7, $enNorte, 0.001, 'El stock no cambio al cambiar de local.');
    }

    /* ═══════════════ Permisos y direcciones ═══════════════ */

    public function test_un_bodeguero_no_puede_eliminar_productos(): void
    {
        $bodeguero = $this->persona('Bodeguero');

        $producto = $this->trabajando(fn () => Product::create([
            'name' => 'Intocable', 'price' => 100, 'cost' => 50,
        ]));

        $this->actingAs($bodeguero)
            ->delete('/products/' . $producto->id)
            ->assertForbidden();

        $this->assertNull($producto->fresh()->deleted_at);
    }

    /**
     * Abrir un producto por su id tiene que funcionar.
     *
     * Parece de cajon y no lo era: el binding buscaba siempre por slug, y
     * el panel arma sus direcciones con el id. Todo el modulo respondia 404
     * al abrir cualquier ficha.
     */
    public function test_se_puede_abrir_un_producto_por_su_id(): void
    {
        $producto = $this->trabajando(fn () => Product::create([
            'name' => 'Cuaderno argollado', 'price' => 9000, 'cost' => 6000,
        ]));

        $this->actingAs($this->admin)
            ->get('/products/' . $producto->id)
            ->assertOk()
            ->assertSee('Cuaderno argollado');
    }

    /** Y el producto de otro negocio no se abre ni con el id en la mano. */
    public function test_no_se_abre_el_producto_de_otro_negocio(): void
    {
        $otra = Empresa::create([
            'nombre' => 'Ajena', 'slug' => 'ajena-prod',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        Tienda::create([
            'empresa_id' => $otra->id, 'nombre' => 'Principal',
            'slug' => 'principal-ajena-prod', 'es_principal' => true, 'activa' => true,
        ]);

        $ajeno = Contexto::comoEmpresa($otra->id, null, fn () => Product::create([
            'name' => 'Producto ajeno', 'price' => 100, 'cost' => 50,
        ]));

        $this->actingAs($this->admin)
            ->get('/products/' . $ajeno->id)
            ->assertNotFound();
    }

    /* ═══════════════ Apoyo ═══════════════ */

    private function persona(string $rol): User
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->sync([$this->empresa->id => ['es_dueno' => $rol === 'Administrador']]);
        $usuario->tiendas()->sync([$this->tienda->id]);

        Contexto::comoEmpresa($this->empresa->id, $this->tienda->id, fn () => $usuario->syncRoles([$rol]));

        return $usuario->fresh();
    }

    /** @return array{0: Category, 1: Unit} */
    private function catalogoBase(): array
    {
        return $this->trabajando(fn () => [
            Category::create(['name' => 'Papeleria', 'is_active' => true]),
            Unit::create(['name' => 'Unidad', 'symbol' => 'und', 'type' => 'Conteo']),
        ]);
    }

    /** Un producto con su fila de existencia en el local principal. */
    private function conExistencia(string $nombre, float $stock, float $minimo): Product
    {
        $producto = Product::create(['name' => $nombre, 'price' => 100, 'cost' => 50]);

        Existencia::create([
            'tienda_id'  => $this->tienda->id,
            'product_id' => $producto->id,
            'stock'      => $stock,
            'min_stock'  => $minimo,
            'activo'     => true,
        ]);

        return $producto->fresh();
    }

    private function trabajando(callable $fn): mixed
    {
        $resultado = Contexto::comoEmpresa($this->empresa->id, $this->tienda->id, $fn);

        Contexto::olvidar();

        return $resultado;
    }
}
