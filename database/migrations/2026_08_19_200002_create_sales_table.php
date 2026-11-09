<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de la venta. Los totales se guardan calculados: si mañana
 * cambia el precio del producto, la venta de ayer no puede cambiar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                       // consecutivo: V-000001

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');            // vendedor
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();

            $table->decimal('subtotal', 12, 2)->default(0);                // suma de líneas sin impuesto
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->decimal('paid_total', 12, 2)->default(0);              // suma de los pagos recibidos
            $table->decimal('change_amount', 12, 2)->default(0);           // devuelta

            $table->decimal('cost_total', 12, 2)->default(0);              // costo de lo vendido
            $table->decimal('profit_total', 12, 2)->default(0);            // utilidad de la venta

            $table->string('status', 20)->default('pagada');               // pagada | anulada
            $table->text('notes')->nullable();

            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            $table->timestamp('sold_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sold_at']);
            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
