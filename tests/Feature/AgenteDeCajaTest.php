<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Tienda;
use App\Models\User;
use App\Services\PrinterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * RN-28 · Cajón por local con el servidor en la nube.
 *
 * En modo «agent» el pulso lo da el agente del PC de caja (tools/agente-caja):
 * el servidor no toca hardware, solo entrega la dirección y el token del
 * agente al POS de ESE local y audita las aperturas manuales.
 */
class AgenteDeCajaTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('agente');
        $this->admin  = $this->empleado('Administrador');
        $this->cajero = $this->empleado('Cajero');

        $this->tienda->update(['drawer_mode' => Tienda::CAJON_AGENTE, 'agent_port' => 9200]);
    }

    public function test_el_pos_apunta_al_agente_con_el_token_de_su_local(): void
    {
        $token = $this->tienda->fresh()->asegurarTokenDeAgente();

        $this->actingAs($this->cajero)->get('/pos')
            ->assertOk()
            ->assertSee('id="posCajon"', false)
            ->assertSee('127.0.0.1:9200', false)
            ->assertSee($token, false);
    }

    public function test_en_modo_agente_el_servidor_no_toca_el_hardware_y_audita_la_apertura_manual(): void
    {
        $this->mock(PrinterService::class, fn ($m) => $m->shouldNotReceive('abrirCajon'));

        $this->actingAs($this->cajero)
            ->postJson('/pos/cajon', ['manual' => 1, 'ok' => 1])
            ->assertOk()
            ->assertJson(['abierto' => true]);

        $this->trabajando(function () {
            $registro = AuditLog::where('event', 'cajon_abierto')->sole();
            $this->assertSame('agente', $registro->new_values['via']);
        });
    }

    public function test_sin_cajon_el_pos_no_ofrece_el_boton(): void
    {
        $this->tienda->update(['drawer_mode' => Tienda::CAJON_NINGUNO]);

        $this->actingAs($this->cajero)->get('/pos')
            ->assertOk()
            ->assertDontSee('id="posCajon"', false);
    }

    public function test_el_token_no_viaja_en_el_json_del_local(): void
    {
        $tienda = $this->tienda->fresh();
        $tienda->asegurarTokenDeAgente();

        $this->assertArrayNotHasKey('agent_token', $tienda->toArray());
    }

    public function test_renovar_el_token_deja_sin_efecto_el_anterior(): void
    {
        $anterior = $this->tienda->fresh()->asegurarTokenDeAgente();

        $this->actingAs($this->admin)
            ->post("/tiendas/{$this->tienda->id}/agente/token")
            ->assertRedirect();

        $nuevo = $this->tienda->fresh()->agent_token;
        $this->assertNotSame($anterior, $nuevo);
        $this->assertSame(40, strlen($nuevo));
    }

    public function test_no_se_renueva_el_token_de_un_local_de_otro_negocio(): void
    {
        $otra   = Empresa::create(['nombre' => 'Vecino', 'slug' => 'vecino-agente', 'rubro' => 'general', 'estado' => 'activa']);
        $ajena  = Tienda::create(['empresa_id' => $otra->id, 'nombre' => 'Ajena', 'slug' => 'ajena-agente', 'es_principal' => true, 'activa' => true]);

        $this->actingAs($this->admin)
            ->post("/tiendas/{$ajena->id}/agente/token")
            ->assertNotFound();
    }

    public function test_el_modo_del_cajon_se_valida(): void
    {
        $this->actingAs($this->admin)
            ->from("/tiendas/{$this->tienda->id}/edit")
            ->put("/tiendas/{$this->tienda->id}", ['nombre' => 'Principal', 'drawer_mode' => 'magia'])
            ->assertSessionHasErrors('drawer_mode');
    }
}
