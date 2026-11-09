<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('barcode', 50)->nullable()->unique();   // codigo_barras
            $table->string('image_url')->nullable();

            // Claves foráneas al nivel 0
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()
                  ->constrained('units')->nullOnDelete();

            // Precio base — AÑADIDO (el esquema viejo lo tenía por tienda).
            // Si algún día usas precio por tienda, quita estas dos líneas.
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('cost', 10, 2)->default(0);

            $table->boolean('is_active')->default(true);            // estado

            // Auditoría: qué usuario lo creó / editó
            $table->foreignId('created_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                  ->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
