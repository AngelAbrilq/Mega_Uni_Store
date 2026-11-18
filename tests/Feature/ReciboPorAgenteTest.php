<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Tienda;
use App\Models\User;
use App\Services\PrinterService;
use App\Services\SaleService;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * RN-28 · Fase 2: el botón «Imprimir en térmica» de la venta.
 *
 * Camino normal: navegador → agente (se prueba de punta a punta con el
 * navegador en tools/agente-caja y en la bitácora del reto). Aquí se prueba
 * lo que pone el servidor: los datos que recibe la página, el relevo por el
 * servidor cuando son la misma máquina, y los drivers de Linux del cajón.
 */
class ReciboPorAgenteTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $cajero;
    private Sale $venta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('recibo-agente');
        $this->cajero = $this->empleado('Cajero');
        $producto     = $this->productoConExistencia('Cuaderno', 4_000, 2_000, 20);

        $this->tienda->update(['drawer_mode' => Tienda::CAJON_AGENTE, 'agent_port' => 9200]);

        $this->venta = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $producto->id, 'quantity' => 1]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 4_000]],
            userId: $this->cajero->id,
        ));
    }

    /* ─────────────── La página de la venta ─────────────── */

    public function test_la_venta_ofrece_la_termica_con_agente_y_relevo_cuando_es_la_misma_maquina(): void
    {
        // Act: las pruebas llegan desde 127.0.0.1, como `php artisan serve` en el PC de caja.
        $respuesta = $this->actingAs($this->cajero)->get(route('sales.show', $this->venta));

        // Assert
        $respuesta->assertOk()
            ->assertSee('id="btnTermica"', false)
            ->assertSee('127.0.0.1:9200', false)
            ->assertSee('recibo\/relevo', false)
            ->assertSee('recibo\/escpos', false);
    }

    public function test_desde_otra_maquina_no_se_ofrece_el_relevo_pero_si_el_agente(): void
    {
        $respuesta = $this->actingAs($this->cajero)
            ->withServerVariables(['REMOTE_ADDR' => '190.25.10.7'])
            ->get(route('sales.show', $this->venta));

        $respuesta->assertOk()
            ->assertSee('id="btnTermica"', false)
            ->assertSee('"relevo":null', false);
    }

    public function test_sin_agente_o_con_recibo_a4_no_hay_boton_de_termica(): void
    {
        $this->trabajando(fn () => Setting::guardar(['recibo.formato' => 'A4']));

        $this->actingAs($this->cajero)->get(route('sales.show', $this->venta))
            ->assertOk()->assertDontSee('id="btnTermica"', false);

        $this->trabajando(fn () => Setting::guardar(['recibo.formato' => '80']));
        $this->tienda->update(['drawer_mode' => Tienda::CAJON_NINGUNO]);

        $this->actingAs($this->cajero)->get(route('sales.show', $this->venta))
            ->assertOk()->assertDontSee('id="btnTermica"', false);
    }

    /* ─────────────── Relevo por el servidor ─────────────── */

    public function test_el_relevo_entrega_el_recibo_al_agente_del_local_con_su_token(): void
    {
        // Arrange
        Http::fake(['127.0.0.1:9200/*' => Http::response(['impreso' => true, 'bytes' => 700])]);
        $token = $this->tienda->fresh()->asegurarTokenDeAgente();

        // Act
        $respuesta = $this->actingAs($this->cajero)->postJson(route('sales.recibo.relevo', $this->venta));

        // Assert
        $respuesta->assertOk()->assertJson(['impreso' => true]);

        Http::assertSent(function (PeticionHttp $peticion) use ($token) {
            $cuerpo = json_decode($peticion->body(), true);
            $bytes  = base64_decode($cuerpo['datos'] ?? '', true);

            return $peticion->url() === 'http://127.0.0.1:9200/imprimir'
                && $peticion->hasHeader('Content-Type', 'text/plain')
                && $cuerpo['token'] === $token
                && str_starts_with((string) $bytes, "\x1B\x40")
                && str_contains((string) $bytes, $this->venta->number);
        });
    }

    public function test_si_el_agente_esta_apagado_el_relevo_responde_502_sin_reventar(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->actingAs($this->cajero)->postJson(route('sales.recibo.relevo', $this->venta))
            ->assertStatus(502)
            ->assertJson(['impreso' => false])
            ->assertJsonMissing(['detalle' => 'Connection refused']);
    }

    public function test_si_el_agente_rechaza_el_token_el_relevo_lo_reporta(): void
    {
        Http::fake(['127.0.0.1:9200/*' => Http::response(['error' => 'token'], 401)]);

        $this->actingAs($this->cajero)->postJson(route('sales.recibo.relevo', $this->venta))
            ->assertStatus(502)
            ->assertJson(['impreso' => false, 'detalle' => 'token']);
    }

    public function test_el_relevo_se_niega_desde_otra_maquina_y_no_toca_la_red(): void
    {
        Http::fake();

        $this->actingAs($this->cajero)
            ->withServerVariables(['REMOTE_ADDR' => '190.25.10.7'])
            ->postJson(route('sales.recibo.relevo', $this->venta))
            ->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_el_relevo_se_niega_si_el_local_no_usa_agente_o_imprime_en_a4(): void
    {
        Http::fake();

        $this->trabajando(fn () => Setting::guardar(['recibo.formato' => 'A4']));
        $this->actingAs($this->cajero)->postJson(route('sales.recibo.relevo', $this->venta))->assertStatus(422);

        $this->tienda->update(['drawer_mode' => Tienda::CAJON_SERVIDOR]);
        $this->actingAs($this->cajero)->postJson(route('sales.recibo.relevo', $this->venta))->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_el_relevo_exige_permiso_y_no_imprime_ventas_de_otro_negocio(): void
    {
        Http::fake();

        // Sin «ventas.ver» no entra (los siete roles del sistema lo traen:
        // se le quitan todos para dejar a alguien del negocio sin permisos).
        $sinPermiso = $this->empleado('Cajero');
        $this->trabajando(fn () => $sinPermiso->syncRoles([]));

        $this->actingAs($sinPermiso)
            ->postJson(route('sales.recibo.relevo', $this->venta))->assertForbidden();

        // Otro negocio: la venta ni siquiera existe para él.
        $vecino      = Empresa::create(['nombre' => 'Vecino', 'slug' => 'vecino-recibo', 'rubro' => 'general', 'estado' => 'activa']);
        $localVecino = Tienda::create(['empresa_id' => $vecino->id, 'nombre' => 'Ajena', 'slug' => 'ajena-recibo', 'es_principal' => true, 'activa' => true, 'drawer_mode' => Tienda::CAJON_AGENTE]);
        $intruso     = User::factory()->create();
        $intruso->empresas()->sync([$vecino->id => ['es_dueno' => true]]);
        $intruso->tiendas()->sync([$localVecino->id]);
        Contexto::comoEmpresa($vecino->id, $localVecino->id, fn () => $intruso->syncRoles(['Administrador']));
        Contexto::olvidar();
        $this->flushSession(); // otra persona, otra sesión: sin esto heredaría el negocio del anterior

        $this->actingAs($intruso)->postJson(route('sales.recibo.relevo', $this->venta))->assertNotFound();

        Http::assertNothingSent();
    }

    /* ─────────────── Cajón en modo servidor sobre Linux ─────────────── */

    public function test_el_driver_linux_escribe_el_pulso_en_el_dispositivo(): void
    {
        // /dev/null acepta cualquier escritura: hace de impresora sin hardware.
        config(['hardware.cajon.driver' => 'linux', 'hardware.cajon.device' => '/dev/null']);

        $servicio = app(PrinterService::class);

        $this->assertTrue($servicio->abrirCajon());
        $this->assertSame('ok', $servicio->estado(forzar: true)['nivel']);
        $this->assertSame('/dev/null', $servicio->estado()['destino']);
    }

    public function test_el_driver_linux_reporta_caido_si_el_dispositivo_no_existe(): void
    {
        config(['hardware.cajon.driver' => 'linux', 'hardware.cajon.device' => '/dev/usb/no-existe-lp9']);

        $servicio = app(PrinterService::class);

        $this->assertFalse($servicio->abrirCajon());
        $this->assertSame('caido', $servicio->estado(forzar: true)['nivel']);
    }

    public function test_el_driver_linux_solo_acepta_rutas_bajo_dev(): void
    {
        $archivo = storage_path('framework/testing/no-es-un-dispositivo.bin');
        @unlink($archivo);
        config(['hardware.cajon.driver' => 'linux', 'hardware.cajon.device' => $archivo]);

        $this->assertFalse(app(PrinterService::class)->abrirCajon());
        $this->assertFileDoesNotExist($archivo, 'Un .env mal puesto no puede crear ni pisar archivos del servidor.');

        config(['hardware.cajon.device' => '/dev/../etc/passwd']);
        $this->assertFalse(app(PrinterService::class)->abrirCajon());
    }
}
