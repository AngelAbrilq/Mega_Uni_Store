<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos de caja. Una venta en efectivo siempre pertenece a un turno
 * abierto; al cerrarlo se compara lo que dice el sistema contra lo que
 * hay físicamente en el cajón (arqueo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');          // quién abrió el turno
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('opening_amount', 12, 2)->default(0);        // base inicial
            $table->decimal('expected_amount', 12, 2)->default(0);       // lo que debería haber
            $table->decimal('counted_amount', 12, 2)->nullable();        // lo que se contó
            $table->decimal('difference', 12, 2)->default(0);            // sobrante o faltante

            $table->string('status', 20)->default('abierta');            // abierta | cerrada
            $table->text('notes')->nullable();

            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
