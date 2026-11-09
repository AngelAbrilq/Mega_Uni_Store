<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VD-VTA-002 · Solicitudes de anulación (RN-43).
 *
 * El Vendedor (y el Cajero) no anulan directo: piden la anulación con un
 * motivo y un supervisor la aprueba o la rechaza. Aprobar ejecuta la
 * anulación de siempre (SaleService::anular): reingreso de inventario,
 * cupón, puntos y auditoría. Tabla nueva en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_void_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users');
            $t->string('reason', 255);
            $t->string('status', 10)->default('pendiente'); // pendiente | aprobada | rechazada
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason', 255)->nullable();
            $t->timestamps();

            $t->index(['tienda_id', 'status']);
            $t->index(['sale_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_void_requests');
    }
};
