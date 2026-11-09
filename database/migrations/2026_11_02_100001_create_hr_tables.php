<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RRHH operativo (RN-44): el equipo de cada local.
 *
 *   departments          áreas del local (Ventas, Bodega, Caja…) con responsable   ← `areas`
 *   positions            cargos con nivel jerárquico                                ← `cargos`
 *   employees            empleados (código único por local, ingreso, salario)       ← `empleados`
 *   employee_position    historial de cargos; un solo cargo activo                  ← `empleados_cargos`
 *   department_employee  áreas del empleado; una sola principal                     ← `empleados_areas`
 *   work_schedules       horarios (Turno mañana, Turno tarde)                       ← `horarios`
 *   work_shifts          franjas por día de la semana de un horario                 ← `turnos`
 *   employee_schedule    horario asignado, con vigencia (historial)                 ← `empleados_horarios`
 *
 * Tablas nuevas en inglés (ADR-001); el ámbito es el local (tienda_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('description', 200)->nullable();
            $t->unsignedTinyInteger('level')->default(5); // 1 = más alto
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tienda_id', 'name']);
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('code', 20);
            $t->string('first_name', 80);
            $t->string('last_name', 80)->nullable();
            $t->string('document_type', 5)->nullable();
            $t->string('document_number', 20)->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('email', 120)->nullable();
            $t->date('hire_date');
            $t->decimal('base_salary', 12, 2)->default(0);
            $t->string('status', 10)->default('activo'); // activo | inactivo
            $t->date('termination_date')->nullable();
            $t->string('termination_reason', 200)->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['tienda_id', 'code']);
            $t->unique(['tienda_id', 'user_id']);
            $t->index(['tienda_id', 'status']);
        });

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('description', 200)->nullable();
            $t->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tienda_id', 'name']);
        });

        Schema::create('employee_position', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['employee_id', 'is_active']);
        });

        Schema::create('department_employee', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('assigned_at');
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
            $t->unique(['department_id', 'employee_id']);
        });

        Schema::create('work_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('description', 200)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tienda_id', 'name']);
        });

        Schema::create('work_shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_schedule_id')->constrained('work_schedules')->cascadeOnDelete();
            $t->unsignedTinyInteger('weekday'); // 1 = lunes … 7 = domingo (ISO-8601)
            $t->time('start_time');
            $t->time('end_time');
            $t->timestamps();
            $t->index(['work_schedule_id', 'weekday']);
        });

        Schema::create('employee_schedule', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->foreignId('work_schedule_id')->constrained('work_schedules')->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'end_date']);
            $t->index(['work_schedule_id', 'end_date']);
        });
    }

    public function down(): void
    {
        foreach (['employee_schedule', 'work_shifts', 'work_schedules', 'department_employee',
                  'employee_position', 'departments', 'employees', 'positions'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
