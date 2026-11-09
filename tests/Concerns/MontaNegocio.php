<?php

namespace Tests\Concerns;

use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Contexto;
use Database\Seeders\RolePermissionSeeder;

/**
 * Arrange común de las pruebas de flujos de dinero (H-05).
 *
 * Monta un negocio con una tienda, roles sembrados y el medio de pago
 * «Efectivo». Todo lo que toca modelos con ámbito se ejecuta dentro de
 * `trabajando()`, igual que en una petición real.
 */
trait MontaNegocio
{
    protected Empresa $empresa;
    protected Tienda $tienda;
    protected PaymentMethod $efectivo;

    protected function montarNegocio(string $slug): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->empresa = Empresa::create([
            'nombre' => "Negocio {$slug}", 'slug' => $slug, 'rubro' => 'general', 'estado' => 'activa',
        ]);

        $this->tienda = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Principal',
            'slug' => "principal-{$slug}", 'es_principal' => true, 'activa' => true,
        ]);

        $this->efectivo = $this->trabajando(
            fn () => PaymentMethod::create(['name' => 'Efectivo', 'is_active' => true])
        );
    }

    /** Usuario con un rol del sistema, asignado al negocio y a su tienda. */
    protected function empleado(string $rol): User
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->sync([$this->empresa->id => ['es_dueno' => false]]);
        $usuario->tiendas()->sync([$this->tienda->id]);
        $this->trabajando(fn () => $usuario->syncRoles([$rol]));

        return $usuario;
    }

    /** Producto con existencia en la tienda; el precio del local es `$precio`. */
    protected function productoConExistencia(
        string $nombre,
        float $precio,
        float $costo,
        float $stock,
        ?int $taxId = null,
    ): Product {
        return $this->trabajando(function () use ($nombre, $precio, $costo, $stock, $taxId) {
            $producto = Product::create([
                'name' => $nombre, 'price' => $precio, 'cost' => $costo,
                'tax_id' => $taxId, 'is_active' => true,
            ]);

            Existencia::create([
                'tienda_id' => $this->tienda->id, 'product_id' => $producto->id,
                'stock' => $stock, 'min_stock' => 0, 'precio' => $precio, 'activo' => true,
            ]);

            return $producto;
        });
    }

    /**
     * Ejecuta `$fn` dentro del negocio y la tienda de prueba y devuelve su resultado.
     *
     * Se puede anidar: comoEmpresa() ya restaura el contexto anterior al salir.
     * (Un olvidar() aquí borraba el contexto del trabajando() de afuera.)
     */
    protected function trabajando(callable $fn): mixed
    {
        return Contexto::comoEmpresa($this->empresa->id, $this->tienda->id, $fn);
    }

    /** Saldo del producto en la tienda de prueba, leído de la base. */
    protected function stockDe(Product $producto): float
    {
        return $this->trabajando(fn () => (float) $producto->fresh()->stock);
    }
}
