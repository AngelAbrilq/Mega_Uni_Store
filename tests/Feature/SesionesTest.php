<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * TBL-SES-001 · Sesiones con dispositivo, IP y expiración (RN-41).
 *   1 · Al entrar se registra la sesión (hash, IP, dispositivo, expires_at).
 *   2 · Cada petición valida activa y vigente.
 *   3 · Vencida → sale y queda is_active = 0.
 *   4 · Varios dispositivos → registros independientes.
 */
class SesionesTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private const WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
    private const IPHONE  = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private User $admin;
    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('sesiones');
        $this->admin  = $this->empleado('Administrador');
        $this->cajero = $this->empleado('Cajero');
    }

    /* ═══════════════ 1 · Crear al entrar ═══════════════ */

    public function test_al_entrar_se_registra_la_sesion_con_dispositivo_ip_y_vencimiento(): void
    {
        // Act: ingresa y abre el panel
        $this->withHeader('User-Agent', self::WINDOWS)
            ->post('/login', ['email' => $this->cajero->email, 'password' => 'password'])
            ->assertRedirect();
        $this->withHeader('User-Agent', self::WINDOWS)->get('/dashboard')->assertOk();

        // Assert
        $s = UserSession::where('user_id', $this->cajero->id)->sole();
        $this->assertTrue($s->is_active);
        $this->assertSame('Chrome en Windows', $s->device);
        $this->assertSame('escritorio', $s->device_type);
        $this->assertSame('127.0.0.1', $s->ip_address);
        $this->assertEqualsWithDelta(now()->addMinutes(config('session.lifetime'))->timestamp, $s->expires_at->timestamp, 5);
        $this->assertSame(UserSession::hashDe(session()->getId()), $s->token_hash, 'Se guarda el hash, nunca el identificador en claro.');
        $this->assertNotSame(session()->getId(), $s->token_hash);
    }

    public function test_varias_peticiones_no_duplican_la_sesion(): void
    {
        $this->actingAs($this->cajero)->get('/dashboard');
        $this->actingAs($this->cajero)->get('/profile');
        $this->actingAs($this->cajero)->get('/dashboard');

        $this->assertSame(1, UserSession::where('user_id', $this->cajero->id)->count());
    }

    /* ═══════════════ 2 · Validar ═══════════════ */

    public function test_una_sesion_cerrada_a_distancia_sale_en_la_siguiente_peticion(): void
    {
        $this->actingAs($this->cajero)->get('/dashboard')->assertOk();
        UserSession::where('user_id', $this->cajero->id)->update(['is_active' => false, 'end_reason' => UserSession::REVOCADA]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /* ═══════════════ 3 · Expirar ═══════════════ */

    public function test_una_sesion_vencida_sale_y_queda_marcada(): void
    {
        $this->actingAs($this->cajero)->get('/dashboard');
        $this->travel(config('session.lifetime') + 5)->minutes();

        $this->get('/dashboard')->assertRedirect(route('login'));

        $s = UserSession::where('user_id', $this->cajero->id)->sole();
        $this->assertFalse($s->is_active);
        $this->assertSame(UserSession::VENCIDA, $s->end_reason);
    }

    public function test_la_actividad_corre_el_vencimiento(): void
    {
        $this->actingAs($this->cajero)->get('/dashboard');
        $this->travel(config('session.lifetime') - 10)->minutes();
        $this->get('/dashboard')->assertOk();

        $this->travel(30)->minutes();
        $this->get('/dashboard')->assertOk();
    }

    public function test_el_comando_marca_las_vencidas(): void
    {
        $this->sesionEn($this->cajero, 'otro', expira: now()->subMinute());

        $this->artisan('mus:expirar-sesiones')->assertSuccessful();

        $this->assertSame(UserSession::VENCIDA, UserSession::where('token_hash', hash('sha256', 'otro'))->value('end_reason'));
    }

    /* ═══════════════ 4 · Varios dispositivos ═══════════════ */

    public function test_cada_dispositivo_es_una_sesion_y_se_pueden_cerrar_las_demas(): void
    {
        // Arrange: el celular ya tenía una sesión abierta
        $celular = $this->sesionEn($this->cajero, 'sesion-del-celular');
        $this->actingAs($this->cajero)->withHeader('User-Agent', self::WINDOWS)->get('/dashboard');
        $this->assertSame(2, UserSession::where('user_id', $this->cajero->id)->where('is_active', true)->count());

        // Act
        $this->post('/sesiones/cerrar-otras')->assertSessionHas('success');

        // Assert: el celular quedó cerrado y esta sigue viva
        $this->assertSame(UserSession::REVOCADA, $celular->fresh()->end_reason);
        $this->get('/dashboard')->assertOk();
    }

    public function test_cerrar_sesion_la_deja_cerrada(): void
    {
        $this->actingAs($this->cajero)->get('/dashboard');
        $this->post('/logout');

        $s = UserSession::where('user_id', $this->cajero->id)->sole();
        $this->assertFalse($s->is_active);
        $this->assertSame(UserSession::CERRADA, $s->end_reason);
    }

    /* ═══════════════ Quién cierra qué ═══════════════ */

    public function test_nadie_cierra_la_sesion_de_otro_desde_su_perfil(): void
    {
        $ajena = $this->sesionEn($this->admin, 'sesion-del-admin');

        $this->actingAs($this->cajero)->delete("/sesiones/{$ajena->id}")->assertNotFound();
        $this->assertTrue($ajena->fresh()->is_active);
    }

    public function test_el_administrador_cierra_las_sesiones_de_un_cajero_pero_no_al_reves(): void
    {
        $delCajero = $this->sesionEn($this->cajero, 'sesion-cajero', ua: self::IPHONE);
        $delAdmin  = $this->sesionEn($this->admin, 'sesion-admin');

        $this->actingAs($this->cajero)->post("/users/{$this->admin->id}/sesiones/cerrar")->assertForbidden();
        $this->assertTrue($delAdmin->fresh()->is_active);

        $this->actingAs($this->admin)->post("/users/{$this->cajero->id}/sesiones/cerrar")->assertSessionHas('success');
        $this->assertFalse($delCajero->fresh()->is_active);
        $this->assertSame($this->admin->id, $delCajero->fresh()->ended_by);
    }

    public function test_el_perfil_y_la_ficha_muestran_los_dispositivos(): void
    {
        $this->sesionEn($this->cajero, 'sesion-celular', ua: self::IPHONE);

        $this->actingAs($this->cajero)->withHeader('User-Agent', self::WINDOWS)->get('/profile')
            ->assertOk()->assertSee('Chrome en Windows')->assertSee('Safari en iPhone')->assertSee('Esta sesión');

        $this->actingAs($this->admin)->get("/users/{$this->cajero->id}")
            ->assertOk()->assertSee('Safari en iPhone')->assertSee('Cerrar todas sus sesiones');
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function sesionEn(User $usuario, string $id, ?\DateTimeInterface $expira = null, string $ua = self::WINDOWS): UserSession
    {
        [$dispositivo, $tipo] = \App\Support\Dispositivo::describir($ua);

        return UserSession::create([
            'user_id' => $usuario->id, 'token_hash' => hash('sha256', $id),
            'ip_address' => '10.0.0.7', 'user_agent' => $ua, 'device' => $dispositivo, 'device_type' => $tipo,
            'last_activity_at' => now(), 'expires_at' => $expira ?? now()->addHour(), 'is_active' => true,
        ]);
    }
}
