<?php

namespace Tests\Feature;

use App\Models\Existencia;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AvisoDelSistema;
use App\Notifications\StockBajo;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * SA-NOT-001 · Notificaciones persistentes: stock bajo automático, avisos
 * manuales, leer y marcar todas.
 * RN-37      · Se avisa al cruzar el mínimo, sin repetir lo no leído.
 */
class NotificacionesTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private User $bodeguero;
    private User $cajero;
    private Product $jabon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('notis');
        $this->admin     = $this->empleado('Administrador');
        $this->bodeguero = $this->empleado('Bodeguero');
        $this->cajero    = $this->empleado('Cajero');

        // 10 unidades con mínimo de 5
        $this->jabon = $this->productoConExistencia('Jabón', 3_000, 1_500, 10);
        $this->trabajando(fn () => Existencia::where('product_id', $this->jabon->id)->update(['min_stock' => 5]));
    }

    /* ═══════════════ RN-37 · Stock bajo ═══════════════ */

    public function test_al_cruzar_el_minimo_avisa_a_quienes_ven_inventario(): void
    {
        $this->vender(6); // 10 → 4

        $this->assertCount(1, $this->admin->fresh()->unreadNotifications);
        $this->assertCount(1, $this->bodeguero->fresh()->unreadNotifications);
        $this->assertCount(0, $this->cajero->fresh()->unreadNotifications, 'El cajero no ve inventario.');

        $n = $this->bodeguero->fresh()->unreadNotifications->first();
        $this->assertSame(StockBajo::class, $n->type);
        $this->assertSame('Stock bajo: Jabón', $n->data['titulo']);
        $this->assertSame('warning', $n->data['nivel']);
    }

    public function test_no_avisa_antes_del_minimo_ni_repite_lo_no_leido(): void
    {
        $this->vender(4); // 10 → 6: todavía arriba del mínimo
        $this->assertCount(0, $this->admin->fresh()->unreadNotifications);

        $this->vender(2); // 6 → 4: cruza
        $this->vender(1); // 4 → 3: ya estaba abajo
        $this->assertCount(1, $this->admin->fresh()->unreadNotifications);
    }

    public function test_agotado_es_urgente(): void
    {
        $this->vender(10);

        $this->assertSame('danger', $this->admin->fresh()->unreadNotifications->first()->data['nivel']);
    }

    public function test_si_las_alertas_estan_apagadas_no_avisa(): void
    {
        $this->trabajando(fn () => Setting::guardar(['inventario.alerta_activa' => '0'], 'inventario'));

        $this->vender(6);

        $this->assertCount(0, $this->admin->fresh()->unreadNotifications);
    }

    /* ═══════════════ Leer ═══════════════ */

    public function test_leer_una_notificacion_la_marca_y_lleva_a_su_enlace(): void
    {
        $this->vender(6);
        $n = $this->admin->fresh()->unreadNotifications->first();

        $this->actingAs($this->admin)->get("/notificaciones/{$n->id}/leer")
            ->assertRedirect(route('inventory.kardex', $this->jabon->id, false));

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_marcar_todas_como_leidas(): void
    {
        $this->avisar($this->admin, 'Uno');
        $this->avisar($this->admin, 'Dos');

        $this->actingAs($this->admin)->post('/notificaciones/leer-todas')->assertSessionHas('success');

        $this->assertCount(0, $this->admin->fresh()->unreadNotifications);
    }

    public function test_nadie_lee_la_notificacion_de_otro(): void
    {
        $this->avisar($this->bodeguero, 'Privado');
        $n = $this->bodeguero->fresh()->notifications->first();

        $this->actingAs($this->admin)->get("/notificaciones/{$n->id}/leer")->assertNotFound();
        $this->assertNull($n->fresh()->read_at);
    }

    /* ═══════════════ Avisos manuales ═══════════════ */

    public function test_el_administrador_avisa_a_su_negocio(): void
    {
        $this->actingAs($this->admin)
            ->post('/notificaciones/enviar', ['titulo' => 'Inventario el sábado', 'mensaje' => 'Llegar a las 7.', 'nivel' => 'info', 'alcance' => 'negocio'])
            ->assertSessionHas('success', 'Aviso enviado a 2 personas.');

        $this->assertSame('Inventario el sábado', $this->cajero->fresh()->unreadNotifications->first()->data['titulo']);
        $this->assertCount(0, $this->admin->fresh()->notifications, 'Quien envía no se avisa a sí mismo.');
    }

    public function test_solo_la_plataforma_avisa_a_todos_los_negocios_y_el_cajero_no_envia(): void
    {
        $this->actingAs($this->admin)->from('/notificaciones/enviar')
            ->post('/notificaciones/enviar', ['titulo' => 'X', 'mensaje' => 'Y', 'nivel' => 'info', 'alcance' => 'todos'])
            ->assertSessionHasErrors('alcance');

        $this->actingAs($this->cajero)->get('/notificaciones/enviar')->assertForbidden();
    }

    public function test_la_campana_y_el_centro_muestran_las_no_leidas(): void
    {
        $this->avisar($this->cajero, 'Cambio de turno');

        $this->actingAs($this->cajero)->get('/notificaciones')->assertOk()->assertSee('Cambio de turno')->assertSee('1 sin leer');
        $this->actingAs($this->cajero)->get('/dashboard')->assertOk()->assertSee('Cambio de turno')->assertSee('Ver las 1 notificaciones sin leer');
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function vender(float $cantidad): void
    {
        $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $this->jabon->id, 'quantity' => $cantidad]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 3_000 * $cantidad]],
            userId: $this->cajero->id,
        ));
    }

    private function avisar(User $a, string $titulo): void
    {
        $a->notify(new AvisoDelSistema($titulo, 'Mensaje de prueba'));
    }
}
