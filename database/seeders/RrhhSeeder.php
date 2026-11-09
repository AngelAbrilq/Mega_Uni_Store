<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Empresa;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\RrhhService;
use App\Support\Contexto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * RRHH de demostración (RN-44): cargos, áreas, dos horarios y el equipo
 * del local principal, vinculado a los usuarios de prueba.
 */
class RrhhSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::query()->orderBy('id')->first();
        $tienda  = $empresa?->tiendaPrincipal();

        if (! $tienda) {
            return;
        }

        Contexto::comoEmpresa($empresa->id, $tienda->id, function () {
            $rrhh = app(RrhhService::class);

            $cargos = collect([
                ['Gerente de tienda', 1], ['Supervisor', 2], ['Vendedor', 5], ['Cajero', 6], ['Bodeguero', 6], ['Auxiliar de aseo', 8],
            ])->mapWithKeys(fn ($c) => [$c[0] => Position::firstOrCreate(['name' => $c[0]], ['level' => $c[1]])]);

            $areas = collect(['Administración', 'Ventas', 'Caja', 'Bodega'])
                ->mapWithKeys(fn ($a) => [$a => Department::firstOrCreate(['name' => $a])]);

            $manana = WorkSchedule::firstOrCreate(['name' => 'Turno mañana'], ['description' => 'Apertura de lunes a sábado']);
            $tarde  = WorkSchedule::firstOrCreate(['name' => 'Turno tarde'], ['description' => 'Cierre de lunes a sábado']);

            if ($manana->shifts()->doesntExist()) {
                foreach (range(1, 5) as $d) {
                    $manana->shifts()->create(['weekday' => $d, 'start_time' => '07:00', 'end_time' => '15:00']);
                    $tarde->shifts()->create(['weekday' => $d, 'start_time' => '13:00', 'end_time' => '21:00']);
                }
                $manana->shifts()->create(['weekday' => 6, 'start_time' => '07:00', 'end_time' => '13:00']);
                $tarde->shifts()->create(['weekday' => 6, 'start_time' => '13:00', 'end_time' => '19:00']);
            }

            $usuario = fn (string $correo) => User::where('email', $correo)->value('id');

            $equipo = [
                ['EMP-001', 'Andrés', 'Molina Rojas', 'admin@megaunistore.test', 'Gerente de tienda', 'Administración', $manana, 3_800_000, '2025-02-03'],
                ['EMP-002', 'Sofía', 'Salazar Díaz', 'supervisor@megaunistore.test', 'Supervisor', 'Ventas', $manana, 2_600_000, '2025-04-14'],
                ['EMP-003', 'Valentina', 'Ríos Pérez', 'vendedor@megaunistore.test', 'Vendedor', 'Ventas', $tarde, 1_750_000, '2025-08-01'],
                ['EMP-004', 'Camilo', 'Torres Vega', 'cajero@megaunistore.test', 'Cajero', 'Caja', $manana, 1_600_000, '2026-01-12'],
                ['EMP-005', 'Jhon', 'Cárdenas Ruiz', 'bodega@megaunistore.test', 'Bodeguero', 'Bodega', $tarde, 1_600_000, '2025-11-18'],
                ['EMP-006', 'Marta', 'Ospina', null, 'Auxiliar de aseo', 'Administración', $manana, 1_423_500, '2026-03-02'],
            ];

            foreach ($equipo as [$codigo, $nombre, $apellido, $correo, $cargo, $area, $horario, $salario, $ingreso]) {
                if (\App\Models\Employee::withTrashed()->where('code', $codigo)->exists()) {
                    continue;
                }

                $e = $rrhh->registrar([
                    'code' => $codigo, 'first_name' => $nombre, 'last_name' => $apellido,
                    'user_id' => $correo ? $usuario($correo) : null, 'document_type' => 'CC',
                    'document_number' => (string) random_int(1_000_000_000, 1_099_999_999),
                    'hire_date' => $ingreso, 'base_salary' => $salario,
                    'position_id' => $cargos[$cargo]->id, 'department_id' => $areas[$area]->id, 'work_schedule_id' => $horario->id,
                ]);

                if ($codigo === 'EMP-004') { // el cajero también apoya en Ventas
                    $rrhh->asignarArea($e, $areas['Ventas'], false, Carbon::parse('2026-03-01'));
                }
            }

            $areas['Administración']->update(['manager_id' => \App\Models\Employee::where('code', 'EMP-001')->value('id')]);
            $areas['Ventas']->update(['manager_id' => \App\Models\Employee::where('code', 'EMP-002')->value('id')]);

            $this->command?->info('  RRHH: ' . count($equipo) . ' empleados, ' . $cargos->count() . ' cargos, ' . $areas->count() . ' áreas, 2 horarios');
        });
    }
}
