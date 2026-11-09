<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * AT-CAJ-001 · Cajas registradoras físicas y movimientos de efectivo.
 * RN-31     · Una caja física admite un solo turno abierto a la vez.
 */
class CajasRegistradorasTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private User $cajero;
    private User $otroCajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('cajas');
        $this->admin      = $this->empleado('Administrador');
        $this->cajero     = $this->empleado('Cajero');
        $this->otroCajero = $this->empleado('Cajero');
    }

    /* ═══════════════ Gestión de cajas ═══════════════ */

    public function test_el_administrador_crea_una_caja_en_su_local(): void
    {
        $this->actingAs($this->admin)
            ->post('/cash_registers', ['code' => 'C1', 'name' => 'Principal'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cash_registers', [
            'tienda_id' => $this->tienda->id, 'code' => 'C1', 'name' => 'Principal', 'is_active' => true,
        ]);
    }

    public function test_no_se_repite_el_nombre_de_una_caja_en_el_mismo_local(): void
    {
        $this->caja('Principal');

        $this->actingAs($this->admin)
            ->from('/cash_registers/create')
            ->post('/cash_registers', ['name' => 'Principal'])
            ->assertSessionHasErrors('name');
    }

    public function test_el_cajero_no_administra_cajas(): void
    {
        $this->actingAs($this->cajero)->get('/cash_registers')->assertForbidden();
    }

    public function test_una_caja_con_historial_se_desactiva_en_vez_de_borrarse(): void
    {
        // Arrange
        $caja  = $this->caja('Principal');
        $turno = $this->abrir($this->cajero, $caja);
        $this->trabajando(fn () => $turno->update(['status' => CashSession::CERRADA, 'closed_at' => now()]));

        // Act
        $this->actingAs($this->admin)->delete("/cash_registers/{$caja->id}")->assertRedirect();

        // Assert
        $this->assertDatabaseHas('cash_registers', ['id' => $caja->id, 'is_active' => false]);
    }

    /* ═══════════════ RN-31 · Apertura en una caja ═══════════════ */

    public function test_si_el_local_tiene_cajas_el_turno_exige_elegir_una(): void
    {
        $this->caja('Principal');

        $this->actingAs($this->cajero)
            ->from('/cash/abrir')
            ->post('/cash', ['opening_amount' => 50_000])
            ->assertSessionHasErrors('cash_register_id');
    }

    public function test_sin_cajas_definidas_el_turno_se_abre_como_antes(): void
    {
        $this->actingAs($this->cajero)
            ->post('/cash', ['opening_amount' => 50_000])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cash_sessions', ['user_id' => $this->cajero->id, 'cash_register_id' => null]);
    }

    public function test_una_caja_no_admite_dos_turnos_abiertos(): void
    {
        // Arrange
        $caja = $this->caja('Principal');
        $this->abrir($this->cajero, $caja);

        // Act
        $this->actingAs($this->otroCajero)
            ->from('/cash/abrir')
            ->post('/cash', ['opening_amount' => 10_000, 'cash_register_id' => $caja->id])
            ->assertSessionHasErrors('cash_register_id');

        // Assert
        $this->assertDatabaseMissing('cash_sessions', ['user_id' => $this->otroCajero->id]);
    }

    public function test_no_se_abre_turno_en_la_caja_de_otro_local(): void
    {
        $sucursal = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Sucursal', 'slug' => 'sucursal-cajas',
            'es_principal' => false, 'activa' => true,
        ]);
        $this->caja('Principal');
        $ajena = CashRegister::sinFiltroEmpresa()->create(['tienda_id' => $sucursal->id, 'name' => 'Ajena']);

        $this->actingAs($this->cajero)
            ->from('/cash/abrir')
            ->post('/cash', ['opening_amount' => 10_000, 'cash_register_id' => $ajena->id])
            ->assertSessionHasErrors('cash_register_id');
    }

    /* ═══════════════ Movimientos de efectivo ═══════════════ */

    public function test_ingresos_y_retiros_ajustan_el_efectivo_esperado_del_arqueo(): void
    {
        // Arrange: base 100.000
        $turno = $this->abrir($this->cajero, null, 100_000);

        // Act: +20.000 de sencillo, −50.000 consignados
        $this->actingAs($this->cajero)->post("/cash/{$turno->id}/movimientos",
            ['type' => 'ingreso', 'amount' => 20_000, 'reason' => 'Sencillo del supervisor'])->assertSessionHasNoErrors();
        $this->actingAs($this->cajero)->post("/cash/{$turno->id}/movimientos",
            ['type' => 'retiro', 'amount' => 50_000, 'reason' => 'Consignación al banco'])->assertSessionHasNoErrors();

        $this->actingAs($this->cajero)->post("/cash/{$turno->id}/cerrar", ['counted_amount' => 70_000]);

        // Assert: 100.000 + 20.000 − 50.000 = 70.000 → cuadra
        $cerrado = $this->trabajando(fn () => $turno->fresh());
        $this->assertSame(CashSession::CERRADA, $cerrado->status);
        $this->assertEqualsWithDelta(70_000, (float) $cerrado->expected_amount, 0.01);
        $this->assertEqualsWithDelta(0, (float) $cerrado->difference, 0.01);
    }

    public function test_no_se_retira_mas_efectivo_del_que_hay_en_caja(): void
    {
        $turno = $this->abrir($this->cajero, null, 30_000);

        $this->actingAs($this->cajero)
            ->from("/cash/{$turno->id}")
            ->post("/cash/{$turno->id}/movimientos", ['type' => 'retiro', 'amount' => 30_001, 'reason' => 'Demasiado'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, CashMovement::sinFiltroEmpresa()->count());
    }

    public function test_no_se_registran_movimientos_en_un_turno_cerrado(): void
    {
        $turno = $this->abrir($this->cajero, null, 30_000);
        $this->trabajando(fn () => $turno->update(['status' => CashSession::CERRADA, 'closed_at' => now()]));

        $this->actingAs($this->cajero)
            ->from("/cash/{$turno->id}")
            ->post("/cash/{$turno->id}/movimientos", ['type' => 'ingreso', 'amount' => 1_000, 'reason' => 'Tarde'])
            ->assertSessionHasErrors('amount');
    }

    public function test_un_cajero_no_mueve_efectivo_del_turno_de_otro(): void
    {
        $turno = $this->abrir($this->otroCajero, null, 30_000);

        $this->actingAs($this->cajero)
            ->post("/cash/{$turno->id}/movimientos", ['type' => 'retiro', 'amount' => 1_000, 'reason' => 'Ajeno'])
            ->assertForbidden();
    }

    public function test_la_ficha_y_el_cierre_z_muestran_la_caja_y_los_movimientos(): void
    {
        $caja  = $this->caja('Principal', 'C1');
        $turno = $this->abrir($this->cajero, $caja, 10_000);
        $this->actingAs($this->cajero)->post("/cash/{$turno->id}/movimientos",
            ['type' => 'retiro', 'amount' => 2_000, 'reason' => 'Pago domiciliario']);

        $this->actingAs($this->cajero)->get("/cash/{$turno->id}")
            ->assertOk()->assertSee('C1 · Principal')->assertSee('Pago domiciliario');
        $this->actingAs($this->cajero)->get("/cash/{$turno->id}/cierre-z")
            ->assertOk()->assertSee('Retiros de efectivo');
        // Otro cajero ve la caja como ocupada y no se la ofrecen.
        $this->actingAs($this->otroCajero)->get('/cash/abrir')->assertOk()->assertSee('está ocupada');
        $this->actingAs($this->admin)->get('/cash_registers')->assertOk()->assertSee('Principal');
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function caja(string $nombre, ?string $codigo = null): CashRegister
    {
        return $this->trabajando(fn () => CashRegister::create(['name' => $nombre, 'code' => $codigo]));
    }

    private function abrir(User $quien, ?CashRegister $caja, float $base = 0): CashSession
    {
        $this->actingAs($quien)
            ->post('/cash', array_filter([
                'opening_amount' => $base, 'cash_register_id' => $caja?->id,
            ], fn ($v) => $v !== null))
            ->assertSessionHasNoErrors();

        return $this->trabajando(fn () => CashSession::where('user_id', $quien->id)->latest('id')->firstOrFail());
    }
}
