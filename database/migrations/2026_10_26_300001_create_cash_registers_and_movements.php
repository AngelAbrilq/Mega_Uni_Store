<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cajas registradoras físicas y movimientos de efectivo (AT-CAJ-001).
 *
 * Hasta aquí el turno de caja era solo de una persona: no se sabía en qué
 * caja física estaba el dinero, y el arqueo no contemplaba que en mitad
 * del turno se sacara plata (consignación, pago a un domiciliario) o se
 * agregara (cambio que trae el supervisor).
 *
 *   cash_registers   Caja 1 «Principal», Caja 2 «Rápida»… por local.
 *                    Si el local no define ninguna, el turno funciona como
 *                    antes (solo por persona): compatible hacia atrás.
 *   cash_sessions    + cash_register_id: en qué caja física se trabajó.
 *                    Una caja admite un solo turno abierto a la vez (RN-31).
 *   cash_movements   ingreso | retiro de efectivo durante el turno, con
 *                    motivo y responsable. Entran al efectivo esperado.
 *
 * Equivale a las tablas documentadas `cajas` y `cajas_movimientos`
 * (apertura y cierre siguen viviendo en cash_sessions). Nombres en
 * inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('code', 10)->nullable();
            $t->string('description', 200)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['tienda_id', 'name']);
            $t->index(['tienda_id', 'is_active']);
        });

        Schema::table('cash_sessions', function (Blueprint $t) {
            $t->foreignId('cash_register_id')->nullable()->after('tienda_id')
                ->constrained('cash_registers')->nullOnDelete();
            $t->index(['cash_register_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 10);
            $t->decimal('amount', 12, 2);
            $t->string('reason', 200);
            $t->timestamps();

            $t->index(['cash_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');

        Schema::table('cash_sessions', function (Blueprint $t) {
            $t->dropForeign(['cash_register_id']);
            $t->dropIndex(['cash_register_id', 'status']);
            $t->dropColumn('cash_register_id');
        });

        Schema::dropIfExists('cash_registers');
    }
};
