<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Empresa;
use App\Models\Existencia;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tienda;
use App\Models\User;
use App\Services\SaleService;
use App\Support\Contexto;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El arqueo, cuando hay vuelto.
 *
 * ── El fallo que estas pruebas clavan ──
 *
 * `sale_payments.amount` guarda lo que el cliente ENTREGÓ, no lo que la
 * venta valía. Es correcto que sea así: el recibo tiene que poder decir
 * «recibido $30.000, cambio $11.436».
 *
 * Pero el arqueo pregunta otra cosa —cuánta plata debería haber en el
 * cajón— y durante un tiempo sumó los pagos sin restar el vuelto. El
 * sistema esperaba el billete completo y le marcaba al cajero un faltante
 * del tamaño exacto del cambio, en CADA venta en efectivo con vuelto.
 *
 * No es un error que se note leyendo el código: las dos cifras son
 * ciertas, solo que responden preguntas distintas. Se nota cuando alguien
 * cuenta el cajón al final del día y no le cuadra, y para entonces ya
 * desconfía del sistema entero.
 *
 * Por eso hay una prueba por cada sitio donde ese número aparece: el
 * cálculo, la pantalla del turno y el informe de medios de pago. Los tres
 * lo calculaban por su cuenta, y los tres se equivocaban igual.
 */
class ArqueoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Tienda $tienda;
    private User $cajero;
    private Product $producto;
    private PaymentMethod $efectivo;
    private PaymentMethod $tarjeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->empresa = Empresa::create([
            'nombre' => 'Tienda de prueba', 'slug' => 'prueba-arqueo',
            'rubro'  => 'general', 'estado' => 'activa',
        ]);

        $this->tienda = Tienda::create([
            'empresa_id'   => $this->empresa->id,
            'nombre'       => 'Principal',
            'slug'         => 'principal-arqueo',
            'es_principal' => true,
            'activa'       => true,
        ]);

        $this->cajero = User::factory()->create();
        $this->cajero->empresas()->sync([$this->empresa->id => ['es_dueno' => true]]);
        $this->cajero->tiendas()->sync([$this->tienda->id]);

        $this->trabajando(function () {
            $this->cajero->syncRoles(['Administrador']);

            $this->efectivo = PaymentMethod::create(['name' => 'Efectivo', 'is_active' => true]);
            $this->tarjeta  = PaymentMethod::create(['name' => 'Tarjeta débito', 'is_active' => true]);

            // Sin impuesto: la prueba es sobre el vuelto, no sobre el IVA.
            // Un precio redondo deja las cuentas a la vista de quien lea
            // el fallo dentro de un año.
            $this->producto = Product::create([
                'name' => 'Cuaderno', 'price' => 10_000, 'cost' => 6_000, 'is_active' => true,
            ]);

            Existencia::create([
                'tienda_id'  => $this->tienda->id,
                'product_id' => $this->producto->id,
                'stock'      => 100,
                'min_stock'  => 0,
                'activo'     => true,
            ]);
        });
    }

    /* ═══════════════ 1. El cálculo ═══════════════ */

    public function test_el_efectivo_esperado_no_incluye_el_vuelto(): void
    {
        $this->trabajando(function () {
            $turno = $this->turno(100_000);

            // Vale 10.000, paga con 50.000: sobran 40.000 que se devuelven.
            $venta = $this->vender(1, [['payment_method_id' => $this->efectivo->id, 'amount' => 50_000]]);

            $this->assertEqualsWithDelta(40_000, (float) $venta->change_amount, 0.01, 'El vuelto no se calculó bien.');

            $this->assertEqualsWithDelta(
                110_000,
                $turno->fresh()->calcularEsperado(),
                0.01,
                'El arqueo espera el billete que entregó el cliente, no lo que quedó en el cajón: '
                . 'le va a marcar al cajero un faltante del tamaño del vuelto.'
            );
        });
    }

    public function test_sin_vuelto_el_esperado_es_la_base_mas_la_venta(): void
    {
        $this->trabajando(function () {
            $turno = $this->turno(100_000);

            $this->vender(1, [['payment_method_id' => $this->efectivo->id, 'amount' => 10_000]]);

            $this->assertEqualsWithDelta(110_000, $turno->fresh()->calcularEsperado(), 0.01);
        });
    }

    /**
     * El caso que separa «restar el vuelto» de «usar el total de la venta»:
     * parte se pagó con tarjeta, así que en el cajón no entró todo.
     */
    public function test_lo_que_se_paga_con_tarjeta_no_entra_al_cajon(): void
    {
        $this->trabajando(function () {
            $turno = $this->turno(100_000);

            // Vale 20.000: 6.000 con tarjeta y 20.000 en efectivo → vuelto 6.000.
            $this->vender(2, [
                ['payment_method_id' => $this->tarjeta->id,  'amount' =>  6_000],
                ['payment_method_id' => $this->efectivo->id, 'amount' => 20_000],
            ]);

            $this->assertEqualsWithDelta(
                114_000,
                $turno->fresh()->calcularEsperado(),
                0.01,
                'Con pago mixto el cajón tiene la base más el efectivo recibido menos el vuelto: '
                . 'ni el total de la venta ni la suma de los pagos sirven aquí.'
            );
        });
    }

    public function test_una_venta_anulada_sale_del_arqueo(): void
    {
        $this->trabajando(function () {
            $turno = $this->turno(100_000);

            $venta = $this->vender(1, [['payment_method_id' => $this->efectivo->id, 'amount' => 50_000]]);

            app(SaleService::class)->anular($venta, $this->cajero->id, 'Prueba');

            $this->assertEqualsWithDelta(
                100_000,
                $turno->fresh()->calcularEsperado(),
                0.01,
                'La plata de una venta anulada salió del cajón con el cliente, pero el arqueo la sigue esperando.'
            );
        });
    }

    /* ═══════════════ 2. La pantalla del turno ═══════════════ */

    public function test_la_pantalla_del_turno_muestra_el_efectivo_neto(): void
    {
        $turno = null;

        $this->trabajando(function () use (&$turno) {
            $turno = $this->turno(100_000);
            $this->vender(1, [['payment_method_id' => $this->efectivo->id, 'amount' => 50_000]]);
        });

        $respuesta = $this->actingAs($this->cajero)->get('/cash/' . $turno->id)->assertOk();

        $this->assertEqualsWithDelta(110_000, (float) $respuesta->viewData('esperado'), 0.01);

        $renglon = collect($respuesta->viewData('porMedio'))
            ->firstWhere('method_name', 'Efectivo');

        $this->assertNotNull($renglon, 'El desglose no trae el efectivo.');

        $this->assertEqualsWithDelta(
            10_000,
            (float) $renglon->total,
            0.01,
            'El desglose que el cajero compara contra el cajón dice una cifra y el efectivo esperado dice otra. '
            . 'Dos números que no cuadran entre sí, en la misma pantalla, en el momento en que más importa que cuadren.'
        );
    }

    /* ═══════════════ 3. El informe ═══════════════ */

    public function test_el_informe_de_medios_de_pago_descuenta_el_vuelto(): void
    {
        $this->trabajando(function () {
            $this->turno(100_000);
            $this->vender(1, [['payment_method_id' => $this->efectivo->id, 'amount' => 50_000]]);
        });

        $respuesta = $this->actingAs($this->cajero)->get('/reports')->assertOk();

        $efectivo = collect($respuesta->viewData('porMedio'))->firstWhere('nombre', 'Efectivo');

        $this->assertNotNull($efectivo, 'El informe no trae el renglón de efectivo.');

        $this->assertEqualsWithDelta(
            10_000,
            (float) $efectivo['total'],
            0.01,
            'El informe dice que por caja entraron cincuenta mil pesos de una venta de diez mil. '
            . 'Y encima no cuadra contra el facturado del mismo informe, que sí está bien.'
        );
    }

    /* ═══════════════ Montaje ═══════════════ */

    private function turno(float $base): CashSession
    {
        return CashSession::create([
            'tienda_id'      => $this->tienda->id,
            'user_id'        => $this->cajero->id,
            'opening_amount' => $base,
            'status'         => CashSession::ABIERTA,
            'opened_at'      => now(),
        ]);
    }

    /** @param array<int, array<string, mixed>> $pagos */
    private function vender(float $cantidad, array $pagos): Sale
    {
        return app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->producto->id, 'quantity' => $cantidad]],
            pagos: $pagos,
            userId: $this->cajero->id,
        );
    }

    private function trabajando(callable $fn): void
    {
        Contexto::comoEmpresa($this->empresa->id, $this->tienda->id, $fn);

        Contexto::olvidar();
    }
}
