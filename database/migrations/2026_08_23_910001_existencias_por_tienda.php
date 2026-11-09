<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 4 · Fase 2 — El stock deja de vivir en el producto.
 *
 * ── Por qué ──
 *
 * `products.stock` es una columna: un número por producto. Con dos
 * locales eso deja de significar algo — hay 12 martillos en el centro y 3
 * en el norte, y «el stock del martillo» no existe.
 *
 * La fila de `existencias` responde tres preguntas a la vez sobre un
 * producto en un local: cuánto hay, desde cuánto hay que reponer, y a qué
 * precio se vende ahí. Y una cuarta por omisión: **si no hay fila, ese
 * local no vende ese producto**. Es lo que permite que la ferretería del
 * centro maneje cemento y la del norte no, sin duplicar el catálogo.
 *
 * ── Por qué las columnas viejas se RENOMBRAN en vez de borrarse ──
 *
 * Hay 92 líneas en 27 archivos que leen `stock`. Si la columna
 * desapareciera, las que se me escapen devolverían null en silencio y el
 * sistema mostraría ceros donde hay mercancía — el peor error posible en
 * un inventario, porque parece un dato.
 *
 * Renombrándola a `stock_total`, cualquier consulta que se me escape
 * revienta con «Unknown column 'stock'» y se arregla en el acto. Un error
 * ruidoso es infinitamente mejor que un número equivocado.
 *
 * Y la columna sigue sirviendo para algo: guarda la suma de todos los
 * locales, que es lo que necesita el informe consolidado sin tener que
 * sumar la tabla entera cada vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('existencias')) {
            Schema::create('existencias', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
                $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();

                // decimal(12,3) como el kardex y los renglones de venta: si
                // se vende medio kilo, la existencia tiene que poder decirlo.
                $t->decimal('stock', 12, 3)->default(0);
                $t->decimal('min_stock', 12, 3)->default(0);

                /**
                 * Precio propio del local. Vacío = usa el del producto.
                 *
                 * Se construye completo desde el primer día, pero viene
                 * apagado: nadie tiene que mantener precios local por local
                 * a menos que quiera.
                 */
                $t->decimal('precio', 12, 2)->nullable();

                // Desactivar no borra: la fila queda y conserva el histórico.
                $t->boolean('activo')->default(true);

                // «Pasillo 3, estante B». Lo pidió el primer bodeguero que
                // tuvo que buscar un tornillo entre ochocientas referencias.
                $t->string('ubicacion', 60)->nullable();

                $t->timestamps();

                $t->unique(['tienda_id', 'product_id']);
                $t->index(['tienda_id', 'activo']);
                $t->index('product_id');
            });
        }

        $this->mudarElStock();
        $this->renombrarLasViejas();
    }

    /**
     * Cada producto estrena su fila en la tienda principal de su empresa,
     * con el stock que tenía.
     *
     * Se hace por lotes: un catálogo de supermercado son miles de filas y
     * cargarlas todas en memoria de una es como se agotan los procesos de
     * PHP en un hosting compartido.
     */
    private function mudarElStock(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'stock')) {
            return;
        }

        // Para cada empresa, cuál es su local principal.
        $principales = DB::table('tiendas')
            ->whereNull('deleted_at')
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->get(['id', 'empresa_id'])
            ->groupBy('empresa_id')
            ->map(fn ($t) => $t->first()->id);

        $ahora = now();

        DB::table('products')->orderBy('id')->chunkById(500, function ($productos) use ($principales, $ahora) {
            $filas = [];

            foreach ($productos as $p) {
                $tiendaId = $principales[$p->empresa_id] ?? null;

                if (! $tiendaId) {
                    continue;
                }

                $filas[] = [
                    'tienda_id'  => $tiendaId,
                    'product_id' => $p->id,
                    'stock'      => $p->stock,
                    'min_stock'  => $p->min_stock,
                    'precio'     => null,     // hereda el del producto
                    'activo'     => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            if ($filas !== []) {
                // insertOrIgnore por si la migración se corre dos veces:
                // el único (tienda, producto) se encarga de no duplicar.
                DB::table('existencias')->insertOrIgnore($filas);
            }
        });
    }

    /**
     * Las columnas viejas pasan a ser el total de todos los locales.
     *
     * El valor que tienen hoy ya ES ese total —había un solo local— así
     * que no hay que recalcular nada: solo cambiarles el nombre para que
     * digan la verdad.
     */
    private function renombrarLasViejas(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'stock')) {
            Schema::table('products', function (Blueprint $t) {
                $t->renameColumn('stock', 'stock_total');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'min_stock')) {
            Schema::table('products', function (Blueprint $t) {
                $t->renameColumn('min_stock', 'min_stock_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'stock_total')) {
            Schema::table('products', function (Blueprint $t) {
                $t->renameColumn('stock_total', 'stock');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'min_stock_total')) {
            Schema::table('products', function (Blueprint $t) {
                $t->renameColumn('min_stock_total', 'min_stock');
            });
        }

        Schema::dropIfExists('existencias');
    }
};
