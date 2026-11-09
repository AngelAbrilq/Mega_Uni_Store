<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de los pagos (TBL-PAG-001).
 *
 * Una transferencia o un Nequi no se pueden dar por buenos en el
 * mostrador: el cliente muestra la pantalla del celular y la plata puede
 * no llegar nunca. Los medios marcados con `requires_verification` dejan
 * el pago PENDIENTE hasta que un supervisor confirma que entró (aprobado)
 * o que no llegó (rechazado). Un pago rechazado deja a la venta con saldo
 * por cobrar (RN-32).
 *
 *   payment_methods.requires_verification   el medio exige verificación
 *   sale_payments.status                    aprobado | pendiente | rechazado
 *   sale_payments.verified_by/at            quién lo revisó y cuándo
 *   sale_payments.rejection_reason          por qué se rechazó
 *
 * Los pagos que ya existían quedan aprobados: así se comportaban.
 * Columnas en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $t) {
            if (! Schema::hasColumn('payment_methods', 'requires_verification')) {
                $t->boolean('requires_verification')->default(false)->after('is_active');
            }
        });

        Schema::table('sale_payments', function (Blueprint $t) {
            if (! Schema::hasColumn('sale_payments', 'status')) {
                $t->string('status', 12)->default('aprobado')->after('reference');
            }
            if (! Schema::hasColumn('sale_payments', 'verified_by')) {
                $t->foreignId('verified_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sale_payments', 'verified_at')) {
                $t->timestamp('verified_at')->nullable()->after('verified_by');
            }
            if (! Schema::hasColumn('sale_payments', 'rejection_reason')) {
                $t->string('rejection_reason', 255)->nullable()->after('verified_at');
            }
        });

        Schema::table('sale_payments', function (Blueprint $t) {
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $t) {
            $t->dropIndex(['status']);
            $t->dropForeign(['verified_by']);
        });

        Schema::table('sale_payments', function (Blueprint $t) {
            $t->dropColumn(['status', 'verified_by', 'verified_at', 'rejection_reason']);
        });

        Schema::table('payment_methods', function (Blueprint $t) {
            $t->dropColumn('requires_verification');
        });
    }
};
