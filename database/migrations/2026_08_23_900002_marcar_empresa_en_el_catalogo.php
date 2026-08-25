<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 4 · Fase 1 — El catálogo y la gente pasan a tener dueño.
 *
 * Estas son las tablas del nivel EMPRESA: lo que un negocio comparte
 * entre todos sus locales. El producto es el mismo en el local del centro
 * y en el del norte; lo que cambia por local es la existencia, y eso es
 * la fase 2.
 *
 * ── La parte que de verdad importa: los índices únicos ──
 *
 * Hoy `products.barcode` es único en TODA la tabla. Con un solo negocio
 * eso está bien. Con dos, significa que si la ferretería registra el
 * código de barras de una Coca-Cola, el supermercado ya no puede
 * registrarlo — y va a recibir un error que no entiende, sobre un
 * producto que no ha visto nunca.
 *
 * Lo mismo con el SKU, con la dirección pública (slug), con el correo del
 * cliente y con su documento. Todos esos únicos tienen que pasar a ser
 * únicos DENTRO de la empresa. Es la clase de detalle que no se nota en
 * desarrollo —donde siempre hay un solo negocio— y aparece el día que
 * entra el segundo cliente.
 *
 * `users.email` es la excepción y se queda global: una persona tiene un
 * solo usuario aunque trabaje en dos negocios.
 */
return new class extends Migration
{
    /** Tablas que pasan a ser de la empresa. */
    private const TABLAS = [
        'products',
        'categories',
        'units',
        'taxes',
        'payment_methods',
        'attributes',
        'suppliers',
        'customers',
    ];

    /**
     * Únicos que hay que reemplazar por «único dentro de la empresa».
     * [tabla => [ [nombre del índice viejo, columnas], … ]]
     */
    private const UNICOS = [
        'products'   => [
            ['products_barcode_unique', ['barcode']],
            ['products_sku_unique',     ['sku']],
            ['products_slug_unique',    ['slug']],
        ],
        'categories' => [
            ['categories_slug_unique',  ['slug']],
        ],
        'customers'  => [
            ['customers_email_unique',           ['email']],
            ['customers_document_number_unique', ['document_number']],
        ],
    ];

    public function up(): void
    {
        $empresaId = (int) (DB::table('empresas')->min('id') ?: 1);

        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'empresa_id')) {
                continue;
            }

            /* 1. La columna entra nullable para que la tabla —que ya tiene
                  filas— no rechace el ALTER. */
            Schema::table($tabla, function (Blueprint $t) {
                $t->unsignedBigInteger('empresa_id')->nullable()->after('id');
            });

            /* 2. Todo lo que existe es del negocio que ya estaba. */
            DB::table($tabla)->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);

            /* 3. Ahora sí: obligatoria, con su llave foránea e índice.
                  El orden importa — al revés, el paso 3 fallaría con las
                  filas viejas todavía en nulo. */
            Schema::table($tabla, function (Blueprint $t) {
                $t->unsignedBigInteger('empresa_id')->nullable(false)->change();
                $t->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
                $t->index('empresa_id');
            });
        }

        $this->reemplazarUnicos();
    }

    /**
     * Cambia «único en la tabla» por «único dentro de la empresa».
     *
     * Cada paso va en su propio try: en una instalación que venga de una
     * versión anterior puede faltar alguno de estos índices —el de
     * `document_number`, por ejemplo, solo se creó si no había clientes
     * repetidos—. Que falte uno no es motivo para dejar la migración a
     * medias.
     */
    private function reemplazarUnicos(): void
    {
        foreach (self::UNICOS as $tabla => $indices) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($indices as [$nombreViejo, $columnas]) {
                foreach ($columnas as $columna) {
                    if (! Schema::hasColumn($tabla, $columna)) {
                        continue 2;
                    }
                }

                try {
                    Schema::table($tabla, function (Blueprint $t) use ($nombreViejo) {
                        $t->dropUnique($nombreViejo);
                    });
                } catch (\Throwable $e) {
                    // No existía. Seguimos: lo que importa es que el nuevo sí quede.
                }

                try {
                    Schema::table($tabla, function (Blueprint $t) use ($columnas) {
                        $t->unique(array_merge(['empresa_id'], $columnas));
                    });
                } catch (\Throwable $e) {
                    // Ya estaba creado de una corrida anterior.
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::UNICOS as $tabla => $indices) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($indices as [$nombreViejo, $columnas]) {
                try {
                    Schema::table($tabla, function (Blueprint $t) use ($columnas) {
                        $t->dropUnique(array_merge(['empresa_id'], $columnas));
                    });
                } catch (\Throwable $e) {
                }

                try {
                    Schema::table($tabla, function (Blueprint $t) use ($columnas, $nombreViejo) {
                        $t->unique($columnas, $nombreViejo);
                    });
                } catch (\Throwable $e) {
                }
            }
        }

        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'empresa_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $t) {
                // Con array Laravel deduce el nombre del índice a partir de
                // la columna, que es justo lo que se quiere aquí.
                $t->dropForeign(['empresa_id']);
                $t->dropColumn('empresa_id');
            });
        }
    }
};
