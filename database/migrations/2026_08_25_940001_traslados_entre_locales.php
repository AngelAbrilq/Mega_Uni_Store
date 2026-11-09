<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mercancía que se mueve de un local a otro.
 *
 * ── Por qué una tabla, si el kardex ya lo registra ──
 *
 * Porque un traslado son DOS movimientos —una salida allá y una entrada
 * acá— y en el kardex quedan como dos líneas sueltas. Basta con que
 * alguien anule una para que el inventario deje de cuadrar sin que nada
 * avise: el sistema tendría veinte martillos de más y veinte de menos, y
 * ninguna forma de saber que eran el mismo movimiento.
 *
 * Esta tabla es lo que los amarra. Los dos movimientos apuntan a la misma
 * fila por `source`, y desde el traslado se puede llegar a los dos.
 *
 * ── Por qué se guarda el número del documento ──
 *
 * Porque el que carga la camioneta necesita un papel con un número, y
 * porque cuando la mercancía no llega —que pasa— hay que poder preguntar
 * por «el traslado T-000014» y no por «el de los martillos del martes».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('traslados') || ! Schema::hasTable('tiendas')) {
            return;
        }

        Schema::create('traslados', function (Blueprint $t) {
            $t->id();
            $t->string('numero', 20);

            $t->foreignId('product_id')->constrained('products');

            /**
             * Origen y destino.
             *
             * `restrictOnDelete` a propósito: borrar un local que tiene
             * traslados dejaría el historial de inventario contando media
             * historia. Si de verdad hay que borrarlo, primero se resuelve
             * qué pasa con sus movimientos.
             */
            $t->foreignId('desde_tienda_id')->constrained('tiendas')->restrictOnDelete();
            $t->foreignId('hacia_tienda_id')->constrained('tiendas')->restrictOnDelete();

            $t->decimal('cantidad', 12, 3);

            // Copia del costo del día. El costo del producto cambia; el
            // valor de lo que se movió ese día, no.
            $t->decimal('costo_unitario', 12, 2)->default(0);

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('notas', 255)->nullable();

            $t->timestamps();

            /**
             * El número es único DENTRO del local que lo emite, igual que
             * las ventas y las compras: dos negocios distintos pueden tener
             * cada uno su T-000001 sin pisarse.
             */
            $t->unique(['desde_tienda_id', 'numero']);
            $t->index(['product_id', 'created_at']);
            $t->index('hacia_tienda_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traslados');
    }
};
