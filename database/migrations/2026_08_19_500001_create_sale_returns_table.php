<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devoluciones parciales.
 *
 * Anular una venta es todo o nada. Una devolución permite recibir de
 * vuelta un artículo de tres sin deshacer la venta completa: la venta
 * original queda intacta y la devolución es un documento aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                 // D-000001

            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('user_id')->constrained('users');      // quién la recibió

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);             // lo que se le devuelve al cliente

            $table->string('reason', 255);
            $table->boolean('restock')->default(true);               // ¿vuelve a bodega o se da de baja?

            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();

            $table->index('returned_at');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            // Cuánto de este renglón ya se devolvió; evita devolver dos veces.
            $table->decimal('returned_quantity', 12, 3)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });

        Schema::dropIfExists('sale_returns');
    }
};
