<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes por local y puntos de fidelidad (CL-TDC-001, CL-PUN-001).
 *
 *   customer_tienda       el cliente en ESTE local: puntos, total gastado y
 *                         número de compras son independientes por local
 *                         (un cliente de Centro y de Norte lleva dos cuentas).
 *                         Equivale a `tiendas_clientes`.
 *   loyalty_transactions  cada movimiento de puntos: ganados en una venta,
 *                         devueltos al anular o por una devolución. El saldo
 *                         del local siempre se puede explicar renglón a renglón.
 *
 * Pivote con el nombre de los modelos en orden alfabético (customer_tienda);
 * tabla nueva en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tienda', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->integer('points_balance')->default(0);
            $t->decimal('total_spent', 14, 2)->default(0);
            $t->unsignedInteger('purchases_count')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['customer_id', 'tienda_id']);
            $t->index(['tienda_id', 'is_active']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $t->foreignId('sale_return_id')->nullable()->constrained('sale_returns')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 10);
            $t->integer('points');
            $t->integer('balance_after');
            $t->string('notes', 200)->nullable();
            $t->timestamps();

            $t->index(['customer_id', 'tienda_id', 'created_at']);
            $t->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('customer_tienda');
    }
};
