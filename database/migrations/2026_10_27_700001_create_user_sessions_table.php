<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TBL-SES-001 · Sesiones de los usuarios del panel (equivale a `sesiones`).
 *
 * La tabla `sessions` de Laravel guarda el CONTENIDO de la sesión y se
 * reescribe en cada petición; esta guarda la HISTORIA: quién entró, desde
 * qué dispositivo e IP, hasta cuándo vale y cómo terminó. El identificador
 * de la sesión nunca se guarda en claro: solo su SHA-256 (`token_hash`),
 * para que quien lea esta tabla no pueda usarla para colarse.
 *
 * Tabla nueva en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->char('token_hash', 64)->unique();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->string('device', 80)->nullable();
            $t->string('device_type', 10)->default('escritorio'); // escritorio | celular | tableta
            $t->timestamp('last_activity_at')->nullable();
            $t->timestamp('expires_at');
            $t->boolean('is_active')->default(true);
            $t->timestamp('ended_at')->nullable();
            $t->string('end_reason', 12)->nullable(); // logout | expired | revoked
            $t->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['user_id', 'is_active']);
            $t->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
