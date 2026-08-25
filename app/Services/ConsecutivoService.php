<?php

namespace App\Services;

use App\Models\Tienda;
use App\Support\Contexto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Números consecutivos de los documentos: V-000001, C-000001, D-000001.
 *
 * ── El problema original ──
 *
 * Antes cada documento se numeraba con `max(id) + 1`. Si dos cajeros
 * cobran en el mismo segundo, los dos leen el mismo máximo, los dos arman
 * el mismo número y el segundo choca contra el índice único — error 500
 * con el cliente enfrente. Es de esos errores que nunca salen probando
 * solo y siempre salen el día de la demostración.
 *
 * La solución es la de siempre en bases relacionales: una fila por
 * contador y un `UPDATE … SET value = value + 1`. Ese UPDATE bloquea la
 * fila, así que la base serializa a los dos cajeros sin que nosotros
 * hagamos nada.
 *
 * ── Lo que cambió con el multi-tienda ──
 *
 * El contador ahora es de la TIENDA, no del sistema. Dos locales
 * compartiendo una sola serie producen recibos entreverados —V-000012 en
 * el centro, V-000013 en el norte, V-000014 en el centro— que nadie puede
 * cuadrar.
 *
 * Y cada local puede llevar su prefijo: la tienda principal no lleva
 * ninguno, para que los V-000001 que ya existen sigan valiendo, y las que
 * se abren después salen como V-B-000001.
 *
 * Se llama SIEMPRE dentro de la transacción que crea el documento: si la
 * venta se cae, el número se devuelve con ella y no queda un hueco.
 */
class ConsecutivoService
{
    /** Prefijo de cada serie. */
    private const PREFIJOS = [
        'ventas'       => 'V-',
        'compras'      => 'C-',
        'devoluciones' => 'D-',
        'pedidos'      => 'P-',
            'traslados'    => 'T-',
    ];

    /** Código de cada tienda, resuelto una sola vez por petición. */
    private static array $codigos = [];

    /**
     * Devuelve el siguiente número de la serie, ya formateado.
     *
     * @param  string    $serie     ventas | compras | devoluciones | pedidos
     * @param  int|null  $tiendaId  null = el local en el que se está trabajando
     */
    public static function siguiente(string $serie, ?int $tiendaId = null, int $digitos = 6): string
    {
        $tiendaId ??= Contexto::tiendaId();

        $prefijoSerie  = self::PREFIJOS[$serie] ?? mb_strtoupper(mb_substr($serie, 0, 1)) . '-';
        $prefijoTienda = self::prefijoTienda($tiendaId);

        return $prefijoSerie
             . $prefijoTienda
             . str_pad((string) self::numero($serie, $tiendaId), $digitos, '0', STR_PAD_LEFT);
    }

    /**
     * Incrementa el contador del local y devuelve el valor nuevo.
     *
     * Si la tabla todavía no existe —proyecto recién clonado, migración a
     * medio correr— se cae con elegancia al método viejo en vez de romper
     * la venta. Es un respaldo, no el camino normal.
     */
    public static function numero(string $serie, ?int $tiendaId = null): int
    {
        $tiendaId ??= Contexto::tiendaId();

        if (! $tiendaId
            || ! Schema::hasTable('counters')
            || ! Schema::hasColumn('counters', 'tienda_id')) {
            return self::respaldo($serie, $tiendaId);
        }

        return DB::transaction(function () use ($serie, $tiendaId) {
            // increment() arma el «value = value + 1» con las comillas que
            // toquen según el motor, así que funciona igual en MySQL que en
            // SQLite —que es donde corren las pruebas.
            $filas = DB::table('counters')
                ->where('tienda_id', $tiendaId)
                ->where('key', $serie)
                ->increment('value', 1, ['updated_at' => now()]);

            // Primera vez que este local usa esta serie.
            if ($filas === 0) {
                $desde = self::respaldo($serie, $tiendaId);

                DB::table('counters')->insert([
                    'tienda_id'  => $tiendaId,
                    'key'        => $serie,
                    'value'      => $desde,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $desde;
            }

            return (int) DB::table('counters')
                ->where('tienda_id', $tiendaId)
                ->where('key', $serie)
                ->value('value');
        });
    }

    /**
     * Cuando no hay tabla de contadores: se mira el documento más alto que
     * exista EN ESE LOCAL. Menos seguro, pero mejor que dejar caer la
     * operación.
     */
    private static function respaldo(string $serie, ?int $tiendaId): int
    {
        $tablas = [
            'ventas'       => 'sales',
            'compras'      => 'purchases',
            'devoluciones' => 'sale_returns',
            'pedidos'      => 'orders',
        ];

        $tabla = $tablas[$serie] ?? null;

        if (! $tabla || ! Schema::hasTable($tabla)) {
            return 1;
        }

        $consulta = DB::table($tabla);

        if ($tiendaId && Schema::hasColumn($tabla, 'tienda_id')) {
            $consulta->where('tienda_id', $tiendaId);
        }

        return ((int) $consulta->max('id')) + 1;
    }

    /** «B-» para la tienda con código B, cadena vacía para la principal. */
    private static function prefijoTienda(?int $tiendaId): string
    {
        if (! $tiendaId) {
            return '';
        }

        if (! array_key_exists($tiendaId, self::$codigos)) {
            self::$codigos[$tiendaId] = Schema::hasTable('tiendas')
                ? (string) (Tienda::whereKey($tiendaId)->value('codigo') ?? '')
                : '';
        }

        $codigo = self::$codigos[$tiendaId];

        return $codigo === '' ? '' : $codigo . '-';
    }

    /** Las pruebas cambian de tienda entre casos. */
    public static function olvidar(): void
    {
        self::$codigos = [];
    }
}
