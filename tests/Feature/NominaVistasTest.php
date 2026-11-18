<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Empresa;
use App\Models\Payroll;
use App\Models\PayrollConcept;
use App\Models\Setting;
use App\Models\SocialBenefit;
use App\Models\Tienda;
use App\Models\User;
use App\Services\Nomina\ContratoService;
use App\Services\Nomina\NominaService;
use App\Services\Nomina\NovedadesService;
use App\Services\Nomina\PrestacionesService;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * RN-45 · Pantallas de nómina (H-F15) y validaciones movidas a Form Requests (H-F16).
 *
 * El módulo tenía servicios y controladores pero ninguna vista: toda página
 * respondía 500. Aquí se abre CADA pantalla con datos reales y se prueban los
 * formularios que antes validaban dentro del controlador.
 */
class NominaVistasTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private Employee $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('nomina-vistas');
        $this->admin = $this->empleado('Administrador');
        $this->ana   = $this->conContrato('Ana', 2_000_000);
    }

    /* ─────────────── Las pantallas abren ─────────────── */

    public function test_todas_las_pantallas_de_nomina_abren_con_datos(): void
    {
        // Arrange: una nómina aprobada, un borrador, novedades pendientes y una prestación.
        $aprobada = $this->liquidar('2026-09-01', '2026-09-30');
        $this->trabajando(fn () => app(NominaService::class)->aprobar($aprobada, $this->admin));
        $borrador = $this->liquidar('2026-10-01', '2026-10-31');

        $this->trabajando(function () {
            app(NovedadesService::class)->registrarHoras($this->ana, ['date' => '2026-11-02', 'hours' => 2, 'type' => 'diurna'], $this->admin);
            app(NovedadesService::class)->registrarAusencia($this->ana, ['type' => 'licencia_remunerada', 'start_date' => '2026-11-10', 'end_date' => '2026-11-11'], $this->admin);
            app(PrestacionesService::class)->prima(Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30'));
        });

        $superadmin = $this->empleado('Superadministrador');

        // Act + Assert: cada ruta GET del módulo responde 200 y pinta lo suyo.
        $paginas = [
            [route('payrolls.index'), [$aprobada->number, $borrador->number, 'Liquidar un período']],
            [route('payrolls.show', $borrador), ['Ana Prueba', 'Salario básico', 'Agregar un concepto', 'Eliminar borrador']],
            [route('payrolls.show', $aprobada), ['Desprendibles (ZIP)', 'PILA', 'Crear nómina de ajuste']],
            [route('payrolls.parametros'), ['Salario mínimo mensual', 'art. 114-1']],
            [route('overtime.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']), ['Pendientes de aprobación', 'Extra diurna', 'Registrar horas']],
            [route('leaves.index'), ['Solicitudes pendientes', 'Licencia remunerada', 'Registrar una novedad']],
            [route('contracts.index'), ['Ana Prueba', 'Término indefinido']],
            [route('benefits.index'), ['Prima de servicios', 'Liquidación final', 'Por pagar']],
            [route('payroll_concepts.index'), ['Salario básico', 'De ley']],
            [route('payroll_concepts.create'), ['Nuevo concepto', 'Cómo se calcula']],
            [route('employees.show', $this->ana), ['Contrato y nómina', 'Renovar']],
        ];

        foreach ($paginas as [$url, $textos]) {
            $respuesta = $this->actingAs($this->admin)->get($url);
            $respuesta->assertOk();
            foreach ($textos as $texto) {
                $respuesta->assertSee($texto);
            }
        }

        $concepto = $this->trabajando(fn () => PayrollConcept::where('is_system', true)->first());
        $this->actingAs($this->admin)->get(route('payroll_concepts.edit', $concepto))->assertOk()->assertSee('Concepto de ley');

        $this->actingAs($superadmin)->get(route('payroll_report.index', ['desde' => '2026-09-01', 'hasta' => '2026-10-31']))
            ->assertOk()->assertSee('Costo laboral mes a mes')->assertSee('Principal');
    }

    public function test_las_pantallas_abren_tambien_sin_ningun_dato(): void
    {
        // Borde: negocio recién creado, sin empleados, contratos ni nóminas.
        $this->trabajando(fn () => $this->ana->contracts()->delete());
        $this->trabajando(fn () => $this->ana->forceDelete());

        foreach (['payrolls.index', 'overtime.index', 'leaves.index', 'contracts.index', 'benefits.index', 'payroll_concepts.index'] as $ruta) {
            $this->actingAs($this->admin)->get(route($ruta))->assertOk();
        }
    }

    public function test_la_ficha_del_empleado_no_muestra_el_salario_a_quien_no_ve_nomina(): void
    {
        // RN-45: el Supervisor ve empleados pero no salarios.
        $this->actingAs($this->empleado('Supervisor'))->get(route('employees.show', $this->ana))
            ->assertOk()
            ->assertDontSee('Contrato y nómina')
            ->assertDontSee('2.000.000');
    }

    public function test_la_verificacion_publica_distingue_codigo_inexistente(): void
    {
        $this->get(route('nomina.verificar', 'ABCDEFGH12345678'))->assertNotFound()->assertSee('No se pudo verificar');
        $this->get('/verificar/nomina/corto')->assertNotFound();
    }

    /* ─────────────── Formularios (antes validaban en el controlador) ─────────────── */

    public function test_agregar_concepto_manual_a_un_borrador(): void
    {
        $borrador = $this->liquidar('2026-10-01', '2026-10-31');
        $bono = $this->trabajando(fn () => PayrollConcept::create(['code' => 'BONO', 'name' => 'Bono de ventas', 'type' => 'devengado', 'calculation' => 'manual', 'value' => 0, 'is_active' => true]));
        $netoAntes = (float) $borrador->total_net;

        $this->actingAs($this->admin)->post(route('payrolls.conceptos.store', $borrador), [
            'employee_id' => $this->ana->id, 'payroll_concept_id' => $bono->id, 'amount' => 100_000,
        ])->assertSessionHasNoErrors();

        $this->assertGreaterThan($netoAntes, (float) $this->trabajando(fn () => $borrador->fresh()->total_net));
    }

    public function test_agregar_concepto_rechaza_concepto_de_otro_negocio_empleado_ajeno_y_valores_invalidos(): void
    {
        $borrador = $this->liquidar('2026-10-01', '2026-10-31');

        // Un concepto de OTRO negocio (RN-26).
        $vecino = Empresa::create(['nombre' => 'Vecino', 'slug' => 'vecino-nomina', 'rubro' => 'general', 'estado' => 'activa']);
        $local  = Tienda::create(['empresa_id' => $vecino->id, 'nombre' => 'Ajena', 'slug' => 'ajena-nomina', 'es_principal' => true, 'activa' => true]);
        $ajeno  = Contexto::comoEmpresa($vecino->id, $local->id, fn () => PayrollConcept::create(['code' => 'AJENO', 'name' => 'Bono ajeno', 'type' => 'devengado', 'calculation' => 'manual', 'value' => 0, 'is_active' => true]));
        Contexto::olvidar();

        $otro = $this->nuevoEmpleado('Beto'); // del negocio, pero no está en la nómina

        $this->actingAs($this->admin)->post(route('payrolls.conceptos.store', $borrador), [
            'employee_id' => $this->ana->id, 'payroll_concept_id' => $ajeno->id, 'amount' => 50_000,
        ])->assertSessionHasErrors('payroll_concept_id');

        $this->actingAs($this->admin)->post(route('payrolls.conceptos.store', $borrador), [
            'employee_id' => $otro->id, 'payroll_concept_id' => $ajeno->id, 'amount' => 0, 'quantity' => -1,
        ])->assertSessionHasErrors(['employee_id', 'amount', 'quantity']);

        $this->assertSame(0, $this->trabajando(fn () => $borrador->items()->where('is_manual', true)->count()));
    }

    public function test_parametros_se_guardan_y_vacio_significa_automatico(): void
    {
        $this->actingAs($this->admin)->put(route('payrolls.parametros.guardar'), [
            'smlmv' => 1_800_000, 'auxilio_transporte' => 250_000, 'horas_mes' => '', 'recargo_dominical' => '', 'ratio_alerta' => 25, 'exoneracion_114' => '1',
        ])->assertSessionHasNoErrors();

        $ajustes = $this->trabajando(fn () => Setting::todos());
        $this->assertEquals(1_800_000, $ajustes['nomina.smlmv']);
        $this->assertSame('auto', $ajustes['nomina.horas_mes']);
        $this->assertSame('1', (string) $ajustes['nomina.exoneracion_114']);
    }

    public function test_parametros_rechazan_valores_fuera_de_rango_y_exigen_permiso(): void
    {
        $this->actingAs($this->admin)->put(route('payrolls.parametros.guardar'), [
            'smlmv' => 0, 'auxilio_transporte' => -1, 'horas_mes' => 500, 'recargo_dominical' => 300, 'ratio_alerta' => 0,
        ])->assertSessionHasErrors(['smlmv', 'auxilio_transporte', 'horas_mes', 'recargo_dominical', 'ratio_alerta']);

        $this->actingAs($this->empleado('Cajero'))->put(route('payrolls.parametros.guardar'), ['smlmv' => 1, 'auxilio_transporte' => 0, 'ratio_alerta' => 30])
            ->assertForbidden();
    }

    public function test_terminar_contrato_exige_fecha_y_motivo(): void
    {
        $contrato = $this->trabajando(fn () => $this->ana->contracts()->first());

        $this->actingAs($this->admin)->post(route('contracts.terminar', $contrato), ['motivo' => 'no'])
            ->assertSessionHasErrors(['ended_at', 'motivo']);

        $this->actingAs($this->admin)->post(route('contracts.terminar', $contrato), ['ended_at' => '2026-10-31', 'motivo' => 'Renuncia voluntaria'])
            ->assertSessionHasNoErrors();

        $this->assertSame('terminado', $this->trabajando(fn () => $contrato->fresh()->status));
    }

    public function test_pagar_prestaciones_no_acepta_ids_de_otro_negocio(): void
    {
        $this->trabajando(fn () => app(PrestacionesService::class)->prima(Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30')));
        $propia = $this->trabajando(fn () => SocialBenefit::first());

        // Una prestación de otro negocio, creada en su propio contexto.
        $vecino = Empresa::create(['nombre' => 'Vecino', 'slug' => 'vecino-prest', 'rubro' => 'general', 'estado' => 'activa']);
        $local  = Tienda::create(['empresa_id' => $vecino->id, 'nombre' => 'Ajena', 'slug' => 'ajena-prest', 'es_principal' => true, 'activa' => true]);
        $ajena  = Contexto::comoEmpresa($vecino->id, $local->id, function () use ($local) {
            $e = Employee::create(['code' => 'X-1', 'first_name' => 'Zoe', 'last_name' => 'Ajena', 'document_type' => 'CC', 'document_number' => '999', 'hire_date' => '2026-01-01', 'base_salary' => 0]);

            return SocialBenefit::create(['tienda_id' => $local->id, 'employee_id' => $e->id, 'type' => 'prima', 'period_start' => '2026-01-01', 'period_end' => '2026-06-30', 'days' => 180, 'base' => 2_000_000, 'amount' => 1_000_000, 'status' => SocialBenefit::CALCULADA]);
        });
        Contexto::olvidar();

        // Error: mezcla una propia con una ajena → no se paga ninguna.
        $this->actingAs($this->admin)->post(route('benefits.pagar'), ['ids' => [$propia->id, $ajena->id]])->assertSessionHasErrors('ids.1');
        $this->assertSame(SocialBenefit::CALCULADA, $this->trabajando(fn () => $propia->fresh()->status));

        // Borde: lista vacía.
        $this->actingAs($this->admin)->post(route('benefits.pagar'), ['ids' => []])->assertSessionHasErrors('ids');

        // Feliz.
        $this->actingAs($this->admin)->post(route('benefits.pagar'), ['ids' => [$propia->id]])->assertSessionHasNoErrors();
        $this->assertSame(SocialBenefit::PAGADA, $this->trabajando(fn () => $propia->fresh()->status));
    }

    public function test_el_motivo_de_un_ajuste_no_puede_pasar_de_200_caracteres(): void
    {
        $n = $this->liquidar('2026-10-01', '2026-10-31');
        $this->trabajando(fn () => app(NominaService::class)->aprobar($n, $this->admin));

        $this->actingAs($this->admin)->post(route('payrolls.ajuste', $n), ['notes' => str_repeat('x', 201)])->assertSessionHasErrors('notes');
        $this->actingAs($this->admin)->post(route('payrolls.ajuste', $n), ['notes' => 'Bono olvidado'])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_la_vista_previa_del_cupon_valida_sus_datos(): void
    {
        $cajero = $this->empleado('Cajero');

        $this->actingAs($cajero)->getJson(route('pos.cupon', ['codigo' => '', 'base' => -5]))->assertStatus(422)->assertJsonValidationErrors(['codigo', 'base']);
        $this->actingAs($cajero)->getJson(route('pos.cupon', ['codigo' => 'NOEXISTE', 'base' => 10_000]))->assertStatus(422)->assertJson(['ok' => false]);
    }

    /* ─────────────── Ayudas ─────────────── */

    private function nuevoEmpleado(string $nombre): Employee
    {
        static $n = 500;
        $n++;

        return $this->trabajando(fn () => Employee::create([
            'code' => 'EMP-' . $n, 'first_name' => $nombre, 'last_name' => 'Prueba', 'document_type' => 'CC',
            'document_number' => (string) (5000 + $n), 'hire_date' => '2026-01-01', 'base_salary' => 0, 'email' => strtolower($nombre) . $n . '@prueba.test',
        ]));
    }

    private function conContrato(string $nombre, float $salario): Employee
    {
        $e = $this->nuevoEmpleado($nombre);
        $this->trabajando(fn () => app(ContratoService::class)->crear($e, ['type' => 'indefinido', 'start_date' => '2026-01-01', 'base_salary' => $salario]));

        return $e->fresh();
    }

    private function liquidar(string $desde, string $hasta): Payroll
    {
        return $this->trabajando(fn () => app(NominaService::class)->liquidar(Carbon::parse($desde), Carbon::parse($hasta), $this->admin));
    }
}
