<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una venta puede pagarse con varios medios a la vez: parte en efectivo
 * y parte con tarjeta, por ejemplo. Por eso los pagos van en su propia
 * tabla y no como una columna de la venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods');

            $table->string('method_name', 100);           // copia del nombre
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable(); // voucher, aprobación, etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
