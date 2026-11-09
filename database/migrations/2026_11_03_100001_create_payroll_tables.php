<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nómina (NR-NOM-001…010 · RN-45). Tablas nuevas en inglés (ADR-001):
 *
 *   employment_contracts   contratos laborales con historia               ← contratos
 *   payroll_concepts       devengados y deducciones parametrizables        ← conceptos_nomina
 *   overtime_entries       horas extra con recargo y aprobación            ← horas_extras
 *   leave_requests         vacaciones y ausencias                          ← vacaciones_ausencias
 *   payrolls               la nómina de un período (borrador → aprobada)   ← nominas
 *   payroll_employees      una fila por empleado liquidado                 ← nomina_empleado
 *   payroll_items          cada concepto aplicado                          ← nomina_detalle
 *   payroll_contributions  aportes a seguridad social y parafiscales       ← aportes_seguridad_social
 *   social_benefits        cesantías, intereses, prima, vacaciones $       ← prestaciones_sociales
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $t->string('type', 15);                  // indefinido | fijo | obra_labor | aprendizaje | prestacion
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->decimal('base_salary', 12, 2);
            $t->unsignedTinyInteger('arl_class')->default(1); // riesgo I…V
            $t->string('status', 12)->default('activo');      // activo | finalizado | terminado
            $t->foreignId('renewed_from_id')->nullable()->constrained('employment_contracts')->nullOnDelete();
            $t->date('ended_at')->nullable();
            $t->string('end_reason', 200)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status']);
        });

        Schema::create('payroll_concepts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('code', 20);
            $t->string('name', 80);
            $t->string('type', 10);                  // devengado | deduccion | aporte (patronal, informativo)
            $t->string('calculation', 15);           // sistema | fijo | pct_salario | pct_smlmv | manual
            $t->decimal('value', 12, 4)->default(0); // valor fijo o porcentaje
            $t->boolean('is_mandatory')->default(false); // se aplica a todos en cada liquidación
            $t->boolean('is_salary')->default(true);     // suma al IBC
            $t->boolean('is_system')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['empresa_id', 'code']);
            $t->unique(['empresa_id', 'name']);
        });

        Schema::create('overtime_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('date');
            $t->decimal('hours', 5, 2);
            $t->string('type', 20);                   // diurna | nocturna | dominical_diurna | dominical_nocturna | recargo_nocturno | recargo_dominical
            $t->decimal('factor', 6, 4);              // multiplicador sobre la hora ordinaria
            $t->decimal('hour_value', 12, 2);
            $t->decimal('amount', 12, 2);
            $t->string('status', 10)->default('pendiente');
            $t->string('notes', 200)->nullable();
            $t->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason', 200)->nullable();
            $t->unsignedBigInteger('payroll_id')->nullable()->index();
            $t->timestamps();
            $t->index(['tienda_id', 'status', 'date']);
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->string('type', 25);                   // vacaciones | incapacidad | licencia_remunerada | licencia_no_remunerada | injustificada
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedSmallInteger('days');         // hábiles en vacaciones; calendario en ausencias
            $t->string('status', 10)->default('pendiente');
            $t->string('notes', 200)->nullable();
            $t->string('document_path')->nullable();  // soporte (incapacidad)
            $t->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason', 200)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'type', 'status']);
        });

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->string('number', 20);
            $t->date('period_start');
            $t->date('period_end');
            $t->string('kind', 10)->default('normal'); // normal | ajuste
            $t->foreignId('adjusts_payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $t->string('status', 10)->default('borrador'); // borrador | aprobada | pagada
            $t->unsignedSmallInteger('employees_count')->default(0);
            $t->decimal('total_earned', 14, 2)->default(0);
            $t->decimal('total_deductions', 14, 2)->default(0);
            $t->decimal('total_net', 14, 2)->default(0);
            $t->decimal('total_employer', 14, 2)->default(0);
            $t->json('parameters')->nullable();       // SMLMV, auxilio, horas/mes… con que se liquidó
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->string('notes', 200)->nullable();
            $t->timestamps();
            $t->unique(['tienda_id', 'number']);
            $t->index(['tienda_id', 'period_start']);
        });

        Schema::create('payroll_employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees');
            $t->foreignId('employment_contract_id')->nullable()->constrained('employment_contracts')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete(); // centro de costo
            $t->decimal('base_salary', 12, 2);
            $t->unsignedTinyInteger('days_worked');
            $t->unsignedTinyInteger('absence_days')->default(0);
            $t->decimal('ibc', 12, 2)->default(0);
            $t->decimal('earned', 12, 2)->default(0);
            $t->decimal('deductions', 12, 2)->default(0);
            $t->decimal('net', 12, 2)->default(0);
            $t->char('verification_code', 16)->unique();
            $t->timestamps();
            $t->unique(['payroll_id', 'employee_id']);
        });

        Schema::create('payroll_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_employee_id')->constrained('payroll_employees')->cascadeOnDelete();
            $t->foreignId('payroll_concept_id')->nullable()->constrained('payroll_concepts')->nullOnDelete();
            $t->string('code', 20);
            $t->string('name', 80);
            $t->string('type', 10);                   // devengado | deduccion
            $t->decimal('quantity', 8, 2)->nullable(); // días u horas
            $t->decimal('amount', 12, 2);
            $t->boolean('is_manual')->default(false);
            $t->timestamps();
        });

        Schema::create('payroll_contributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_employee_id')->unique()->constrained('payroll_employees')->cascadeOnDelete();
            $t->decimal('ibc', 12, 2);
            $t->boolean('ibc_capped')->default(false);
            $t->decimal('health_employee', 12, 2)->default(0);
            $t->decimal('health_employer', 12, 2)->default(0);
            $t->decimal('pension_employee', 12, 2)->default(0);
            $t->decimal('pension_employer', 12, 2)->default(0);
            $t->decimal('solidarity_fund', 12, 2)->default(0);
            $t->unsignedTinyInteger('arl_class')->default(1);
            $t->decimal('arl', 12, 2)->default(0);
            $t->decimal('ccf', 12, 2)->default(0);
            $t->decimal('icbf', 12, 2)->default(0);
            $t->decimal('sena', 12, 2)->default(0);
            $t->decimal('total_employer', 12, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('social_benefits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->string('type', 25);                   // cesantias | intereses_cesantias | prima | vacaciones_dinero
            $t->date('period_start');
            $t->date('period_end');
            $t->unsignedSmallInteger('days');
            $t->decimal('base', 12, 2);
            $t->decimal('amount', 12, 2);
            $t->string('status', 10)->default('calculada'); // calculada | pagada
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        foreach (['social_benefits', 'payroll_contributions', 'payroll_items', 'payroll_employees', 'payrolls',
                  'leave_requests', 'overtime_entries', 'payroll_concepts', 'employment_contracts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
