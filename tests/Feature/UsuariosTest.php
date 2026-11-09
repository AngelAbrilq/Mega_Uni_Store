<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Sale;
use App\Models\Tienda;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * Módulo de usuarios completo.
 *
 * SA-USR-001 · apellido, teléfono y estado.
 * SA-USR-002 · locales donde trabaja (solo los del negocio).
 * SA-USR-003 / VW-USR-001 · desactivar y dar de baja sin borrar.
 * H-F04      · borrar a un vendedor ya no rompe ni pierde su historial.
 */
class UsuariosTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('usuarios');
        $this->admin  = $this->empleado('Administrador');
        $this->cajero = $this->empleado('Cajero');
    }

    /* ═══════════════ H-F04 · Baja lógica ═══════════════ */

    public function test_dar_de_baja_a_un_vendedor_con_ventas_conserva_quien_vendio(): void
    {
        // Arrange
        $producto = $this->productoConExistencia('Lápiz', 1_000, 400, 10);
        $venta = $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $producto->id, 'quantity' => 1]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 1_000]],
            userId: $this->cajero->id,
        ));

        // Act
        $this->actingAs($this->admin)
            ->delete("/users/{$this->cajero->id}")
            ->assertRedirect(route('users.index'));

        // Assert
        $this->assertSoftDeleted('users', ['id' => $this->cajero->id, 'is_active' => false]);
        $this->trabajando(fn () => $this->assertSame(
            $this->cajero->id,
            Sale::find($venta->id)->user?->id,
            'La venta debe seguir mostrando a su vendedor aunque esté dado de baja.'
        ));
    }

    public function test_un_usuario_dado_de_baja_no_puede_iniciar_sesion(): void
    {
        $this->cajero->delete();

        $this->post('/login', ['email' => $this->cajero->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /* ═══════════════ SA-USR-003 · Desactivar ═══════════════ */

    public function test_un_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $this->cajero->update(['is_active' => false]);

        $this->post('/login', ['email' => $this->cajero->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Tu usuario está desactivado. Habla con el administrador del negocio.']);

        $this->assertGuest();
    }

    public function test_con_clave_incorrecta_no_revela_que_la_cuenta_esta_desactivada(): void
    {
        $this->cajero->update(['is_active' => false]);

        $this->post('/login', ['email' => $this->cajero->email, 'password' => 'otra-clave'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no coinciden con nuestros registros.']);
    }

    public function test_si_lo_desactivan_con_la_sesion_abierta_sale_en_la_siguiente_peticion(): void
    {
        $this->cajero->update(['is_active' => false]);

        $this->actingAs($this->cajero->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_nadie_puede_desactivarse_a_si_mismo(): void
    {
        $this->actingAs($this->admin)
            ->put("/users/{$this->admin->id}", $this->datos([
                'name' => $this->admin->name, 'email' => $this->admin->email, 'is_active' => '0',
                'roles' => ['Administrador'],
            ]))
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_el_listado_filtra_por_estado(): void
    {
        $this->cajero->update(['is_active' => false]);
        $activo = $this->empleado('Vendedor');

        $this->actingAs($this->admin)
            ->get('/users?estado=inactivos')
            ->assertOk()
            ->assertSee($this->cajero->email)
            ->assertDontSee($activo->email);
    }

    /* ═══════════════ SA-USR-001 · Perfil ═══════════════ */

    public function test_guarda_apellidos_telefono_y_estado(): void
    {
        $this->actingAs($this->admin)
            ->post('/users', $this->datos(['last_name' => 'Gómez Ruiz', 'phone' => '+57 300 123 4567', 'is_active' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'nueva@ejemplo.com', 'last_name' => 'Gómez Ruiz',
            'phone' => '+57 300 123 4567', 'is_active' => true,
        ]);
    }

    public function test_rechaza_un_telefono_con_letras(): void
    {
        $this->actingAs($this->admin)
            ->from('/users/create')
            ->post('/users', $this->datos(['phone' => 'llámame']))
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'nueva@ejemplo.com']);
    }

    /* ═══════════════ SA-USR-002 · Locales ═══════════════ */

    public function test_asigna_los_locales_del_negocio_elegidos(): void
    {
        $sucursal = $this->otraTienda($this->empresa, 'sucursal-usuarios');

        $this->actingAs($this->admin)
            ->post('/users', $this->datos(['tiendas' => [$sucursal->id], 'tiendas_presentes' => '1']))
            ->assertSessionHasNoErrors();

        $nueva = User::where('email', 'nueva@ejemplo.com')->firstOrFail();
        $this->assertSame([$sucursal->id], $nueva->tiendas()->pluck('tiendas.id')->all());
    }

    public function test_rechaza_un_local_de_otro_negocio(): void
    {
        $vecino = Empresa::create([
            'nombre' => 'Vecino', 'slug' => 'vecino-usuarios', 'rubro' => 'general', 'estado' => 'activa',
        ]);
        $ajena = $this->otraTienda($vecino, 'ajena-usuarios');

        $this->actingAs($this->admin)
            ->from('/users/create')
            ->post('/users', $this->datos(['tiendas' => [$ajena->id], 'tiendas_presentes' => '1']))
            ->assertSessionHasErrors('tiendas.0');
    }

    public function test_desmarcar_todos_los_locales_lo_deja_trabajar_en_todos(): void
    {
        // Arrange: el cajero está atado al local principal (empleado()).
        $this->otraTienda($this->empresa, 'segunda-usuarios');

        // Act
        $this->actingAs($this->admin)
            ->put("/users/{$this->cajero->id}", $this->datos([
                'name' => $this->cajero->name, 'email' => $this->cajero->email,
                'password' => '', 'password_confirmation' => '', 'tiendas_presentes' => '1',
            ]))
            ->assertSessionHasNoErrors();

        // Assert: sin asignación explícita → todos los locales activos.
        $this->assertCount(0, $this->cajero->tiendas()->get());
        $this->assertCount(2, $this->cajero->tiendasEn($this->empresa->id));
    }

    /* ═══════════════ Ayudas ═══════════════ */

    /** @param array<string, mixed> $cambios */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'name'                  => 'Nueva',
            'email'                 => 'nueva@ejemplo.com',
            'password'              => 'ClaveLarga123',
            'password_confirmation' => 'ClaveLarga123',
            'roles'                 => ['Cajero'],
        ], $cambios);
    }

    private function otraTienda(Empresa $empresa, string $slug): Tienda
    {
        return Tienda::create([
            'empresa_id' => $empresa->id, 'nombre' => ucfirst($slug),
            'slug' => $slug, 'es_principal' => false, 'activa' => true,
        ]);
    }
}
