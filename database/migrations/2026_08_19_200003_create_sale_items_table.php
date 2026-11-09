<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renglones de la venta.
 *
 * Se copian nombre, SKU, precio, costo y tarifa de impuesto tal como
 * estaban en el momento de vender. Si el producto se edita o se elimina
 * después, la factura sigue diciendo lo que decía ese día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Copia del producto en el momento de la venta
            $table->string('name', 200);
            $table->string('sku', 50)->nullable();

            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);        // precio sin impuesto
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);

            $table->string('tax_name', 80)->nullable();
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);

            $table->decimal('subtotal', 12, 2);          // (precio × cantidad) − descuento
            $table->decimal('total', 12, 2);             // subtotal + impuesto

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
