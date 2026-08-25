<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Rol;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Tienda;
use App\Models\Unit;
use App\Models\User;
use App\Support\Contexto;
use App\Support\Rubros;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Monta un negocio de prueba, completo, en un par de segundos.
 *
 * ── Qué problema resuelve ──
 *
 * El que Angel puso primero: «quiero que el sistema interactúe con el
 * usuario, que no dependa tanto de mí». Un interesado entra a la página,
 * dice qué clase de negocio tiene, y en el siguiente clic ya está adentro
 * con su catálogo puesto. Sin correo de vuelta, sin cita, sin que nadie
 * tenga que estar disponible.
 *
 * ── Por qué se siembra con datos y no vacío ──
 *
 * Porque un sistema vacío no se puede probar. Quien entra y ve cero
 * productos no está viendo el software: está viendo un formulario en
 * blanco. Con dieciocho productos de su propio rubro puede hacer una venta
 * de verdad en el primer minuto — y esa venta es la demostración entera.
 *
 * ── Las 2 h 30 ──
 *
 * `expira_en` se pone a 150 minutos. No lo hace cumplir este archivo: lo
 * hace el guardián de contexto, que ya niega la entrada a las empresas
 * vencidas. Aquí solo se pone la hora.
 *
 * Se eligió que venza en HORAS y no en días porque una demostración larga
 * no es más generosa: es una que el interesado deja para después y no
 * vuelve. Dos horas y media es el rato que alguien dedica de una sentada.
 */
class DemoService
{
    /** Lo que dura una demostración. */
    public const MINUTOS = 150;

    public function __construct(private StockService $stock) {}

    /**
     * @param  array{negocio:string, nombre:string, email:string, rubro:string, telefono?:string}  $datos
     */
    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            $rubro = Rubros::uno($datos['rubro']);

            $empresa = $this->montarEmpresa($datos, $rubro);
            $tienda  = $this->montarTienda($empresa);
            $usuario = $this->montarUsuario($datos, $empresa, $tienda);

            // Todo lo que sigue se escribe DENTRO de la empresa nueva. Sin
            // esto, los ganchos de los modelos pondrían `empresa_id` según
            // el contexto de quien está navegando —que en la página pública
            // no hay ninguno— y el catálogo quedaría sin dueño.
            Contexto::comoEmpresa($empresa->id, $tienda->id, function () use ($rubro, $empresa, $datos, $usuario) {
                $this->ajustes($datos);
                $this->rolesDelRubro($rubro, $empresa);
                $this->catalogo($rubro, $usuario);
                $this->gentePrimera($datos);
            });

            return $usuario;
        });
    }

    /** Cuántos minutos le quedan a esta demostración. */
    public function minutosRestantes(Empresa $empresa): ?int
    {
        return $empresa->minutosRestantes();
    }

    /* ═══════════════ Las piezas ═══════════════ */

    private function montarEmpresa(array $datos, array $rubro): Empresa
    {
        $nombre = trim($datos['negocio']);

        return Empresa::create([
            'nombre'       => $nombre,
            // Sufijo aleatorio: dos ferreterías que se llamen igual entran
            // el mismo día, y el slug es único.
            'slug'         => Str::slug($nombre) . '-' . Str::lower(Str::random(5)),
            'rubro'        => $datos['rubro'],
            'razon_social' => $nombre,
            'correo'       => $datos['email'],
            'telefono'     => $datos['telefono'] ?? null,
            'estado'       => 'activa',
            'es_demo'      => true,
            'expira_en'    => now()->addMinutes(self::MINUTOS),
            'plan_id'      => Plan::where('es_demo', true)->value('id'),
        ]);
    }

    private function montarTienda(Empresa $empresa): Tienda
    {
        return Tienda::create([
            'empresa_id'   => $empresa->id,
            'nombre'       => 'Principal',
            'slug'         => 'principal',
            'es_principal' => true,
            'activa'       => true,
        ]);
    }

    /**
     * La cuenta con la que entra.
     *
     * Se le da rol de Administrador y no de Superadministrador: el
     * superadministrador toca la configuración global del sistema, que no
     * es de él. Administrador ve todo lo suyo, que es lo que vino a ver.
     */
    private function montarUsuario(array $datos, Empresa $empresa, Tienda $tienda): User
    {
        $usuario = new User();
        $usuario->name              = trim($datos['nombre']);
        $usuario->email             = Str::lower(trim($datos['email']));
        $usuario->password          = Hash::make($datos['password'] ?? Str::random(24));
        $usuario->email_verified_at = now();
        $usuario->save();

        // `sync` y no `attach`: el gancho del modelo User pudo haberlo
        // metido en la empresa que estuviera activa. Aquí solo va en la suya.
        $usuario->empresas()->sync([$empresa->id => ['es_dueno' => true]]);
        $usuario->tiendas()->sync([$tienda->id]);

        Contexto::comoEmpresa($empresa->id, $tienda->id, fn () => $usuario->syncRoles(['Administrador']));

        return $usuario;
    }

    private function ajustes(array $datos): void
    {
        Setting::guardar([
            'negocio.nombre'    => trim($datos['negocio']),
            'negocio.correo'    => $datos['email'],
            'negocio.telefono'  => $datos['telefono'] ?? '',
        ], 'negocio');
    }

    /**
     * Los cargos propios de ese rubro.
     *
     * Se crean como roles de ESTA empresa, no del sistema: el «Estilista»
     * de esta peluquería no le aparece a la ferretería de al lado.
     */
    private function rolesDelRubro(array $rubro, Empresa $empresa): void
    {
        foreach ($rubro['roles'] as $nombre => $permisos) {
            $rol = Rol::create([
                'name'       => $nombre,
                'guard_name' => 'web',
                'empresa_id' => $empresa->id,
            ]);

            $rol->syncPermissions($permisos);
        }
    }

    /**
     * Unidades, categorías, impuestos, medios de pago y productos.
     *
     * Los productos entran con su existencia inicial COMO MOVIMIENTO y no
     * como un número puesto a mano. Cuesta una fila más por producto, y a
     * cambio el kardex de la demostración arranca cuadrado: si el
     * interesado abre el movimiento de un producto, ve de dónde salió cada
     * unidad. Un kardex que no cuadra en una demostración es peor que no
     * tener kardex.
     */
    private function catalogo(array $rubro, User $usuario): void
    {
        /* ── Unidades ── */
        $unidades = [];

        foreach ($rubro['unidades'] as [$nombre, $simbolo]) {
            $unidades[$simbolo] = Unit::create(['name' => $nombre, 'symbol' => $simbolo])->id;
        }

        /* ── Categorías ── */
        $categorias = [];

        foreach ($rubro['categorias'] as $nombre) {
            $categorias[$nombre] = Category::create(['name' => $nombre, 'is_active' => true])->id;
        }

        /* ── Impuestos ──
           Colombia: el 19% y el exento. Con esos dos se cubre casi todo el
           comercio de barrio, y más opciones en una demostración solo
           estorban. */
        $iva = Tax::create(['name' => 'IVA 19%', 'rate' => 19, 'type' => 'percentage', 'is_active' => true]);
        Tax::create(['name' => 'Excluido', 'rate' => 0, 'type' => 'percentage', 'is_active' => true]);

        /* ── Medios de pago ── */
        foreach (['Efectivo', 'Tarjeta débito', 'Tarjeta crédito', 'Nequi', 'Daviplata', 'Transferencia'] as $medio) {
            PaymentMethod::create(['name' => $medio, 'is_active' => true]);
        }

        /* ── Productos ── */
        foreach ($rubro['productos'] as [$nombre, $categoria, $precio, $costo, $stock, $unidad]) {
            $producto = Product::create([
                'name'        => $nombre,
                'category_id' => $categorias[$categoria] ?? null,
                'unit_id'     => $unidades[$unidad] ?? null,
                'tax_id'      => $iva->id,
                'price'       => $precio,
                'cost'        => $costo,
                'is_active'   => true,
                'created_by'  => $usuario->id,
            ]);

            Existencia::create([
                'tienda_id'  => Contexto::tiendaId(),
                'product_id' => $producto->id,
                'stock'      => 0,
                // Un mínimo proporcional: avisa cuando queda una quinta
                // parte. Poner cero haría que la alerta de stock bajo nunca
                // se dispare, y esa alerta es de lo que más se muestra.
                'min_stock'  => max(1, (int) round($stock * 0.2)),
                'activo'     => true,
            ]);

            if ($stock > 0) {
                $this->stock->mover(
                    $producto,
                    $stock,
                    'inicial',
                    null,
                    $usuario->id,
                    'Existencia inicial de la demostración',
                    $costo,
                );
            }
        }
    }

    /** Un cliente de mostrador y un proveedor, para no arrancar en cero. */
    private function gentePrimera(array $datos): void
    {
        Customer::create([
            'first_name' => 'Cliente',
            'last_name'  => 'de mostrador',
        ]);

        Customer::create([
            'first_name' => trim(Str::before($datos['nombre'], ' ')) ?: 'Cliente',
            'last_name'  => trim(Str::after($datos['nombre'], ' ')),
            'email'      => $datos['email'],
            'phone'      => $datos['telefono'] ?? null,
        ]);

        Supplier::create([
            'name'         => 'Proveedor de ejemplo',
            'contact_name' => 'Cámbialo por el tuyo',
            'is_active'    => true,
        ]);
    }
}
