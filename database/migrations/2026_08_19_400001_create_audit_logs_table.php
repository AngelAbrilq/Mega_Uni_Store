<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría: quién cambió qué, cuándo y desde dónde.
 *
 * Es la respuesta a "¿quién le bajó el precio a este producto?" — sin
 * esto, un sistema de tienda no se puede defender ante una diferencia
 * de inventario o de caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120)->nullable();   // copia: si borran al usuario, queda el nombre

            $table->string('event', 20);                    // creado | actualizado | eliminado | restaurado
            $table->morphs('auditable');                    // sobre qué modelo y cuál registro
            $table->string('auditable_label', 200)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('url', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
