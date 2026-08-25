<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `php artisan mus:verificar`
 *
 * Comprueba, contra la base de datos de verdad, que el multi-tienda quedó
 * bien puesto. No es una prueba de laboratorio: son las preguntas que uno
 * haría a mano después de una migración grande, hechas todas de una y sin
 * que se escape ninguna.
 *
 * Está pensado para correrse después de CADA fase del paso 4. Las
 * comprobaciones de las fases que todavía no existen se saltan solas y lo
 * dicen, en vez de fallar.
 *
 * La pregunta que responde es una sola: ¿hay alguna fila que se haya
 * quedado sin dueño? Una fila sin empresa no la ve nadie —desaparece del
 * sistema el día que el filtro se encienda— y una venta sin tienda no
 * cuadra la caja de ningún local.
 */
class VerificarMultitienda extends Command
{
    protected $signature = 'mus:verificar {--detalle : muestra los ids de las filas con problema}';

    protected $description = 'Revisa que empresas, tiendas, contadores e índices quedaron bien';

    /** Tablas del nivel empresa. */
    private const DE_EMPRESA = [
        'products', 'categories', 'units', 'taxes',
        'payment_methods', 'attributes', 'suppliers', 'customers',
    ];

    /** Tablas del nivel tienda. */
    private const DE_TIENDA = [
        'sales', 'purchases', 'sale_returns', 'cash_sessions', 'stock_movements',
    ];

    private int $problemas = 0;

    public function handle(): int
    {
        $this->newLine();

        if (! Schema::hasTable('empresas')) {
            $this->components->error('No existen las tablas de empresas. ¿Corriste php artisan migrate?');

            return self::FAILURE;
        }

        $this->panorama();
        $this->sinDueno();
        $this->usuariosEnlazados();
        $this->contadores();
        $this->existencias();
        $this->cruces();
        $this->roles();
        $this->planes();
        $this->indices();

        $this->newLine();

        if ($this->problemas === 0) {
            $this->components->info('Todo cuadra. Ninguna fila se quedó sin dueño.');

            return self::SUCCESS;
        }

        $this->components->error("Hay {$this->problemas} cosas por revisar. Ninguna se arregla sola.");

        return self::FAILURE;
    }

    /* ═══════════════ 1. Qué hay ═══════════════ */

    private function panorama(): void
    {
        $this->components->info('Qué hay en la base');

        $empresas = DB::table('empresas')->whereNull('deleted_at')->get();

        foreach ($empresas as $e) {
            $tiendas = DB::table('tiendas')
                ->where('empresa_id', $e->id)
                ->whereNull('deleted_at')
                ->get();

            $usuarios = DB::table('empresa_usuario')->where('empresa_id', $e->id)->count();

            $this->line(sprintf(
                '  <fg=cyan>#%d %s</>  ·  %s  ·  %d %s  ·  %d %s',
                $e->id,
                $e->nombre,
                $e->estado . ($e->es_demo ? ' (demo)' : ''),
                $tiendas->count(),
                $tiendas->count() === 1 ? 'tienda' : 'tiendas',
                $usuarios,
                $usuarios === 1 ? 'usuario' : 'usuarios'
            ));

            foreach ($tiendas as $t) {
                $prefijo = $t->codigo ? "prefijo {$t->codigo}-" : 'sin prefijo';
                $nit     = $t->nit ? "NIT propio {$t->nit}" : 'NIT de la empresa';

                $this->line(sprintf(
                    '       └ %s%s  ·  %s  ·  %s',
                    $t->nombre,
                    $t->es_principal ? ' (principal)' : '',
                    $prefijo,
                    $nit
                ));
            }
        }

        $this->newLine();
    }

    /* ═══════════════ 2. Filas sin dueño ═══════════════ */

    private function sinDueno(): void
    {
        $this->components->info('Filas sin dueño');

        $huerfanas = 0;

        foreach ([['empresa_id', self::DE_EMPRESA], ['tienda_id', self::DE_TIENDA]] as [$columna, $tablas]) {
            foreach ($tablas as $tabla) {
                if (! Schema::hasTable($tabla)) {
                    continue;
                }

                if (! Schema::hasColumn($tabla, $columna)) {
                    $this->line("  <fg=yellow>·</> {$tabla} todavía no tiene {$columna} — falta una migración");
                    $this->problemas++;

                    continue;
                }

                $total = DB::table($tabla)->count();
                $sin   = DB::table($tabla)->whereNull($columna)->count();

                if ($sin > 0) {
                    $this->line("  <fg=red>✗</> {$tabla}: {$sin} de {$total} filas sin {$columna}");
                    $huerfanas += $sin;
                    $this->problemas++;

                    if ($this->option('detalle')) {
                        $ids = DB::table($tabla)->whereNull($columna)->limit(20)->pluck('id')->implode(', ');
                        $this->line("      ids: {$ids}");
                    }
                } else {
                    $this->line(sprintf('  <fg=green>✓</> %-18s %s filas, todas con %s',
                        $tabla, number_format($total, 0, ',', '.'), $columna));
                }
            }
        }

        if ($huerfanas === 0) {
            $this->line('  <fg=green>Ninguna fila quedó suelta.</>');
        }

        $this->newLine();
    }

    /* ═══════════════ 3. Usuarios ═══════════════ */

    private function usuariosEnlazados(): void
    {
        $this->components->info('Usuarios');

        if (! Schema::hasTable('empresa_usuario')) {
            $this->line('  <fg=yellow>·</> falta la tabla de enlace');
            $this->problemas++;

            return;
        }

        $sueltos = DB::table('users')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('empresa_usuario')
                  ->whereColumn('empresa_usuario.user_id', 'users.id');
            })
            ->get(['id', 'name', 'email']);

        if ($sueltos->isNotEmpty()) {
            $this->line("  <fg=red>✗</> {$sueltos->count()} usuarios no pertenecen a ninguna empresa:");

            foreach ($sueltos as $u) {
                $this->line("      #{$u->id} {$u->name} · {$u->email}");
            }

            $this->line('      <fg=yellow>Solución:</> php artisan db:seed --class=EmpresaSeeder');
            $this->problemas++;
        } else {
            $total = DB::table('users')->count();
            $this->line("  <fg=green>✓</> los {$total} usuarios pertenecen a una empresa");
        }

        // Un negocio sin dueño es un negocio al que nadie puede administrar.
        $sinDueno = DB::table('empresas')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('empresa_usuario')
                  ->whereColumn('empresa_usuario.empresa_id', 'empresas.id')
                  ->where('empresa_usuario.es_dueno', true);
            })
            ->pluck('nombre');

        if ($sinDueno->isNotEmpty()) {
            $this->line('  <fg=red>✗</> empresas sin dueño: ' . $sinDueno->implode(', '));
            $this->problemas++;
        } else {
            $this->line('  <fg=green>✓</> todas las empresas tienen dueño');
        }

        $this->newLine();
    }

    /* ═══════════════ 4. Contadores ═══════════════ */

    /**
     * El contador nunca puede ir por DETRÁS del documento más alto: si va
     * atrás, la próxima venta intenta repetir un número que ya existe y se
     * cae contra el índice único, con el cliente enfrente.
     *
     * Ir por delante sí es normal y correcto —pasa cuando se anula el
     * último documento— así que eso no se marca como problema.
     */
    private function contadores(): void
    {
        $this->components->info('Contadores de documentos');

        if (! Schema::hasTable('counters') || ! Schema::hasColumn('counters', 'tienda_id')) {
            $this->line('  <fg=yellow>·</> los contadores todavía no son por tienda');
            $this->problemas++;

            return;
        }

        /**
         * [tabla, prefijo, columna del local, columna del número]
         *
         * Los traslados no siguen la convención de los demás documentos:
         * tienen DOS locales —de dónde sale y a dónde llega— así que su
         * columna se llama `desde_tienda_id`, y el consecutivo es el del
         * local que lo emite. Por eso las columnas se declaran aquí en vez
         * de darlas por sentadas.
         */
        $series = [
            'ventas'       => ['sales',        'V-', 'tienda_id',       'number'],
            'compras'      => ['purchases',    'C-', 'tienda_id',       'number'],
            'devoluciones' => ['sale_returns', 'D-', 'tienda_id',       'number'],
            'traslados'    => ['traslados',    'T-', 'desde_tienda_id', 'numero'],
        ];

        $tiendas = DB::table('tiendas')->whereNull('deleted_at')->get();

        foreach ($tiendas as $t) {
            foreach ($series as $serie => [$tabla, $prefijoSerie, $colTienda, $colNumero]) {
                if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $colTienda)) {
                    continue;
                }

                $contador = (int) DB::table('counters')
                    ->where('tienda_id', $t->id)
                    ->where('key', $serie)
                    ->value('value');

                $documentos = DB::table($tabla)->where($colTienda, $t->id)->count();

                if ($documentos === 0) {
                    continue;
                }

                $mayor = $this->numeroMasAlto($tabla, $t->id, $prefijoSerie, $t->codigo, $colTienda, $colNumero);

                if ($contador < $mayor) {
                    $this->line(sprintf(
                        '  <fg=red>✗</> %s · %s: el contador va en %d pero ya existe el %d',
                        $t->nombre, $serie, $contador, $mayor
                    ));
                    $this->problemas++;
                } else {
                    $this->line(sprintf(
                        '  <fg=green>✓</> %-14s %-13s contador %d, documento más alto %d (%d en total)',
                        $t->nombre, $serie, $contador, $mayor, $documentos
                    ));
                }
            }
        }

        $this->newLine();
    }

    /** «V-B-000123» → 123. Aguanta con prefijo de tienda y sin él. */
    private function numeroMasAlto(
        string $tabla,
        int $tiendaId,
        string $prefijoSerie,
        ?string $codigo,
        string $colTienda = 'tienda_id',
        string $colNumero = 'number',
    ): int {
        $numeros = DB::table($tabla)->where($colTienda, $tiendaId)->pluck($colNumero);
        $mayor   = 0;

        foreach ($numeros as $n) {
            $limpio = str_replace($prefijoSerie, '', (string) $n);

            if ($codigo) {
                $limpio = str_replace($codigo . '-', '', $limpio);
            }

            $mayor = max($mayor, (int) ltrim($limpio, '0'));
        }

        return $mayor;
    }

    /* ═══════════════ 5. Existencias ═══════════════ */

    /**
     * Las tres preguntas del inventario después de la mudanza.
     *
     *   1. ¿Se quedó algún producto sin fila en ningún local? Ese producto
     *      no lo puede vender nadie y desapareció del punto de venta.
     *   2. ¿`products.stock_total` sigue siendo la suma de los locales? Si
     *      no, el informe consolidado miente.
     *   3. ¿El kardex cuadra? El último movimiento de cada producto en cada
     *      local tiene que dejar el mismo saldo que dice la existencia. Si
     *      no cuadra, alguien tocó el inventario sin registrar el
     *      movimiento — y ese es el error que no se nota hasta que es tarde.
     */
    private function existencias(): void
    {
        $this->components->info('Inventario');

        if (! Schema::hasTable('existencias')) {
            $this->line('  <fg=yellow>·</> todavía no existe la tabla — falta la fase 2');

            return;
        }

        /* 1. Productos sin ninguna existencia */
        $huerfanos = DB::table('products')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('existencias')
                  ->whereColumn('existencias.product_id', 'products.id');
            })
            ->count();

        $productos = DB::table('products')->whereNull('deleted_at')->count();
        $filas     = DB::table('existencias')->count();

        if ($huerfanos > 0) {
            $this->line("  <fg=red>✗</> {$huerfanos} productos no están en ningún local — nadie los puede vender");
            $this->problemas++;
        } else {
            $this->line(sprintf('  <fg=green>✓</> %s productos · %s filas de existencia, ninguno suelto',
                number_format($productos, 0, ',', '.'), number_format($filas, 0, ',', '.')));
        }

        /* 2. El total del producto contra la suma de sus locales */
        if (Schema::hasColumn('products', 'stock_total')) {
            $descuadrados = DB::table('products')
                ->leftJoin(
                    DB::raw('(SELECT product_id, SUM(stock) AS suma FROM existencias GROUP BY product_id) e'),
                    'e.product_id', '=', 'products.id'
                )
                ->whereNull('products.deleted_at')
                ->whereRaw('ABS(products.stock_total - COALESCE(e.suma, 0)) > 0.001')
                ->count();

            if ($descuadrados > 0) {
                $this->line("  <fg=red>✗</> {$descuadrados} productos donde el total no es la suma de los locales");
                $this->line('      <fg=yellow>Se corrige solo</> con el siguiente movimiento de cada uno.');
                $this->problemas++;
            } else {
                $this->line('  <fg=green>✓</> el total de cada producto es la suma exacta de sus locales');
            }
        }

        /* 3. El kardex contra la existencia */
        if (! Schema::hasTable('stock_movements') || ! Schema::hasColumn('stock_movements', 'tienda_id')) {
            $this->newLine();

            return;
        }

        $malos = [];

        DB::table('existencias')->orderBy('id')->chunkById(500, function ($lote) use (&$malos) {
            foreach ($lote as $e) {
                $ultimo = DB::table('stock_movements')
                    ->where('tienda_id', $e->tienda_id)
                    ->where('product_id', $e->product_id)
                    ->orderByDesc('id')
                    ->value('balance_after');

                // Sin movimientos no hay nada que comparar: es un producto
                // que nunca se ha movido en ese local.
                if ($ultimo === null) {
                    continue;
                }

                if (abs((float) $ultimo - (float) $e->stock) > 0.001) {
                    $malos[] = [$e->tienda_id, $e->product_id, (float) $e->stock, (float) $ultimo];
                }
            }
        });

        if ($malos !== []) {
            $this->line('  <fg=red>✗</> ' . count($malos) . ' existencias no cuadran con su kardex:');

            foreach (array_slice($malos, 0, 10) as [$t, $prod, $saldo, $kardex]) {
                $this->line("      tienda {$t} · producto {$prod}: existencia {$saldo}, kardex {$kardex}");
            }

            $this->problemas++;
        } else {
            $this->line('  <fg=green>✓</> el kardex cuadra con la existencia en todos los locales');
        }

        $this->newLine();
    }

    /* ═══════════════ 6. Datos cruzados ═══════════════ */

    /**
     * Filas que apuntan a algo de otro negocio.
     *
     * Es el problema que el filtro global NO puede evitar por sí solo: si
     * una venta de la ferretería quedó apuntando a un cliente del
     * supermercado, las dos filas están bien marcadas cada una por su lado
     * —ninguna aparece como «sin dueño»— y sin embargo el recibo de
     * William saldría con el nombre de un cliente de Diego.
     *
     * Aquí no debería salir nada nunca. Si sale algo, es señal de una de
     * dos cosas: una migración que se llevó los datos mal, o un formulario
     * que aceptó un id que le mandaron a mano desde afuera.
     */
    private function cruces(): void
    {
        $this->components->info('Datos cruzados entre negocios');

        // [texto, tabla, columna que apunta, tabla apuntada]
        $revisiones = [
            ['ventas con un cliente de otro negocio',        'sales',           'customer_id', 'customers'],
            ['compras con un proveedor de otro negocio',     'purchases',       'supplier_id', 'suppliers'],
            ['existencias de un producto de otro negocio',   'existencias',     'product_id',  'products'],
            ['movimientos de un producto de otro negocio',   'stock_movements', 'product_id',  'products'],
        ];

        $revisados = 0;

        foreach ($revisiones as [$texto, $tabla, $columna, $destino]) {
            if (! Schema::hasTable($tabla) || ! Schema::hasTable($destino)) {
                continue;
            }

            if (! Schema::hasColumn($tabla, 'tienda_id') || ! Schema::hasColumn($destino, 'empresa_id')) {
                continue;
            }

            $revisados++;

            $malas = DB::table($tabla . ' as f')
                ->join('tiendas as t', 't.id', '=', 'f.tienda_id')
                ->join($destino . ' as d', 'd.id', '=', 'f.' . $columna)
                ->whereColumn('d.empresa_id', '!=', 't.empresa_id')
                ->count();

            $this->resultadoCruce($texto, $malas, $tabla . '.' . $columna);
        }

        /* Las líneas de venta no llevan tienda: se llega por su venta. */
        if (Schema::hasTable('sale_items') && Schema::hasTable('products')
            && Schema::hasColumn('sales', 'tienda_id')) {
            $revisados++;

            $malas = DB::table('sale_items as l')
                ->join('sales as v', 'v.id', '=', 'l.sale_id')
                ->join('tiendas as t', 't.id', '=', 'v.tienda_id')
                ->join('products as p', 'p.id', '=', 'l.product_id')
                ->whereColumn('p.empresa_id', '!=', 't.empresa_id')
                ->count();

            $this->resultadoCruce('líneas de venta con un producto de otro negocio', $malas, 'sale_items.product_id');
        }

        if ($revisados === 0) {
            $this->line('  <fg=yellow>·</> todavía no hay tablas que cruzar');
        }
    }

    private function resultadoCruce(string $texto, int $malas, string $donde): void
    {
        if ($malas === 0) {
            $this->line("  <fg=green>✓</> ninguna de las {$texto}");

            return;
        }

        $this->line("  <fg=red>✗</> {$malas} {$texto} — revisa {$donde}");
        $this->problemas++;
    }

    /* ═══════════════ 7. Roles ═══════════════ */

    /**
     * Que nadie se haya quedado sin permisos, y que los roles propios de un
     * negocio no se le hayan escapado a otro.
     *
     * La primera es la que importa. Cambiar cómo se buscan los roles es la
     * clase de cosa que no falla al migrar: falla el lunes, cuando el
     * cajero entra y no puede vender. Aquí se cuenta antes de que eso pase.
     */
    private function roles(): void
    {
        $this->components->info('Roles y permisos');

        if (! Schema::hasTable('roles')) {
            $this->line('  <fg=yellow>·</> todavía no hay tabla de roles');

            return;
        }

        if (! Schema::hasColumn('roles', 'empresa_id')) {
            $this->line('  <fg=yellow>·</> falta la migración de roles por empresa');

            return;
        }

        $delSistema = DB::table('roles')->whereNull('empresa_id')->count();
        $propios    = DB::table('roles')->whereNotNull('empresa_id')->count();

        $this->line("  <fg=green>✓</> {$delSistema} rol(es) del sistema · {$propios} propio(s) de algún negocio");

        if ($delSistema === 0) {
            $this->line('  <fg=red>✗</> no quedó ni un rol del sistema: nadie podría entrar');
            $this->problemas++;
        }

        /* ── Usuarios sin ningún rol ── */
        $sinRol = DB::table('users')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('model_has_roles')
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', \App\Models\User::class))
            ->count();

        if ($sinRol === 0) {
            $this->line('  <fg=green>✓</> todos los usuarios conservan su rol');
        } else {
            $this->line("  <fg=red>✗</> {$sinRol} usuario(s) sin ningún rol — no podrían hacer nada al entrar");
            $this->problemas++;
        }

        /* ── Roles vacíos ── */
        $vacios = DB::table('roles as r')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('role_has_permissions as rp')
                ->whereColumn('rp.role_id', 'r.id'))
            ->count();

        if ($vacios === 0) {
            $this->line('  <fg=green>✓</> ningún rol quedó sin permisos');
        } else {
            $this->line("  <fg=yellow>·</> {$vacios} rol(es) sin ningún permiso asignado");
        }

        /* ── Roles de un negocio asignados a gente de otro ── */
        if (Schema::hasTable('empresa_usuario')) {
            $cruzados = DB::table('model_has_roles as mr')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->whereNotNull('r.empresa_id')
                ->where('mr.model_type', \App\Models\User::class)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('empresa_usuario as eu')
                    ->whereColumn('eu.user_id', 'mr.model_id')
                    ->whereColumn('eu.empresa_id', 'r.empresa_id'))
                ->count();

            if ($cruzados === 0) {
                $this->line('  <fg=green>✓</> ningún rol propio está asignado a gente de otro negocio');
            } else {
                $this->line("  <fg=red>✗</> {$cruzados} asignación(es) de un rol propio a alguien que no es de ese negocio");
                $this->problemas++;
            }
        }
    }

    /* ═══════════════ 8. Planes e ingreso ═══════════════ */

    /**
     * Que la facturación cuadre con lo que hay.
     *
     * Dos preguntas. La primera: ¿hay algún cliente por encima de su
     * plan? No es un error del sistema —bajarse de plan deja a uno por
     * encima a propósito, y eso está bien— pero es una conversación
     * comercial pendiente, y conviene verla antes de que el cliente
     * pregunte por qué no puede crear nada.
     *
     * La segunda: ¿la bitácora del ingreso cuadra con el ingreso? Si
     * alguien cambió un plan por fuera del servicio, el MRR de hoy y la
     * suma de los movimientos no van a dar lo mismo — y ahí el que miente
     * es el histórico, que es el que se usa para decidir.
     */
    private function planes(): void
    {
        $this->components->info('Planes e ingreso');

        if (! Schema::hasTable('planes') || ! Schema::hasColumn('empresas', 'plan_id')) {
            $this->line('  <fg=yellow>·</> todavía no está la migración de planes');

            return;
        }

        $planes = DB::table('planes')->count();
        $this->line("  <fg=green>✓</> {$planes} plan(es) en el catálogo");

        if ($planes === 0) {
            $this->line('  <fg=red>✗</> no hay planes sembrados — corre php artisan db:seed --class=PlanSeeder');
            $this->problemas++;

            return;
        }

        /* ── Reparto de clientes ── */
        $sinPlan = DB::table('empresas')->whereNull('plan_id')->whereNull('deleted_at')->count();

        if ($sinPlan > 0) {
            // No es un problema: sin plan = sin límites, y así arranca todo
            // el mundo. Se informa para que no sorprenda.
            $this->line("  <fg=yellow>·</> {$sinPlan} empresa(s) sin plan (sin topes, es lo normal al empezar)");
        }

        /* ── Quién se pasó de su plan ── */
        $excedidos = $this->empresasPorEncimaDeSuPlan();

        if ($excedidos === []) {
            $this->line('  <fg=green>✓</> ningún cliente está por encima de lo que contrató');
        } else {
            foreach ($excedidos as $texto) {
                $this->line("  <fg=yellow>·</> {$texto}");
            }
        }

        /* ── El ingreso ── */
        $mrr = app(\App\Services\MrrService::class)->mrrActual();

        $this->line('  <fg=green>✓</> MRR actual: $' . number_format($mrr, 0, ',', '.')
            . ' · ARR: $' . number_format($mrr * 12, 0, ',', '.'));

        /* ── La bitácora ── */
        if (! Schema::hasTable('mrr_movimientos')) {
            return;
        }

        $conPlan = DB::table('empresas')
            ->whereNotNull('plan_id')
            ->whereNull('deleted_at')
            ->where('estado', 'activa')
            ->pluck('id');

        $sinBitacora = $conPlan->filter(
            fn ($id) => ! DB::table('mrr_movimientos')->where('empresa_id', $id)->exists()
        );

        if ($sinBitacora->isEmpty()) {
            $this->line('  <fg=green>✓</> cada cliente con plan tiene su historial de ingreso');
        } else {
            $this->line('  <fg=yellow>·</> ' . $sinBitacora->count()
                . ' empresa(s) con plan pero sin ningún movimiento anotado — se les puso el plan por fuera de MrrService');
        }
    }

    /** @return array<int, string> */
    private function empresasPorEncimaDeSuPlan(): array
    {
        $avisos = [];

        $empresas = \App\Models\Empresa::query()->with('plan')->whereNotNull('plan_id')->get();

        foreach ($empresas as $empresa) {
            foreach (\App\Models\Plan::RECURSOS as $recurso) {
                $limite = $empresa->limite($recurso);

                if ($limite === null) {
                    continue;
                }

                $usado = $empresa->usado($recurso);

                if ($usado > $limite) {
                    $avisos[] = "«{$empresa->nombre}» va en {$usado} {$recurso} y su plan "
                        . "{$empresa->plan->nombre} incluye {$limite}";
                }
            }
        }

        return $avisos;
    }

    /* ═══════════════ 9. Índices ═══════════════ */

    /**
     * Los únicos que pasaron de globales a «por empresa» o «por tienda».
     *
     * Si uno de estos no quedó, el error no aparece hoy: aparece el día
     * que entre el segundo cliente y no pueda registrar un código de
     * barras que otro ya usó, o el día que el segundo local intente
     * emitir su V-000001.
     */
    private function indices(): void
    {
        $this->components->info('Índices únicos');

        $esperados = [
            'products'     => [['empresa_id', 'barcode'], ['empresa_id', 'sku'], ['empresa_id', 'slug']],
            'categories'   => [['empresa_id', 'slug']],
            'customers'    => [['empresa_id', 'email'], ['empresa_id', 'document_number']],
            'sales'        => [['tienda_id', 'number']],
            'purchases'    => [['tienda_id', 'number']],
            'sale_returns' => [['tienda_id', 'number']],
            'counters'     => [['tienda_id', 'key']],
            'settings'     => [['empresa_id', 'key']],
            'existencias'  => [['tienda_id', 'product_id']],
            'traslados'    => [['desde_tienda_id', 'numero']],
        ];

        foreach ($esperados as $tabla => $combinaciones) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            try {
                $indices = collect(Schema::getIndexes($tabla))
                    ->filter(fn ($i) => $i['unique'] ?? false)
                    ->map(fn ($i) => $i['columns'])
                    ->all();
            } catch (\Throwable $e) {
                $this->line("  <fg=yellow>·</> no se pudieron leer los índices de {$tabla} en este motor");

                continue;
            }

            foreach ($combinaciones as $columnas) {
                foreach ($columnas as $c) {
                    if (! Schema::hasColumn($tabla, $c)) {
                        continue 2;
                    }
                }

                $existe = false;

                foreach ($indices as $cols) {
                    if (array_values($cols) === $columnas) {
                        $existe = true;
                        break;
                    }
                }

                $texto = $tabla . ' (' . implode(' + ', $columnas) . ')';

                if ($existe) {
                    $this->line("  <fg=green>✓</> {$texto}");
                } else {
                    $this->line("  <fg=red>✗</> falta el único {$texto}");
                    $this->problemas++;
                }
            }
        }

        $this->newLine();
    }
}
