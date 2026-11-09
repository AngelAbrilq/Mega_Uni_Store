<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tienda;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;

/**
 * RRHH operativo (RN-44):
 *   AT-EMP-001 empleados · AT-ARE-001 áreas · AT-CGO-001 cargos · AT-HOR-001 horarios
 *   TBL-TUR-001 turnos · TBL-EHA-001 áreas del empleado · VW-EMP-001 activos.
 */
class RrhhTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $admin;
    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNegocio('rrhh');
        $this->admin  = $this->empleado('Administrador');
        $this->cajero = $this->empleado('Cajero');
    }

    /* ═══════════════ AT-EMP-001 ═══════════════ */

    public function test_registra_un_empleado_con_cargo_area_y_horario(): void
    {
        [$cargo, $area, $horario] = $this->trabajando(fn () => [
            Position::create(['name' => 'Cajero', 'level' => 6]),
            Department::create(['name' => 'Caja']),
            WorkSchedule::create(['name' => 'Turno mañana']),
        ]);

        $this->actingAs($this->admin)->post('/employees', $this->datos([
            'user_id' => $this->cajero->id, 'position_id' => $cargo->id, 'department_id' => $area->id, 'work_schedule_id' => $horario->id,
        ]))->assertSessionHasNoErrors();

        $e = $this->trabajando(fn () => Employee::with(['positions', 'departments', 'scheduleAssignments'])->where('code', 'EMP-001')->sole());
        $this->assertSame(Employee::ACTIVO, $e->status);
        $this->assertSame($this->tienda->id, $e->tienda_id);
        $this->assertSame('Cajero', $e->cargoActual()?->name);
        $this->assertSame('Caja', $e->areaPrincipal()?->name);
        $this->assertSame($horario->id, $e->horarioActual()?->work_schedule_id);
    }

    public function test_codigo_y_usuario_no_se_repiten_en_el_local(): void
    {
        $this->actingAs($this->admin)->post('/employees', $this->datos(['user_id' => $this->cajero->id]));

        $this->actingAs($this->admin)->from('/employees/create')
            ->post('/employees', $this->datos(['code' => 'emp-001', 'user_id' => $this->cajero->id]))
            ->assertSessionHasErrors([
                'code'    => 'El código de empleado ya existe en esta tienda.',
                'user_id' => 'Este usuario ya está registrado como empleado en esta tienda.',
            ]);
    }

    public function test_el_mismo_codigo_si_se_usa_en_otro_local(): void
    {
        $this->actingAs($this->admin)->post('/employees', $this->datos());
        $norte = Tienda::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Norte', 'slug' => 'norte-rrhh', 'activa' => true]);

        Contexto::comoEmpresa($this->empresa->id, $norte->id, fn () => Employee::create([
            'code' => 'EMP-001', 'first_name' => 'Otro', 'hire_date' => '2026-01-01', 'base_salary' => 1,
        ]));

        $this->assertSame(2, Employee::withoutGlobalScopes()->where('code', 'EMP-001')->count());
    }

    public function test_las_pantallas_de_rrhh_abren(): void
    {
        $this->actingAs($this->admin)->get('/employees/create')->assertOk()->assertSee('EMP-001');
        foreach (['/departments', '/departments/create', '/positions', '/positions/create', '/work_schedules', '/work_schedules/create'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_el_listado_muestra_activos_con_cargo_y_area(): void
    {
        $this->actingAs($this->admin)->post('/employees', $this->datos([
            'position_id' => $this->trabajando(fn () => Position::create(['name' => 'Vendedor senior']))->id,
            'department_id' => $this->trabajando(fn () => Department::create(['name' => 'Ventas']))->id,
        ]));

        $this->actingAs($this->admin)->get('/employees')
            ->assertOk()->assertSee('Laura Gómez')->assertSee('Vendedor senior')->assertSee('Ventas');
    }

    public function test_el_retiro_lo_saca_de_los_activos_y_cierra_cargo_y_horario(): void
    {
        $horario = $this->trabajando(fn () => WorkSchedule::create(['name' => 'Tarde']));
        $cargo   = $this->trabajando(fn () => Position::create(['name' => 'Bodeguero']));
        $this->actingAs($this->admin)->post('/employees', $this->datos(['work_schedule_id' => $horario->id, 'position_id' => $cargo->id]));
        $e = $this->trabajando(fn () => Employee::sole());

        $this->actingAs($this->admin)->delete("/employees/{$e->id}", ['fecha' => today()->toDateString(), 'motivo' => 'Renuncia voluntaria'])
            ->assertSessionHasNoErrors();

        $e = $this->trabajando(fn () => Employee::withTrashed()->find($e->id));
        $this->assertSoftDeleted($e);
        $this->assertSame(Employee::INACTIVO, $e->status);
        $this->assertSame(0, $this->trabajando(fn () => Employee::activos()->count()), 'VW-EMP-001: ya no está en los activos.');
        $this->assertDatabaseHas('employee_position', ['employee_id' => $e->id, 'is_active' => false]);
        $this->assertNotNull($e->scheduleAssignments()->first()->end_date);
        $this->actingAs($this->admin)->get("/employees/{$e->id}")->assertOk()->assertSee('Retirado');
    }

    public function test_un_cajero_no_ve_ni_registra_empleados(): void
    {
        $this->actingAs($this->cajero)->get('/employees')->assertForbidden();
        $this->actingAs($this->cajero)->post('/employees', $this->datos())->assertForbidden();
        $this->actingAs($this->cajero)->get('/departments')->assertForbidden();
    }

    /* ═══════════════ AT-CGO-001 · un solo cargo activo ═══════════════ */

    public function test_el_cargo_nuevo_desactiva_el_anterior_y_guarda_la_historia(): void
    {
        [$vend, $sup] = $this->trabajando(fn () => [Position::create(['name' => 'Vendedor', 'level' => 6]), Position::create(['name' => 'Supervisor', 'level' => 2])]);
        $this->actingAs($this->admin)->post('/employees', $this->datos(['position_id' => $vend->id]));
        $e = $this->trabajando(fn () => Employee::sole());

        $this->actingAs($this->admin)->post("/employees/{$e->id}/asignar", ['tipo' => 'cargo', 'position_id' => $sup->id])->assertSessionHasNoErrors();

        $e = $this->trabajando(fn () => Employee::with('positions')->find($e->id));
        $this->assertSame('Supervisor', $e->cargoActual()->name);
        $this->assertCount(2, $e->positions);
        $this->assertSame(1, $e->positions->where('pivot.is_active', true)->count());
    }

    /* ═══════════════ TBL-EHA-001 · una sola área principal ═══════════════ */

    public function test_una_sola_area_principal_y_varias_secundarias(): void
    {
        [$ventas, $caja] = $this->trabajando(fn () => [Department::create(['name' => 'Ventas']), Department::create(['name' => 'Caja'])]);
        $this->actingAs($this->admin)->post('/employees', $this->datos(['department_id' => $ventas->id]));
        $e = $this->trabajando(fn () => Employee::sole());

        $this->actingAs($this->admin)->post("/employees/{$e->id}/asignar", ['tipo' => 'area', 'department_id' => $caja->id]);
        $this->assertSame('Ventas', $this->trabajando(fn () => Employee::with('departments')->find($e->id))->areaPrincipal()->name);

        $this->actingAs($this->admin)->post("/employees/{$e->id}/asignar", ['tipo' => 'area', 'department_id' => $caja->id, 'is_primary' => '1']);
        $e = $this->trabajando(fn () => Employee::with('departments')->find($e->id));
        $this->assertSame('Caja', $e->areaPrincipal()->name);
        $this->assertSame(1, $e->departments->where('pivot.is_primary', true)->count());
        $this->assertCount(2, $e->departments);
    }

    /* ═══════════════ AT-ARE-001 ═══════════════ */

    public function test_un_area_con_empleados_no_se_elimina_y_muestra_su_gente(): void
    {
        $ventas = $this->trabajando(fn () => Department::create(['name' => 'Ventas']));
        $this->actingAs($this->admin)->post('/employees', $this->datos(['department_id' => $ventas->id]));
        $e = $this->trabajando(fn () => Employee::sole());

        $this->actingAs($this->admin)->put("/departments/{$ventas->id}", ['name' => 'Ventas', 'manager_id' => $e->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get("/departments/{$ventas->id}")->assertOk()->assertSee('Laura Gómez')->assertSee('Responsable');

        $this->actingAs($this->admin)->from("/departments/{$ventas->id}")->delete("/departments/{$ventas->id}")
            ->assertSessionHasErrors(['area' => 'No se puede eliminar el área porque tiene empleados asignados.']);
        $this->assertNotNull($ventas->fresh());
    }

    /* ═══════════════ AT-HOR-001 · TBL-TUR-001 ═══════════════ */

    public function test_franjas_por_dia_sin_cruces_y_orden_por_dia(): void
    {
        $h = $this->trabajando(fn () => WorkSchedule::create(['name' => 'Turno mañana']));

        $this->actingAs($this->admin)->post("/work_schedules/{$h->id}/turnos", ['weekdays' => [6, 1], 'start_time' => '07:00', 'end_time' => '13:00'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->from("/work_schedules/{$h->id}")
            ->post("/work_schedules/{$h->id}/turnos", ['weekdays' => [1], 'start_time' => '12:00', 'end_time' => '15:00'])
            ->assertSessionHasErrors('start_time');
        $this->actingAs($this->admin)->from("/work_schedules/{$h->id}")
            ->post("/work_schedules/{$h->id}/turnos", ['weekdays' => [2], 'start_time' => '15:00', 'end_time' => '07:00'])
            ->assertSessionHasErrors('end_time');

        $franjas = $this->trabajando(fn () => $h->fresh()->shifts);
        $this->assertSame([1, 6], $franjas->pluck('weekday')->all());
        $this->assertEquals(12, $this->trabajando(fn () => $h->fresh('shifts')->horasSemana()));
    }

    public function test_cambiar_de_horario_cierra_el_anterior_y_no_se_quita_una_franja_en_uso(): void
    {
        [$man, $tar] = $this->trabajando(fn () => [WorkSchedule::create(['name' => 'Mañana']), WorkSchedule::create(['name' => 'Tarde'])]);
        $franja = $this->trabajando(fn () => $man->shifts()->create(['weekday' => 1, 'start_time' => '07:00', 'end_time' => '15:00']));
        $this->actingAs($this->admin)->post('/employees', $this->datos(['work_schedule_id' => $man->id, 'hire_date' => today()->subMonth()->toDateString()]));
        $e = $this->trabajando(fn () => Employee::sole());

        $this->actingAs($this->admin)->from("/work_schedules/{$man->id}")
            ->delete("/work_schedules/{$man->id}/turnos/{$franja->id}")->assertSessionHasErrors('horario');

        $this->actingAs($this->admin)->post("/employees/{$e->id}/asignar", ['tipo' => 'horario', 'work_schedule_id' => $tar->id]);

        $e = $this->trabajando(fn () => Employee::with('scheduleAssignments')->find($e->id));
        $this->assertSame($tar->id, $e->horarioActual()->work_schedule_id);
        $this->assertSame(today()->subDay()->toDateString(), $e->scheduleAssignments->firstWhere('work_schedule_id', $man->id)->end_date->toDateString());

        // Ya nadie tiene «Mañana» hoy: ahora sí se quita la franja.
        $this->actingAs($this->admin)->delete("/work_schedules/{$man->id}/turnos/{$franja->id}")->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get("/employees/{$e->id}")->assertOk()->assertSee('Tarde');
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'code' => 'EMP-001', 'first_name' => 'Laura', 'last_name' => 'Gómez', 'document_type' => 'CC', 'document_number' => '1075234567',
            'hire_date' => '2026-02-01', 'base_salary' => 1_423_500,
        ];
    }
}
