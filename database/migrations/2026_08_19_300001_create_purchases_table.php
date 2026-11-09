<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compras a proveedores.
 *
 * Una compra pasa por dos momentos: se registra (borrador) y se recibe.
 * El inventario solo se mueve al recibir, que es cuando la mercancía
 * entra físicamente a la bodega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                      // C-000001
            $table->string('invoice_number', 50)->nullable();            // factura del proveedor

            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('user_id')->constrained('users');          // quién la registró
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // borrador | recibida | anulada
            $table->string('status', 20)->default('borrador');
            $table->text('notes')->nullable();

            $table->date('ordered_at');
            $table->timestamp('received_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'ordered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
