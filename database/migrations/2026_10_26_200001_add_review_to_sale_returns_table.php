<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devoluciones con aprobación (AT-DEV-001, BD-DEV-001, TBL `devoluciones`).
 *
 * Antes la devolución se aplicaba al instante: cualquier cajero con
 * `ventas.devolver` metía mercancía al inventario sin que nadie lo revisara.
 * Ahora quien no tiene `ventas.aprobar_devolucion` deja la devolución
 * PENDIENTE (reserva la cantidad, no mueve inventario) y un supervisor la
 * aprueba o la rechaza.
 *
 *   status            pendiente | aprobada | rechazada
 *   reviewed_by/at    quién la aprobó o rechazó y cuándo
 *   rejection_reason  por qué se rechazó
 *
 * Las devoluciones que ya existían quedan como aprobadas por quien las
 * registró: así se comportaban. Columnas en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $t) {
            if (! Schema::hasColumn('sale_returns', 'status')) {
                $t->string('status', 12)->default('aprobada')->after('restock');
            }
            if (! Schema::hasColumn('sale_returns', 'reviewed_by')) {
                $t->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sale_returns', 'reviewed_at')) {
                $t->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('sale_returns', 'rejection_reason')) {
                $t->string('rejection_reason', 255)->nullable()->after('reviewed_at');
            }
        });

        Schema::table('sale_returns', function (Blueprint $t) {
            $t->index(['tienda_id', 'status']);
        });

        DB::table('sale_returns')
            ->whereNull('reviewed_by')
            ->update([
                'reviewed_by' => DB::raw('user_id'),
                'reviewed_at' => DB::raw('returned_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $t) {
            $t->dropIndex(['tienda_id', 'status']);
            $t->dropForeign(['reviewed_by']);
        });

        Schema::table('sale_returns', function (Blueprint $t) {
            $t->dropColumn(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason']);
        });
    }
};
