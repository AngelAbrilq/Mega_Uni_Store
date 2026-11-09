<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Contexto;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Los roles pueden ser del sistema o de un negocio.
 *
 * ── Lo que esta prueba tiene que garantizar, por encima de todo ──
 *
 * Que NADIE pierda un permiso.
 *
 * Cambiar cómo se busca un rol es de esas cosas que no fallan al migrar:
 * la migración sale verde, el sistema arranca, y el lunes el cajero entra
 * y no puede vender. Para cuando alguien llama, ya se perdió la mañana.
 *
 * Por eso la primera prueba de este archivo no es la del aislamiento sino
 * la de la continuidad: los siete roles siguen ahí, con sus mismos
 * permisos, y la gente que los tenía los sigue teniendo.
 */
class RolesPorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que cada rol del sistema tiene que seguir pudiendo hacer. */
    private const MINIMOS = [
        'Superadministrador' => ['configuracion.editar', 'usuarios.crear', 'ventas.crear'],
        'Administrador'      => ['usuarios.crear', 'productos.editar', 'ventas.anular'],
        'Supervisor'         => ['reportes.ver', 'inventario.ajustar'],
        'Cajero'             => ['ventas.crear', 'caja.abrir', 'caja.cerrar'],
        'Vendedor'           => ['ventas.crear', 'clientes.crear'],
        'Bodeguero'          => ['inventario.ajustar', 'compras.recibir'],
        'Reportero'          => ['reportes.ver', 'productos.ver'],
    ];

    private Empresa $ferreteria;
    private Empresa $peluqueria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->ferreteria = $this->montarNegocio('Ferretería El Tornillo', 'ferreteria-roles');
        $this->peluqueria = $this->montarNegocio('Peluquería Estilo', 'peluqueria-roles');

        Contexto::olvidar();
    }

    /* ═══════════════ 1. Nadie pierde nada ═══════════════ */

    public function test_los_siete_roles_del_sistema_siguen_estando(): void
    {
        foreach (array_keys(self::MINIMOS) as $nombre) {
            $rol = Rol::where('name', $nombre)->whereNull('empresa_id')->first();

            $this->assertNotNull($rol, "Desapareció el rol del sistema «{$nombre}».");
            $this->assertTrue($rol->esDelSistema(), "«{$nombre}» dejó de ser un rol del sistema.");
        }
    }

    public function test_ningun_rol_del_sistema_perdio_permisos(): void
    {
        foreach (self::MINIMOS as $nombre => $permisos) {
            $rol = Rol::where('name', $nombre)->whereNull('empresa_id')->firstOrFail();

            foreach ($permisos as $permiso) {
                $this->assertTrue(
                    $rol->hasPermissionTo($permiso),
                    "El rol «{$nombre}» perdió el permiso «{$permiso}»."
                );
            }
        }
    }

    /**
     * La prueba de verdad: una persona con un rol asignado ANTES del cambio
     * sigue pudiendo lo mismo DESPUÉS.
     */
    public function test_un_usuario_conserva_sus_permisos_dentro_de_su_negocio(): void
    {
        $cajero = $this->usuarioDe($this->ferreteria, 'Cajero');

        Contexto::comoEmpresa($this->ferreteria->id, null, function () use ($cajero) {
            $this->assertTrue($cajero->hasRole('Cajero'));
            $this->assertTrue($cajero->can('ventas.crear'));
            $this->assertTrue($cajero->can('caja.abrir'));
            $this->assertFalse($cajero->can('configuracion.editar'));
        });
    }

    /** Y también parado en el otro negocio: los del sistema no dependen de dónde estés. */
    public function test_un_rol_del_sistema_vale_en_cualquier_negocio(): void
    {
        $cajero = $this->usuarioDe($this->ferreteria, 'Cajero');

        Contexto::comoEmpresa($this->peluqueria->id, null, function () use ($cajero) {
            $this->assertTrue(
                $cajero->hasRole('Cajero'),
                'Un rol del sistema dejó de reconocerse al cambiar de negocio.'
            );
        });
    }

    /* ═══════════════ 2. Los roles propios no se escapan ═══════════════ */

    public function test_un_negocio_no_ve_los_roles_propios_del_otro(): void
    {
        $this->rolPropio($this->peluqueria, 'Estilista', ['ventas.crear', 'clientes.crear']);

        $desdeLaFerreteria = Contexto::comoEmpresa(
            $this->ferreteria->id,
            null,
            fn () => Rol::visibles()->pluck('name')->all()
        );

        $this->assertNotContains(
            'Estilista',
            $desdeLaFerreteria,
            'El rol propio de la peluquería le apareció a la ferretería.'
        );

        $this->assertContains('Cajero', $desdeLaFerreteria, 'Se perdieron los roles del sistema.');
    }

    public function test_cada_negocio_si_ve_el_suyo(): void
    {
        $this->rolPropio($this->peluqueria, 'Estilista', ['ventas.crear']);

        $suyos = Contexto::comoEmpresa(
            $this->peluqueria->id,
            null,
            fn () => Rol::visibles()->pluck('name')->all()
        );

        $this->assertContains('Estilista', $suyos);
        $this->assertContains('Cajero', $suyos, 'Los roles del sistema tienen que seguir estando.');
    }

    /**
     * Dos negocios pueden llamar igual a su rol.
     *
     * Es el caso que motivó todo: dos peluquerías que ni se conocen no
     * tienen por qué pelearse por la palabra «Estilista».
     */
    public function test_dos_negocios_pueden_usar_el_mismo_nombre(): void
    {
        $unoA = $this->rolPropio($this->peluqueria, 'Estilista', ['ventas.crear']);
        $unoB = $this->rolPropio($this->ferreteria, 'Estilista', ['inventario.ver']);

        $this->assertNotSame($unoA->id, $unoB->id, 'Se reutilizó el rol del otro negocio en vez de crear uno.');
    }

    /**
     * Y buscar por nombre trae el propio, no el del vecino.
     *
     * Este es el punto exacto donde se rompería el aislamiento sin darse
     * cuenta: `assignRole('Estilista')` es una cadena de texto, y sin
     * contexto no hay forma de saber cuál de los dos es.
     */
    public function test_buscar_por_nombre_trae_el_del_negocio_activo(): void
    {
        $deLaPeluqueria = $this->rolPropio($this->peluqueria, 'Estilista', ['ventas.crear']);
        $deLaFerreteria = $this->rolPropio($this->ferreteria, 'Estilista', ['inventario.ver']);

        $encontrado = Contexto::comoEmpresa(
            $this->ferreteria->id,
            null,
            fn () => Rol::findByName('Estilista')
        );

        $this->assertSame(
            $deLaFerreteria->id,
            $encontrado->id,
            'Buscando «Estilista» desde la ferretería salió el rol de la peluquería.'
        );

        $this->assertNotSame($deLaPeluqueria->id, $encontrado->id);
    }

    /** Asignar un rol por nombre asigna el del negocio en el que se está. */
    public function test_asignar_por_nombre_asigna_el_del_negocio_activo(): void
    {
        $this->rolPropio($this->peluqueria, 'Estilista', ['ventas.crear']);
        $deLaFerreteria = $this->rolPropio($this->ferreteria, 'Estilista', ['inventario.ver']);

        $empleado = $this->usuarioDe($this->ferreteria, 'Vendedor');

        Contexto::comoEmpresa($this->ferreteria->id, null, function () use ($empleado, $deLaFerreteria) {
            $empleado->assignRole('Estilista');

            $this->assertTrue(
                $empleado->fresh()->roles->contains('id', $deLaFerreteria->id),
                'Se asignó el «Estilista» de la peluquería a un empleado de la ferretería.'
            );
        });
    }

    /* ═══════════════ 3. Por la puerta web ═══════════════ */

    /** El desplegable de roles del formulario no ofrece los del vecino. */
    public function test_la_pantalla_de_usuarios_no_ofrece_roles_ajenos(): void
    {
        $this->rolPropio($this->peluqueria, 'Estilista', ['ventas.crear']);

        $jefe = $this->usuarioDe($this->ferreteria, 'Superadministrador');

        $this->actingAs($jefe)->get('/users/create')
            ->assertOk()
            ->assertDontSee('Estilista')
            ->assertSee('Cajero');
    }

    /** Y mandarlo a mano tampoco sirve. */
    public function test_no_se_puede_asignar_a_mano_un_rol_ajeno(): void
    {
        $this->rolPropio($this->peluqueria, 'Estilista', ['ventas.crear']);

        $jefe = $this->usuarioDe($this->ferreteria, 'Superadministrador');

        $this->actingAs($jefe)
            ->from('/users/create')
            ->post('/users', [
                'name'                  => 'Colado',
                'email'                 => 'colado@ejemplo.com',
                'password'              => 'ClaveLarga123',
                'password_confirmation' => 'ClaveLarga123',
                'roles'                 => ['Estilista'],
            ])
            ->assertSessionHasErrors('roles.0');
    }

    /* ═══════════════ Montaje ═══════════════ */

    private function montarNegocio(string $nombre, string $slug): Empresa
    {
        $empresa = Empresa::create([
            'nombre' => $nombre,
            'slug'   => $slug,
            'rubro'  => 'general',
            'estado' => 'activa',
        ]);

        Tienda::create([
            'empresa_id'   => $empresa->id,
            'nombre'       => 'Principal',
            'slug'         => 'principal-' . $slug,
            'es_principal' => true,
            'activa'       => true,
        ]);

        return $empresa;
    }

    private function usuarioDe(Empresa $empresa, string $rol): User
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->sync([$empresa->id => ['es_dueno' => $rol === 'Superadministrador']]);

        Contexto::comoEmpresa($empresa->id, null, fn () => $usuario->syncRoles([$rol]));

        return $usuario->fresh();
    }

    /**
     * Crea un rol propio de un negocio, con sus permisos.
     *
     * Se crea DENTRO de `comoEmpresa` a propósito: así es como va a pasar
     * en el sistema —el rol lo crea alguien parado en su negocio— y así la
     * comprobación de «ya existe uno con ese nombre» mira solo los del
     * sistema y los suyos, que es justo lo que permite que dos negocios
     * usen la misma palabra.
     */
    private function rolPropio(Empresa $empresa, string $nombre, array $permisos): Rol
    {
        foreach ($permisos as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $rol = Contexto::comoEmpresa($empresa->id, null, fn () => Rol::create([
            'name'       => $nombre,
            'guard_name' => 'web',
            'empresa_id' => $empresa->id,
        ]));

        $rol->syncPermissions($permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $rol;
    }
}
