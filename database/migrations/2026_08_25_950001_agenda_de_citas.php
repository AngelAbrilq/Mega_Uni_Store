<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La agenda: quién atiende, cuándo, y a quién.
 *
 * ── Las cuatro piezas ──
 *
 * `recursos` es quien atiende. Puede ser una persona —un estilista, un
 * técnico— o una cosa: una silla, una cabina, un consultorio. Se modela
 * igual porque el problema es el mismo: una sola cita a la vez.
 *
 * `horarios` es cuándo trabaja cada recurso, por día de la semana. Es lo
 * que permite decir «el martes no hay» sin que nadie tenga que acordarse.
 *
 * `bloqueos` son los huecos puntuales: vacaciones, un festivo, la hora del
 * almuerzo, «hoy salgo a las 3». Van aparte del horario porque el horario
 * es la regla y esto es la excepción — y mezclarlos obliga a reescribir la
 * regla cada vez que alguien se enferma.
 *
 * `citas` es lo que se agenda.
 *
 * ── Por qué se guarda `fin` y no solo la duración ──
 *
 * Porque la pregunta que más se hace la agenda es «¿esta franja choca con
 * otra?», y con `inicio` y `fin` es una comparación que la base resuelve
 * con un índice. Con duración habría que sumar en cada fila de cada
 * consulta, y el índice no serviría de nada.
 *
 * Cuesta una columna que se puede calcular. A cambio, la comprobación de
 * solapes —que corre en cada intento de agendar— es inmediata.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiendas')) {
            return;
        }

        /* ═══════════════ Servicios con duración ═══════════════ */
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'duracion_minutos')) {
            Schema::table('products', function (Blueprint $t) {
                /**
                 * En nulo = no es un servicio que se agenda.
                 *
                 * Así el mismo catálogo sirve para la ferretería y para la
                 * peluquería: un martillo no tiene duración, un corte de
                 * dama sí. Y la agenda solo ofrece los que la tienen.
                 */
                $t->unsignedSmallInteger('duracion_minutos')->nullable()->after('cost');
                $t->index('duracion_minutos');
            });
        }

        /* ═══════════════ recursos ═══════════════ */
        if (! Schema::hasTable('recursos')) {
            Schema::create('recursos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();

                $t->string('nombre', 120);
                $t->string('tipo', 20)->default('persona');   // persona | espacio | equipo

                /**
                 * Su color en el calendario.
                 *
                 * No es adorno: en una agenda con seis estilistas, el color
                 * es lo único que deja leer la pantalla de un vistazo. El
                 * texto se lee después, cuando ya se sabe dónde mirar.
                 */
                $t->string('color', 7)->default('#2E6EA8');

                // Si el recurso es una persona con cuenta, se enlaza — para
                // que pueda ver su propia agenda al entrar.
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                $t->boolean('activo')->default(true);
                $t->unsignedSmallInteger('orden')->default(0);
                $t->string('notas', 255)->nullable();

                $t->timestamps();
                $t->softDeletes();

                $t->index(['tienda_id', 'activo', 'orden']);
            });
        }

        /* ═══════════════ horarios ═══════════════ */
        if (! Schema::hasTable('horarios')) {
            Schema::create('horarios', function (Blueprint $t) {
                $t->id();
                $t->foreignId('recurso_id')->constrained('recursos')->cascadeOnDelete();

                // 0 = domingo … 6 = sábado, como devuelve Carbon.
                $t->unsignedTinyInteger('dia_semana');

                $t->time('desde');
                $t->time('hasta');

                $t->timestamps();

                /**
                 * Sin único a propósito: un recurso puede tener DOS franjas
                 * el mismo día —de 8 a 12 y de 2 a 6—, que es exactamente
                 * como trabaja medio comercio del país. El único impediría
                 * la jornada partida.
                 */
                $t->index(['recurso_id', 'dia_semana']);
            });
        }

        /* ═══════════════ bloqueos ═══════════════ */
        if (! Schema::hasTable('bloqueos')) {
            Schema::create('bloqueos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('recurso_id')->constrained('recursos')->cascadeOnDelete();

                $t->dateTime('inicio');
                $t->dateTime('fin');
                $t->string('motivo', 150)->nullable();

                $t->timestamps();

                $t->index(['recurso_id', 'inicio', 'fin']);
            });
        }

        /* ═══════════════ citas ═══════════════ */
        if (! Schema::hasTable('citas')) {
            Schema::create('citas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
                $t->string('numero', 20);

                $t->foreignId('recurso_id')->constrained('recursos');
                $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

                // El servicio. Nulo cuando es una cita suelta («revisión»).
                $t->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

                $t->string('titulo', 150);

                $t->dateTime('inicio');
                $t->dateTime('fin');

                /**
                 * pendiente | confirmada | atendida | no_llego | cancelada
                 *
                 * `no_llego` existe separada de `cancelada` porque no son lo
                 * mismo para el negocio: una cancelación avisada deja el
                 * cupo libre para otro; un plantón se pierde entero. Si se
                 * guardaran juntas, el dueño no podría ver a quién le pasa
                 * seguido — que es el dato que sirve para decidir si le pide
                 * abono la próxima vez.
                 */
                $t->string('estado', 20)->default('pendiente');

                // Copia del precio del día. El del servicio cambia; lo que
                // se le prometió a este cliente, no.
                $t->decimal('precio', 12, 2)->default(0);

                $t->string('notas', 500)->nullable();

                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                // La venta que salió de esta cita, si ya se cobró.
                $t->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();

                $t->timestamp('recordada_en')->nullable();
                $t->timestamp('atendida_en')->nullable();

                $t->timestamps();
                $t->softDeletes();

                $t->unique(['tienda_id', 'numero']);

                /**
                 * El índice del que depende todo.
                 *
                 * Cada intento de agendar pregunta «¿este recurso tiene algo
                 * entre estas dos horas?». Sin este índice, esa consulta
                 * recorre la tabla entera — y se hace en cada clic de la
                 * pantalla de agenda, no una vez al día.
                 */
                $t->index(['recurso_id', 'inicio', 'fin']);
                $t->index(['tienda_id', 'inicio']);
                $t->index('estado');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
        Schema::dropIfExists('bloqueos');
        Schema::dropIfExists('horarios');
        Schema::dropIfExists('recursos');

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'duracion_minutos')) {
            Schema::table('products', function (Blueprint $t) {
                $t->dropIndex(['duracion_minutos']);
                $t->dropColumn('duracion_minutos');
            });
        }
    }
};
