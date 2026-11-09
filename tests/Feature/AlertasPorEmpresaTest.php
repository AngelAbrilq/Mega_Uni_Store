<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Empresa;
use App\Models\Tienda;
use App\Models\User;
use App\Services\AlertService;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * H-01: la campana de alertas no se comparte entre negocios.
 *
 * Antes la caché tenía una sola clave global: el primer negocio que pedía
 * la campana se la «prestaba» a todos durante 120 s.
 */
class AlertasPorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_campana_de_un_negocio_no_aparece_en_otro(): void
    {
        // Arrange
        [$conAlerta, $tiendaA, $usuario] = $this->negocio('ferreteria');
        [$limpio, $tiendaB]              = $this->negocio('supermercado');

        Contexto::comoEmpresa($conAlerta->id, $tiendaA->id, fn () => $this->cajaOlvidada($usuario));

        // Act — primero el negocio con alerta, para que llene la caché.
        $deA = Contexto::comoEmpresa($conAlerta->id, $tiendaA->id, fn () => app(AlertService::class)->todas());
        $deB = Contexto::comoEmpresa($limpio->id, $tiendaB->id, fn () => app(AlertService::class)->todas());

        // Assert
        $this->assertSame(1, $deA['total']);
        $this->assertSame(0, $deB['total'], 'El supermercado vio la campana de la ferretería: la clave de caché no lleva la empresa.');
    }

    public function test_olvidar_refresca_la_campana_del_negocio(): void
    {
        // Arrange
        [$empresa, $tienda, $usuario] = $this->negocio('ferreteria');
        $servicio = app(AlertService::class);

        $antes = Contexto::comoEmpresa($empresa->id, $tienda->id, fn () => $servicio->todas());

        // Act
        Contexto::comoEmpresa($empresa->id, $tienda->id, function () use ($usuario, $empresa) {
            $this->cajaOlvidada($usuario);
            AlertService::olvidar($empresa->id);
        });

        $despues = Contexto::comoEmpresa($empresa->id, $tienda->id, fn () => $servicio->todas());

        // Assert
        $this->assertSame(0, $antes['total']);
        $this->assertSame(1, $despues['total']);
    }

    /* ─────────── Ayudas ─────────── */

    /** @return array{0:Empresa, 1:Tienda, 2:User} */
    private function negocio(string $slug): array
    {
        $empresa = Empresa::create(['nombre' => ucfirst($slug), 'slug' => $slug, 'rubro' => 'general', 'estado' => 'activa']);
        $tienda  = Tienda::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Principal', 'slug' => 'principal',
            'es_principal' => true, 'activa' => true,
        ]);
        $usuario = User::factory()->create();

        Contexto::olvidar();

        return [$empresa, $tienda, $usuario];
    }

    private function cajaOlvidada(User $usuario): void
    {
        $turno = CashSession::create(['user_id' => $usuario->id, 'opening_amount' => 0, 'status' => CashSession::ABIERTA]);
        $turno->forceFill(['opened_at' => now()->subDays(2)])->saveQuietly();
    }
}
