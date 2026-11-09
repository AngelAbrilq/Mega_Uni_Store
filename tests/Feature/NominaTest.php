<?php

namespace Tests\Feature;

use App\Exceptions\NominaInmutable;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\LeaveRequest;
use App\Models\OvertimeEntry;
use App\Models\Payroll;
use App\Models\PayrollConcept;
use App\Models\PayrollEmployee;
use App\Models\SocialBenefit;
use App\Models\User;
use App\Services\Nomina\ContratoService;
use App\Services\Nomina\NominaService;
use App\Services\Nomina\NovedadesService;
use App\Services\Nomina\PrestacionesService;
use App\Support\Nomina\ReglasLaborales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * Nómina (RN-45 · NR-NOM-001…010). Valores legales 2026:
 * SMLMV $1.750.905 · auxilio $249.095 · 210 h/mes desde el 15-jul-2026.
 */
class NominaTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('nomina');
        $this->admin = $this->empleado('Administrador');
    }

    /* ═══════════════ NR-NOM-001 · Contratos ═══════════════ */

    public function test_contrato_valida_smlmv_y_fecha_fin_y_renueva_con_trazabilidad(): void
    {
        $e = $this->nuevoEmpleado('Ana');

        $this->actingAs($this->admin)->post(route('contracts.store', $e), [
            'type' => 'indefinido', 'start_date' => '2026-01-01', 'base_salary' => 1_500_000,
        ])->assertSessionHasErrors(['base_salary' => 'El salario no puede ser menor al SMLV ($1.750.905). Ajusta el salario.']);

        $this->actingAs($this->admin)->post(route('contracts.store', $e), [
            'type' => 'fijo', 'start_date' => '2026-01-01', 'base_salary' => 2_000_000,
        ])->assertSessionHasErrors(['end_date' => 'Los contratos a término fijo requieren fecha de finalización.']);

        $this->actingAs($this->admin)->post(route('contracts.store', $e), [
            'type' => 'fijo', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'base_salary' => 2_000_000, 'arl_class' => 2,
        ])->assertSessionHasNoErrors();

        $primero = $this->trabajando(fn () => EmploymentContract::sole());
        $this->assertSame($this->tienda->id, $primero->tienda_id);

        $this->actingAs($this->admin)->post(route('contracts.renovar', $primero), [
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'base_salary' => 2_200_000,
        ])->assertSessionHasNoErrors();

        $historia = $this->trabajando(fn () => $e->fresh()->contracts()->get());
        $this->assertCount(2, $historia);
        $this->assertSame(EmploymentContract::ACTIVO, $historia[0]->status);
        $this->assertSame($primero->id, $historia[0]->renewed_from_id);
        $this->assertSame(EmploymentContract::FINALIZADO, $historia[1]->status);
        $this->assertSame('2026-06-30', $historia[1]->ended_at->toDateString());
        $this->assertSame(2_200_000.0, (float) $this->trabajando(fn () => $e->fresh()->base_salary));
    }

    public function test_un_solo_contrato_activo_por_empleado(): void
    {
        $e = $this->conContrato('Beto', 2_000_000);

        $this->expectException(ValidationException::class);
        $this->trabajando(fn () => app(ContratoService::class)->crear($e, ['type' => 'indefinido', 'start_date' => '2026-03-01', 'base_salary' => 2_000_000]));
    }

    /* ═══════════════ NR-NOM-002 · Conceptos ═══════════════ */

    public function test_conceptos_de_ley_se_crean_solos_y_los_nuevos_no_repiten_nombre(): void
    {
        $this->actingAs($this->admin)->get(route('payroll_concepts.index'))->assertOk()->assertSee('Salario básico')->assertSee('Pensión (4 %)');
        $this->assertSame(count(PayrollConcept::DEL_SISTEMA), $this->trabajando(fn () => PayrollConcept::where('is_system', true)->count()));

        $this->actingAs($this->admin)->post(route('payroll_concepts.store'), [
            'name' => 'Salario básico', 'code' => 'OTRO', 'type' => 'devengado', 'calculation' => 'fijo', 'value' => 1,
        ])->assertSessionHasErrors(['name' => 'Ya existe un concepto con ese nombre.']);

        $this->actingAs($this->admin)->post(route('payroll_concepts.store'), [
            'name' => 'Auxilio de alimentación', 'code' => 'ALIM', 'type' => 'devengado', 'calculation' => 'pct_smlmv',
            'value' => 11.7, 'is_mandatory' => 1, 'is_salary' => 0,
        ])->assertSessionHasNoErrors();

        // E3: «SMLV * 0.117» se aplica solo en la liquidación.
        $this->conContrato('Caro', 2_000_000);
        $n = $this->liquidarOctubre();
        $item = $n->employees->first()->items->firstWhere('code', 'ALIM');
        $this->assertEquals(round(1_750_905 * 0.117), (float) $item->amount);
    }

    public function test_concepto_desactivado_no_entra_en_nuevas_liquidaciones(): void
    {
        $c = $this->trabajando(fn () => PayrollConcept::create(['code' => 'BONO', 'name' => 'Bono fijo', 'type' => 'devengado', 'calculation' => 'fijo', 'value' => 50_000, 'is_mandatory' => true]));
        $this->actingAs($this->admin)->patch(route('payroll_concepts.estado', $c))->assertSessionHasNoErrors();

        $this->conContrato('Dani', 2_000_000);
        $this->assertNull($this->liquidarOctubre()->employees->first()->items->firstWhere('code', 'BONO'));
    }

    /* ═══════════════ NR-NOM-003/004 · Liquidación y aportes ═══════════════ */

    public function test_liquida_el_mes_completo_con_auxilio_deducciones_y_aportes(): void
    {
        $this->conContrato('Eva', 2_000_000);

        $this->actingAs($this->admin)->post(route('payrolls.store'), ['period_start' => '2026-10-01', 'period_end' => '2026-10-31'])
            ->assertSessionHasNoErrors();

        $n = $this->trabajando(fn () => Payroll::with('employees.items', 'employees.contribution')->sole());
        $f = $n->employees->sole();

        $this->assertSame(Payroll::BORRADOR, $n->status);
        $this->assertSame(30, $f->days_worked);
        $this->assertEquals(2_000_000, $f->items->firstWhere('code', 'SALARIO')->amount);
        $this->assertEquals(249_095, $f->items->firstWhere('code', 'AUXTRANS')->amount);
        $this->assertEquals(80_000, $f->items->firstWhere('code', 'SALUD')->amount);
        $this->assertEquals(80_000, $f->items->firstWhere('code', 'PENSION')->amount);
        $this->assertEquals(2_000_000, $f->ibc);
        $this->assertEquals(2_089_095, $f->net);

        $a = $f->contribution;
        $this->assertEquals(170_000, $a->health_employer);
        $this->assertEquals(240_000, $a->pension_employer);
        $this->assertEquals(10_440, $a->arl);
        $this->assertEquals(80_000, $a->ccf);
        $this->assertEquals(60_000, $a->icbf);
        $this->assertEquals(40_000, $a->sena);
        $this->assertEquals(600_440, $n->total_employer);
        $this->assertSame(1, $n->employees_count);
    }

    public function test_proporcional_por_ingreso_y_descuento_de_ausencias(): void
    {
        $this->conContrato('Fabio', 3_000_000, '2026-10-16');      // 15 días comerciales
        $g = $this->conContrato('Gina', 3_000_000);
        $this->trabajando(fn () => app(NovedadesService::class)->registrarAusencia($g,
            ['type' => 'injustificada', 'start_date' => '2026-10-06', 'end_date' => '2026-10-08'], $this->admin));

        $n = $this->liquidarOctubre();
        $fabio = $n->employees->firstWhere('employee.first_name', 'Fabio');
        $gina  = $n->employees->firstWhere('employee.first_name', 'Gina');

        $this->assertSame(15, $fabio->days_worked);
        $this->assertEquals(1_500_000, $fabio->items->firstWhere('code', 'SALARIO')->amount);

        $this->assertSame(3, $gina->absence_days);
        $this->assertEquals(300_000, $gina->items->firstWhere('code', 'AUSENCIA')->amount); // (3.000.000 / 30) × 3
        $this->assertEquals(2_700_000, $gina->ibc);
    }

    public function test_incluye_solo_horas_extra_aprobadas_del_periodo(): void
    {
        $e = $this->conContrato('Hugo', 2_100_000);
        $svc = app(NovedadesService::class);

        [$ok, $pend, $fuera] = $this->trabajando(fn () => [
            $svc->registrarHoras($e, ['date' => '2026-10-10', 'hours' => 2, 'type' => 'nocturna'], $this->admin),
            $svc->registrarHoras($e, ['date' => '2026-10-11', 'hours' => 3, 'type' => 'diurna'], $this->admin),
            $svc->registrarHoras($e, ['date' => '2026-09-30', 'hours' => 1, 'type' => 'diurna'], $this->admin),
        ]);

        // NR-NOM-005 E4: hora = salario / 210 (Ley 2101, desde 15-jul-2026) × 1,75
        $this->assertEquals(10_000, $ok->hour_value);
        $this->assertEquals(35_000, $ok->amount);

        $this->actingAs($this->admin)->post(route('overtime.aprobar', $ok))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('overtime.aprobar', $fuera))->assertSessionHasNoErrors();

        $n = $this->liquidarOctubre();
        $hextra = $n->employees->sole()->items->where('code', 'HEXTRA');

        $this->assertCount(1, $hextra);
        $this->assertEquals(35_000, $hextra->first()->amount);
        $this->assertSame($n->id, $ok->fresh()->payroll_id);
        $this->assertNull($pend->fresh()->payroll_id);
        $this->assertNull($fuera->fresh()->payroll_id); // NR-INT-012 E4: queda para su período
    }

    public function test_recargo_dominical_sigue_el_calendario_de_la_ley_2466(): void
    {
        $this->assertSame(0.80, ReglasLaborales::recargoDominical(Carbon::parse('2026-06-15')));
        $this->assertSame(0.90, ReglasLaborales::recargoDominical(Carbon::parse('2026-07-01')));
        $this->assertSame(2.15, ReglasLaborales::factor('dominical_diurna', Carbon::parse('2026-10-04')));
        $this->assertSame(220, ReglasLaborales::horasMes(Carbon::parse('2026-07-14')));
        $this->assertSame(210, ReglasLaborales::horasMes(Carbon::parse('2026-07-15')));
    }

    public function test_ibc_se_limita_a_25_smlmv(): void
    {
        $this->conContrato('Iván', 50_000_000);
        $a = $this->liquidarOctubre()->employees->sole()->contribution;

        $this->assertTrue($a->ibc_capped);
        $this->assertEquals(25 * 1_750_905, $a->ibc);
        $this->assertEquals(round(25 * 1_750_905 * 0.02), $a->solidarity_fund);
    }

    public function test_concepto_manual_por_empleado_sobrevive_al_recalculo(): void
    {
        $e = $this->conContrato('Juan', 2_000_000);
        $bono = $this->trabajando(fn () => PayrollConcept::create(['code' => 'BONDES', 'name' => 'Bonificación por desempeño', 'type' => 'devengado', 'calculation' => 'manual']));
        $n = $this->liquidarOctubre();

        $this->actingAs($this->admin)->post(route('payrolls.conceptos.store', $n), [
            'employee_id' => $e->id, 'payroll_concept_id' => $bono->id, 'amount' => 150_000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('payrolls.recalcular', $n))->assertSessionHasNoErrors();

        $f = $this->trabajando(fn () => PayrollEmployee::with('items')->sole());
        $this->assertEquals(150_000, $f->items->firstWhere('code', 'BONDES')->amount);
        $this->assertEquals(2_150_000, $f->ibc); // la bonificación es salarial
    }

    /* ═══════════════ NR-NOM-010 · Inmutabilidad y auditoría ═══════════════ */

    public function test_aprobada_es_inmutable_y_el_intento_queda_en_la_bitacora(): void
    {
        $this->conContrato('Karen', 2_000_000);
        $n = $this->liquidarOctubre();

        $this->actingAs($this->admin)->post(route('payrolls.aprobar', $n))->assertSessionHasNoErrors();
        $n->refresh();
        $this->assertSame(Payroll::APROBADA, $n->status);

        $aprobacion = AuditLog::where('auditable_type', Payroll::class)->where('event', 'actualizado')->latest('id')->first();
        $this->assertSame('borrador', $aprobacion->old_values['status']);
        $this->assertSame('aprobada', $aprobacion->new_values['status']);

        $this->actingAs($this->admin)->post(route('payrolls.recalcular', $n))
            ->assertSessionHas('error', NominaInmutable::MENSAJE);

        try {
            $this->trabajando(fn () => $n->update(['total_net' => 1]));
            $this->fail('Debió bloquear el UPDATE.');
        } catch (NominaInmutable) {
        }

        $this->assertTrue(AuditLog::where('auditable_type', Payroll::class)->where('event', 'bloqueado')->exists());
        $this->assertEquals(2_089_095, $n->fresh()->total_net);

        // El pago sí está permitido; el ajuste es la corrección.
        $this->actingAs($this->admin)->post(route('payrolls.pagar', $n))->assertSessionHasNoErrors();
        $this->assertSame(Payroll::PAGADA, $n->fresh()->status);

        $this->actingAs($this->admin)->post(route('payrolls.ajuste', $n))->assertRedirect();
        $ajuste = $this->trabajando(fn () => Payroll::where('kind', 'ajuste')->with('employees.items')->sole());
        $this->assertSame($n->id, $ajuste->adjusts_payroll_id);
        $this->assertCount(1, $ajuste->employees);
        $this->assertCount(0, $ajuste->employees->first()->items);
    }

    public function test_no_se_liquida_dos_veces_el_mismo_periodo(): void
    {
        $this->conContrato('Luis', 2_000_000);
        $this->liquidarOctubre();

        $this->actingAs($this->admin)->post(route('payrolls.store'), ['period_start' => '2026-10-16', 'period_end' => '2026-10-31'])
            ->assertSessionHasErrors('period_start');
    }

    /* ═══════════════ NR-NOM-006 · Vacaciones ═══════════════ */

    public function test_vacaciones_validan_saldo_y_se_pagan_en_la_nomina(): void
    {
        $e = $this->conContrato('Mara', 2_000_000, '2026-01-01');
        $svc = app(NovedadesService::class);

        $saldo = $this->trabajando(fn () => $svc->saldoVacaciones($e, Carbon::parse('2026-10-01')));
        $this->assertSame(11, $saldo); // 270 días × 15 / 360 = 11,25

        $this->actingAs($this->admin)->post(route('leaves.store'), [
            'employee_id' => $e->id, 'type' => 'vacaciones', 'start_date' => '2026-10-05', 'end_date' => '2026-10-31',
        ])->assertSessionHasErrors('end_date');

        $this->actingAs($this->admin)->post(route('leaves.store'), [
            'employee_id' => $e->id, 'type' => 'vacaciones', 'start_date' => '2026-10-05', 'end_date' => '2026-10-10',
        ])->assertSessionHasNoErrors();

        $v = $this->trabajando(fn () => LeaveRequest::sole());
        $this->assertSame(6, $v->days); // lunes a sábado
        $this->actingAs($this->admin)->post(route('leaves.aprobar', $v))->assertSessionHasNoErrors();

        // Calendario bloqueado: no hay horas extra en vacaciones.
        $this->expectException(ValidationException::class);

        try {
            $f = $this->liquidarOctubre()->employees->sole();
            $this->assertEquals(400_000, $f->items->firstWhere('code', 'VACACION')->amount);      // 6 días
            $this->assertEquals(round(249_095 / 30 * 24), $f->items->firstWhere('code', 'AUXTRANS')->amount);
        } finally {
            $this->trabajando(fn () => $svc->registrarHoras($e, ['date' => '2026-10-07', 'hours' => 1, 'type' => 'diurna'], $this->admin));
        }
    }

    public function test_incapacidad_exige_soporte_y_no_descuenta(): void
    {
        $e = $this->conContrato('Nico', 2_000_000);

        $this->actingAs($this->admin)->post(route('leaves.store'), [
            'employee_id' => $e->id, 'type' => 'incapacidad', 'start_date' => '2026-10-01', 'end_date' => '2026-10-03',
        ])->assertSessionHasErrors('document');

        $this->actingAs($this->admin)->post(route('leaves.store'), [
            'employee_id' => $e->id, 'type' => 'incapacidad', 'start_date' => '2026-10-01', 'end_date' => '2026-10-03',
            'document' => \Illuminate\Http\UploadedFile::fake()->create('incapacidad.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(LeaveRequest::APROBADA, $this->trabajando(fn () => LeaveRequest::sole()->status));
        $this->assertEquals(2_089_095, $this->liquidarOctubre()->employees->sole()->net);
    }

    /* ═══════════════ NR-NOM-007 · Prestaciones ═══════════════ */

    public function test_cesantias_intereses_y_prima(): void
    {
        $this->conContrato('Olga', 2_000_000, '2026-01-01');
        $svc = app(PrestacionesService::class);

        $ces = $this->trabajando(fn () => $svc->cesantias(Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30')));
        $base = 2_000_000 + 249_095;

        $this->assertEquals(round($base * 180 / 360), $ces->firstWhere('type', 'cesantias')->amount);
        $this->assertEquals(round(round($base * 180 / 360) * 0.12 * 180 / 360), $ces->firstWhere('type', 'intereses_cesantias')->amount);

        $prima = $this->trabajando(fn () => $svc->prima(Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30')));
        $this->assertEquals(round($base * 180 / 360), $prima->sole()->amount);

        // No se calcula dos veces.
        $this->assertCount(0, $this->trabajando(fn () => $svc->prima(Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30'))));

        $ids = $this->trabajando(fn () => SocialBenefit::pluck('id')->all());
        $this->actingAs($this->admin)->post(route('benefits.pagar'), ['ids' => $ids])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->trabajando(fn () => SocialBenefit::where('status', 'pagada')->count()));
    }

    /* ═══════════════ NR-NOM-008 · Desprendibles ═══════════════ */

    public function test_desprendible_pdf_con_qr_verificable_solo_si_esta_aprobada(): void
    {
        $this->conContrato('Pablo', 2_000_000);
        $n = $this->liquidarOctubre();
        $f = $n->employees->sole();

        $this->actingAs($this->admin)->get(route('payrolls.desprendible', [$n, $f]))->assertSessionHasErrors('nomina');
        $this->get(route('nomina.verificar', $f->verification_code))->assertNotFound();

        $this->trabajando(fn () => app(NominaService::class)->aprobar($n, $this->admin));

        $pdf = $this->actingAs($this->admin)->get(route('payrolls.desprendible', [$n, $f]))->assertOk();
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));

        auth()->logout();
        $this->get(route('nomina.verificar', $f->verification_code))->assertOk()->assertSee('Documento auténtico')->assertSee('2.089.095');
    }

    public function test_pila_trae_un_registro_por_cotizante(): void
    {
        $this->conContrato('Quique', 2_000_000);
        $this->conContrato('Rosa', 3_000_000);
        $n = $this->liquidarOctubre();
        $this->trabajando(fn () => app(NominaService::class)->aprobar($n, $this->admin));

        $txt = $this->actingAs($this->admin)->get(route('payrolls.pila', $n))->assertOk()->getContent();
        $lineas = array_values(array_filter(explode("\r\n", $txt)));

        $this->assertCount(3, $lineas);
        $this->assertStringStartsWith('01', $lineas[0]);
        $this->assertStringStartsWith('02', $lineas[1]);
    }

    /* ═══════════════ Permisos y consolidado ═══════════════ */

    public function test_permisos_de_nomina_por_rol(): void
    {
        $cajero = $this->empleado('Cajero');
        $supervisor = $this->empleado('Supervisor');
        $e = $this->conContrato('Sara', 2_000_000);

        $this->actingAs($cajero)->get(route('payrolls.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('payrolls.index'))->assertForbidden(); // salarios reservados
        $this->actingAs($supervisor)->get(route('overtime.index'))->assertOk();

        $this->actingAs($supervisor)->post(route('overtime.store'), ['employee_id' => $e->id, 'date' => '2026-10-02', 'hours' => 1, 'type' => 'diurna'])
            ->assertSessionHasNoErrors();
        $h = $this->trabajando(fn () => OvertimeEntry::sole());
        $this->actingAs($supervisor)->post(route('overtime.aprobar', $h))->assertForbidden();

        $this->actingAs($this->admin)->post(route('overtime.rechazar', $h), ['motivo' => 'No estaba autorizada'])->assertSessionHasNoErrors();
        $this->assertSame(1, $supervisor->fresh()->notifications()->count());

        $this->actingAs($this->admin)->get(route('payroll_report.index'))->assertForbidden();
        $this->actingAs($this->empleado('Superadministrador'))->get(route('payroll_report.index'))->assertOk()->assertSee('Principal');
    }

    public function test_consolidado_suma_tiendas_y_alerta_el_ratio_sobre_ingresos(): void
    {
        $this->conContrato('Tomás', 2_000_000);
        $n = $this->liquidarOctubre();
        $this->trabajando(fn () => app(NominaService::class)->aprobar($n, $this->admin));

        $svc = app(\App\Services\Nomina\ReporteNominaService::class);
        $fila = $this->trabajando(fn () => $svc->consolidado(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')))->firstWhere('tienda.id', $this->tienda->id);

        $this->assertSame(1, $fila['empleados']);
        $this->assertEquals(2_249_095 + 600_440, $fila['costo']);
        $this->assertNull($fila['ratio']); // sin ventas no hay ratio

        $xlsx = $this->actingAs($this->empleado('Superadministrador'))->get(route('payroll_report.excel', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->getContent());
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function nuevoEmpleado(string $nombre, string $ingreso = '2026-01-01'): Employee
    {
        static $n = 0;
        $n++;

        return $this->trabajando(fn () => Employee::create([
            'code' => 'EMP-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'first_name' => $nombre, 'last_name' => 'Prueba',
            'document_type' => 'CC', 'document_number' => (string) (1000 + $n), 'hire_date' => $ingreso, 'base_salary' => 0,
            'email' => strtolower($nombre) . '@prueba.test',
        ]));
    }

    private function conContrato(string $nombre, float $salario, string $inicio = '2026-01-01'): Employee
    {
        $e = $this->nuevoEmpleado($nombre, $inicio);
        $this->trabajando(fn () => app(ContratoService::class)->crear($e, ['type' => 'indefinido', 'start_date' => $inicio, 'base_salary' => $salario]));

        return $e->fresh();
    }

    private function liquidarOctubre(): Payroll
    {
        return $this->trabajando(function () {
            $n = app(NominaService::class)->liquidar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), $this->admin);

            return $n->load('employees.items', 'employees.contribution', 'employees.employee');
        });
    }
}
