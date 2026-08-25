<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lo que la tienda pública necesita de la base, antes de escribir una sola
 * vista.
 *
 * — `slug`: la dirección amable del producto. `/producto/cuaderno-argollado`
 *   en vez de `/producto/47`. Se decide AHORA porque cambiar direcciones
 *   después rompe los enlaces que la gente guardó y lo que Google indexó.
 *
 * — `is_public`: qué se publica. Nace en `false` a propósito: nada sale a
 *   internet por accidente, todo se publica a mano. Lo contrario —que todo
 *   salga por defecto— es como se filtran los productos de prueba.
 *
 * — Índices: el catálogo público consulta «activos + públicos, ordenados
 *   por nombre» en cada visita. Sin índice compuesto, MySQL recorre la
 *   tabla entera cada vez.
 *
 * — Índice de texto completo: `LIKE '%café%'` no puede usar ningún índice,
 *   así que el buscador se arrastra en cuanto el catálogo crece. FULLTEXT
 *   sí lo aprovecha. Solo existe en MySQL, así que se salta en SQLite —que
 *   es donde corren las pruebas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $esMysql = DB::connection()->getDriverName() === 'mysql';

        /* ───────── Productos ───────── */
        Schema::table('products', function (Blueprint $tabla) {
            if (! Schema::hasColumn('products', 'slug')) {
                $tabla->string('slug', 220)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('products', 'is_public')) {
                $tabla->boolean('is_public')->default(false)->after('is_active');
            }
        });

        Schema::table('products', function (Blueprint $tabla) {
            // El listado público filtra por los dos a la vez y ordena por nombre.
            $tabla->index(['is_active', 'is_public', 'name'], 'products_publico_index');
        });

        /* ───────── Categorías ───────── */
        Schema::table('categories', function (Blueprint $tabla) {
            if (! Schema::hasColumn('categories', 'slug')) {
                $tabla->string('slug', 140)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('categories', 'is_public')) {
                $tabla->boolean('is_public')->default(true)->after('is_active');
            }
        });

        Schema::table('categories', function (Blueprint $tabla) {
            $tabla->index(['is_active', 'is_public', 'parent_id'], 'categories_publico_index');
        });

        /* ───────── Direcciones para lo que ya existe ───────── */
        $this->rellenarSlugs('products', 220);
        $this->rellenarSlugs('categories', 140);

        /* ───────── Buscador ───────── */
        if ($esMysql) {
            DB::statement('ALTER TABLE products ADD FULLTEXT products_texto_index (name, description)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products DROP INDEX products_texto_index');
        }

        Schema::table('products', function (Blueprint $tabla) {
            $tabla->dropIndex('products_publico_index');
            $tabla->dropColumn(['slug', 'is_public']);
        });

        Schema::table('categories', function (Blueprint $tabla) {
            $tabla->dropIndex('categories_publico_index');
            $tabla->dropColumn(['slug', 'is_public']);
        });
    }

    /**
     * Genera la dirección amable de cada registro que todavía no la tiene.
     *
     * Si dos productos se llaman igual, al segundo se le pega el id: la
     * columna es única y una colisión reventaría la migración a la mitad.
     */
    private function rellenarSlugs(string $tabla, int $largo): void
    {
        $usados = DB::table($tabla)->whereNotNull('slug')->pluck('slug')->flip();

        DB::table($tabla)
            ->whereNull('slug')
            ->orderBy('id')
            ->select('id', 'name')
            ->chunkById(200, function ($filas) use ($tabla, $largo, &$usados) {
                foreach ($filas as $fila) {
                    $base = Str::limit(Str::slug($fila->name), $largo - 12, '');

                    if ($base === '') {
                        $base = 'registro';
                    }

                    $slug = $base;

                    if ($usados->has($slug)) {
                        $slug = $base . '-' . $fila->id;
                    }

                    $usados[$slug] = true;

                    DB::table($tabla)->where('id', $fila->id)->update(['slug' => $slug]);
                }
            });
    }
};
