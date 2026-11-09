<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exportación y envío de reportes (RP-EXP-001, TBL-ENV-001) y
 * notificaciones persistentes (SA-NOT-001).
 *
 *   report_exports     cada archivo PDF/XLSX generado: qué reporte, con qué
 *                      filtros, quién, cuánto pesa y dónde quedó guardado.
 *                      Equivale a `reportes` + `exportaciones` de la doc.
 *   report_deliveries  cada envío de una exportación por email o WhatsApp,
 *                      con destinatario y estado (pendiente | enviado |
 *                      fallido). Equivale a `envios_reporte`.
 *   notifications      la tabla estándar de Laravel (canal database): un
 *                      aviso por usuario con su read_at. Equivale a
 *                      `notificaciones`.
 *
 * Nombres en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('tienda_id')->nullable()->constrained('tiendas')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('report', 40);
            $t->string('format', 5);
            $t->json('filters')->nullable();
            $t->string('file_name', 160);
            $t->string('file_path', 255);
            $t->unsignedInteger('size_bytes')->default(0);
            $t->unsignedInteger('rows')->default(0);
            $t->timestamps();

            $t->index(['empresa_id', 'created_at']);
        });

        Schema::create('report_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_export_id')->constrained('report_exports')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('channel', 10);
            $t->string('recipient', 150);
            $t->string('status', 10)->default('pendiente');
            $t->string('error', 255)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();

            $t->index(['report_export_id', 'status']);
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('type');
                $t->morphs('notifiable');
                $t->text('data');
                $t->timestamp('read_at')->nullable();
                $t->timestamps();

                $t->index(['notifiable_type', 'notifiable_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('report_deliveries');
        Schema::dropIfExists('report_exports');
    }
};
