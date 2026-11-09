<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué paga cada negocio, qué le cabe, y cómo se movió el ingreso.
 *
 * ── Las tres tablas y por qué son tres ──
 *
 * `planes` es el catálogo: Básico, Pro, Empresa. Lo que cuesta y lo que
 * incluye.
 *
 * En `empresas` se guarda a cuál está suscrito cada cliente, con dos
 * columnas que parecen redundantes y no lo son: `periodo` —mensual o
 * anual— y `precio_pactado`. Esa última es la que hace que esto sirva en
 * la vida real: a William se le va a cobrar lo que se le prometió, no lo
 * que diga la tabla de precios el día que alguien la actualice. Un
 * descuento acordado no se puede perder porque se subió la lista.
 *
 * `mrr_movimientos` es la bitácora del ingreso. Cada vez que un cliente
 * entra, sube, baja o se va, queda una línea.
 *
 * ── Por qué esa tercera tabla, si el MRR se puede sumar ──
 *
 * Porque sumar responde «cuánto tengo hoy» y esa es la pregunta menos
 * útil de las dos. La que sirve es «por qué cambió»: si el MRR pasó de
 * $905.000 a $743.000, sumar solo dice que bajó. La bitácora dice que
 * entraron tres clientes nuevos Y ADEMÁS un Empresa se bajó a Pro — que
 * es un problema distinto y se arregla distinto.
 *
 * Sin esta tabla ese dato no se puede reconstruir después: el estado de
 * hoy no se acuerda de por dónde pasó.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ═══════════════ planes ═══════════════ */
        if (! Schema::hasTable('planes')) {
            Schema::create('planes', function (Blueprint $t) {
                $t->id();
                $t->string('nombre', 80);
                $t->string('slug', 90)->unique();
                $t->string('descripcion', 200)->nullable();

                /* ── Precio ──
                   Se guardan los dos por separado y no un porcentaje de
                   descuento: el precio anual es una decisión comercial, no
                   una cuenta. El día que el anual deje de ser exactamente
                   el mensual menos 20%, no hay que tocar código. */
                $t->decimal('precio_mensual', 12, 2)->default(0);
                $t->decimal('precio_anual', 12, 2)->default(0);

                // Lo que cuesta cada sede después de las que incluye el plan.
                $t->decimal('precio_tienda_extra', 12, 2)->default(0);

                /* ── Límites ──
                   En nulo = sin límite. Es distinto de cero, que sería
                   «ninguno», y la diferencia importa: el plan Empresa no
                   limita productos, no permite cero productos. */
                $t->unsignedInteger('max_tiendas')->nullable();
                $t->unsignedInteger('max_usuarios')->nullable();
                $t->unsignedInteger('max_productos')->nullable();
                $t->unsignedInteger('max_ventas_mes')->nullable();

                /* Funciones que el plan habilita, por nombre. Es json y no
                   una columna por función porque la lista va a crecer
                   —agenda, portal público, facturación DIAN— y no vale una
                   migración por cada una. */
                $t->json('funciones')->nullable();

                $t->unsignedSmallInteger('dias_prueba')->default(0);

                // El plan con el que corre la demostración de 2 h 30.
                $t->boolean('es_demo')->default(false);

                $t->boolean('visible')->default(true);
                $t->unsignedSmallInteger('orden')->default(0);

                $t->timestamps();

                $t->index(['visible', 'orden']);
            });
        }

        /* ═══════════════ empresas ═══════════════ */
        if (Schema::hasTable('empresas')) {
            Schema::table('empresas', function (Blueprint $t) {
                if (! Schema::hasColumn('empresas', 'plan_id')) {
                    /**
                     * En nulo = sin plan = SIN LÍMITES.
                     *
                     * Se eligió así a propósito. La alternativa —que sin
                     * plan no se pueda hacer nada— convertiría esta
                     * migración en un apagón: todas las empresas que ya
                     * existen quedarían bloqueadas hasta que alguien les
                     * asigne plan a mano. Un cambio de facturación no puede
                     * dejar a nadie sin poder vender.
                     */
                    $t->foreignId('plan_id')->nullable()->after('rubro')
                        ->constrained('planes')->nullOnDelete();
                }

                if (! Schema::hasColumn('empresas', 'periodo')) {
                    $t->string('periodo', 10)->default('mensual')->after('plan_id');
                }

                if (! Schema::hasColumn('empresas', 'precio_pactado')) {
                    // Lo que se le prometió a ESTE cliente. Manda sobre la
                    // lista de precios.
                    $t->decimal('precio_pactado', 12, 2)->nullable()->after('periodo');
                }

                if (! Schema::hasColumn('empresas', 'tiendas_contratadas')) {
                    $t->unsignedSmallInteger('tiendas_contratadas')->default(1)->after('precio_pactado');
                }

                if (! Schema::hasColumn('empresas', 'suscrita_desde')) {
                    $t->date('suscrita_desde')->nullable()->after('tiendas_contratadas');
                }
            });
        }

        /* ═══════════════ mrr_movimientos ═══════════════ */
        if (! Schema::hasTable('mrr_movimientos')) {
            Schema::create('mrr_movimientos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

                // nuevo | expansion | contraccion | baja | reactivacion
                $t->string('tipo', 20);

                $t->foreignId('plan_antes_id')->nullable()->constrained('planes')->nullOnDelete();
                $t->foreignId('plan_despues_id')->nullable()->constrained('planes')->nullOnDelete();

                /* Se guardan los tres y no solo el delta. El delta se puede
                   calcular, sí — pero teniendo los tres, una fila sola ya
                   cuenta la historia completa sin ir a buscar la anterior. */
                $t->decimal('mrr_antes', 12, 2)->default(0);
                $t->decimal('mrr_despues', 12, 2)->default(0);
                $t->decimal('delta', 12, 2)->default(0);

                $t->string('motivo', 200)->nullable();

                // La fecha del hecho, no la de la fila: una baja se puede
                // registrar tres días después y sigue siendo del día que fue.
                $t->date('ocurrio_en');

                $t->timestamps();

                $t->index(['ocurrio_en', 'tipo']);
                $t->index(['empresa_id', 'ocurrio_en']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mrr_movimientos');

        if (Schema::hasTable('empresas')) {
            Schema::table('empresas', function (Blueprint $t) {
                if (Schema::hasColumn('empresas', 'plan_id')) {
                    $t->dropForeign(['plan_id']);
                    $t->dropColumn('plan_id');
                }

                foreach (['periodo', 'precio_pactado', 'tiendas_contratadas', 'suscrita_desde'] as $columna) {
                    if (Schema::hasColumn('empresas', $columna)) {
                        $t->dropColumn($columna);
                    }
                }
            });
        }

        Schema::dropIfExists('planes');
    }
};
