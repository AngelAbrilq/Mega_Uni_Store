<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');

            $table->string('name', 200);                    // copia del nombre
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2);            // lo que cobra el proveedor
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);

            // ¿Actualizar el costo del producto al recibir?
            $table->boolean('update_cost')->default(true);

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
