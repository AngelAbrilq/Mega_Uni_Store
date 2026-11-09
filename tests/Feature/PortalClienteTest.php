<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Empresa;
use App\Models\Tienda;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Portal B2C — Fase 1: contexto por slug y cuenta del cliente.
 *
 * Lo que de verdad se prueba es el aislamiento: el mismo correo en dos
 * negocios son dos cuentas, y la sesión de una no abre la otra.
 */
class PortalClienteTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $ferreteria;
    private Empresa $supermercado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ferreteria   = $this->negocio('ferreteria');
        $this->supermercado = $this->negocio('supermercado');
    }

    /* ─────────── Contexto ─────────── */

    public function test_la_tienda_de_un_negocio_activo_abre(): void
    {
        $this->get('/tienda/ferreteria')->assertOk()->assertSee('Ferreteria');
    }

    public function test_slug_inexistente_o_negocio_suspendido_da_404(): void
    {
        $this->supermercado->update(['estado' => 'suspendida']);

        $this->get('/tienda/no-existe')->assertNotFound();
        $this->get('/tienda/supermercado')->assertNotFound();
    }

    /* ─────────── Registro ─────────── */

    public function test_registro_crea_el_cliente_en_el_negocio_del_slug(): void
    {
        // Act
        $respuesta = $this->post('/tienda/ferreteria/registro', $this->datosRegistro());

        // Assert
        $respuesta->assertRedirect('/tienda/ferreteria/cuenta');
        $this->assertAuthenticated('cliente');

        $cliente = Customer::sinFiltroEmpresa()->where('email', 'ana@correo.co')->sole();
        $this->assertSame($this->ferreteria->id, $cliente->empresa_id);
        $this->assertTrue(Hash::check('clave1234', $cliente->password));
    }

    public function test_un_cliente_del_mostrador_reclama_su_registro_sin_duplicarse(): void
    {
        // Arrange — el cajero lo creó sin contraseña.
        $this->crearCliente($this->ferreteria, 'ana@correo.co', null, ['document_number' => '1023']);

        // Act
        $this->post('/tienda/ferreteria/registro', $this->datosRegistro())->assertRedirect();

        // Assert
        $filas = Customer::sinFiltroEmpresa()->where('email', 'ana@correo.co')->get();
        $this->assertCount(1, $filas);
        $this->assertSame('1023', $filas->first()->document_number, 'Se pisaron datos que ya tenía el cajero.');
        $this->assertNull($filas->first()->email_verified_at);
    }

    public function test_no_se_registra_dos_veces_el_mismo_correo_con_cuenta(): void
    {
        $this->crearCliente($this->ferreteria, 'ana@correo.co', 'otraClave99');

        $this->post('/tienda/ferreteria/registro', $this->datosRegistro())
            ->assertSessionHasErrors('email');

        $this->assertGuest('cliente');
    }

    public function test_registro_valida_campos_con_mensaje_por_campo(): void
    {
        $this->post('/tienda/ferreteria/registro', [
            'first_name' => '', 'email' => 'no-es-correo', 'password' => '123', 'password_confirmation' => '321',
        ] + $this->antispam())
            ->assertSessionHasErrors(['first_name', 'email', 'password', 'acepta_privacidad']);
    }

    /* ─────────── Ingreso y aislamiento ─────────── */

    public function test_el_cliente_ingresa_en_su_tienda(): void
    {
        $this->crearCliente($this->ferreteria, 'ana@correo.co', 'clave1234');

        $this->post('/tienda/ferreteria/ingresar', ['email' => 'ANA@correo.co ', 'password' => 'clave1234'])
            ->assertRedirect('/tienda/ferreteria/cuenta');

        $this->assertAuthenticated('cliente');
    }

    public function test_las_credenciales_de_un_negocio_no_abren_la_tienda_de_otro(): void
    {
        $this->crearCliente($this->ferreteria, 'ana@correo.co', 'clave1234');

        $this->post('/tienda/supermercado/ingresar', ['email' => 'ana@correo.co', 'password' => 'clave1234'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('cliente');
    }

    public function test_la_sesion_de_un_negocio_no_vale_en_la_tienda_de_otro(): void
    {
        // Arrange — ingresa de verdad en la ferretería (sesión real, no actingAs).
        $this->crearCliente($this->ferreteria, 'ana@correo.co', 'clave1234');
        $this->post('/tienda/ferreteria/ingresar', ['email' => 'ana@correo.co', 'password' => 'clave1234']);

        // Act + Assert
        $this->get('/tienda/supermercado/cuenta')->assertRedirect('/tienda/supermercado/ingresar');
        $this->get('/tienda/ferreteria/cuenta')->assertOk();
    }

    public function test_un_cliente_no_entra_al_panel_administrativo(): void
    {
        // actingAs(…, 'cliente') cambiaría el guard por defecto de la prueba;
        // por eso se ingresa por el formulario, como en la vida real.
        $this->crearCliente($this->ferreteria, 'ana@correo.co', 'clave1234');
        $this->post('/tienda/ferreteria/ingresar', ['email' => 'ana@correo.co', 'password' => 'clave1234']);

        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_cuenta_sin_sesion_manda_al_ingreso_de_esa_tienda(): void
    {
        $this->get('/tienda/ferreteria/cuenta')->assertRedirect('/tienda/ferreteria/ingresar');
    }

    public function test_salir_cierra_solo_la_sesion_del_cliente(): void
    {
        $ana = $this->crearCliente($this->ferreteria, 'ana@correo.co', 'clave1234');

        $this->actingAs($ana, 'cliente')
            ->post('/tienda/ferreteria/salir')
            ->assertRedirect('/tienda/ferreteria');

        $this->assertGuest('cliente');
    }

    /* ─────────── Ayudas ─────────── */

    private function negocio(string $slug): Empresa
    {
        $empresa = Empresa::create(['nombre' => ucfirst($slug), 'slug' => $slug, 'rubro' => 'general', 'estado' => 'activa']);

        Tienda::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Principal', 'slug' => 'principal',
            'es_principal' => true, 'activa' => true,
        ]);

        return $empresa;
    }

    private function crearCliente(Empresa $empresa, string $email, ?string $clave, array $extra = []): Customer
    {
        return Contexto::comoEmpresa($empresa->id, null, function () use ($email, $clave, $extra) {
            $cliente = new Customer(['first_name' => 'Ana', 'email' => $email] + $extra);
            $cliente->password = $clave;
            $cliente->save();

            return $cliente;
        });
    }

    /** @return array<string, mixed> */
    private function datosRegistro(): array
    {
        return [
            'first_name'            => 'Ana',
            'last_name'             => 'Pérez',
            'email'                 => 'ana@correo.co',
            'phone'                 => '300 123 4567',
            'password'              => 'clave1234',
            'password_confirmation' => 'clave1234',
            'acepta_privacidad'     => '1',
        ] + $this->antispam();
    }
}
