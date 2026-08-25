<?php

namespace App\Models;

use App\Support\Contexto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros del negocio, en clave/valor.
 *
 * Se cachean porque el recibo, la cabecera del panel y ahora también el
 * formato de todos los números los leen en cada petición, y no cambian casi
 * nunca. Cualquier guardado borra la caché.
 *
 * Convención de las claves: `grupo.nombre`. El grupo es el que decide en
 * qué pestaña de Configuración aparece el campo.
 */
class Setting extends Model
{
    protected $fillable = ['empresa_id', 'key', 'value', 'group'];

    /**
     * Clave nueva a propósito.
     *
     * La anterior («mus.settings») guardaba el array ya mezclado con los
     * valores de fábrica. Al cambiar el formato de lo que se guarda hay que
     * cambiar también la clave, o la instalación que ya esté andando lee la
     * caché vieja con el formato viejo y se cae. Es la misma regla de
     * siempre: si cambia la forma del dato, cambia el nombre donde vive.
     */
    private const CLAVE_CACHE = 'mus.settings.v3';

    /** Mezcla resuelta una sola vez por petición, por empresa. */
    private static array $memoria = [];

    /**
     * Valores por defecto: el sistema funciona aunque nadie configure nada,
     * y una instalación nueva ya arranca con formato colombiano.
     */
    public const PREDETERMINADOS = [
        /* ── Negocio ── */
        'negocio.nombre'    => 'MEGA UNI STORE',
        'negocio.lema'      => 'Tienda universitaria',
        'negocio.nit'       => '',
        'negocio.direccion' => '',
        'negocio.telefono'  => '',
        'negocio.correo'    => '',
        'negocio.ciudad'    => 'Bogotá D.C.',
        'negocio.logo'      => '',

        /* ── Regional ── */
        'regional.idioma'           => 'es',
        'regional.moneda_codigo'    => 'COP',
        'regional.moneda_simbolo'   => '$',
        'regional.simbolo_posicion' => 'antes',     // antes | despues
        'regional.decimales'        => '0',
        'regional.sep_miles'        => 'punto',     // punto | coma | espacio | ninguno
        'regional.sep_decimal'      => 'coma',
        'regional.zona_horaria'     => 'America/Bogota',
        'regional.formato_fecha'    => 'dmy_slash',

        /* ── Recibo ── */
        'recibo.formato'           => '80',         // 80 | 58 | a4
        'recibo.mensaje'           => '¡Gracias por tu compra!',
        'recibo.pie'               => '',
        'recibo.mostrar_logo'      => '1',
        'recibo.mostrar_qr'        => '0',
        'recibo.mostrar_impuestos' => '0',
        'recibo.mostrar_cajero'    => '1',
        'recibo.mostrar_ahorro'    => '1',
        'recibo.copias'            => '1',
        'recibo.auto'              => '1',

        /* ── Ventas ── */
        'venta.stock_negativo'      => '0',
        'venta.descuento_max'       => '100',
        'venta.cliente_obligatorio' => '0',
        'venta.redondeo'            => '0',         // 0 | 50 | 100

        /* ── Inventario ── */
        'inventario.alerta_activa' => '1',
        'inventario.dias_rotacion' => '30',
        'inventario.costo_metodo'  => 'promedio',   // promedio | ultimo

        /* ── Apariencia ── */
        'apariencia.acento'      => 'azul',
        'apariencia.densidad'    => 'comoda',
        'apariencia.animaciones' => '1',
    ];

    /** Grupos que existen, en el orden de las pestañas. */
    public const GRUPOS = ['negocio', 'regional', 'recibo', 'venta', 'inventario', 'apariencia'];

    /**
     * Devuelve el valor guardado tal cual, incluso si está vacío: que el
     * usuario haya borrado el lema del negocio es una decisión suya, no un
     * hueco que haya que rellenar con el valor de fábrica.
     */
    public static function obtener(string $clave, $porDefecto = null, ?int $empresaId = null)
    {
        return static::todos($empresaId)[$clave]
            ?? $porDefecto
            ?? self::PREDETERMINADOS[$clave]
            ?? null;
    }

    /** Ajuste de sí/no. Cualquier '1' es sí; todo lo demás es no. */
    public static function activo(string $clave): bool
    {
        return (string) (static::todos()[$clave] ?? self::PREDETERMINADOS[$clave] ?? '0') === '1';
    }

    public static function entero(string $clave, int $porDefecto = 0): int
    {
        return (int) (static::todos()[$clave] ?? self::PREDETERMINADOS[$clave] ?? $porDefecto);
    }

    /**
     * Todos los ajustes: los de fábrica, pisados por los guardados.
     *
     * ── Por qué la caché guarda SOLO lo de la base ──
     *
     * Antes se cacheaba el array ya mezclado. Parecía más rápido y era una
     * bomba de tiempo: la caché es `rememberForever`, así que el día que se
     * agregó un ajuste nuevo al código, la caché siguió devolviendo la
     * mezcla vieja —sin la clave nueva— y la primera pantalla que la leyera
     * se caía con «Undefined array key». Un error de despliegue que no
     * aparece en desarrollo (donde uno limpia la caché sin pensarlo) y sí
     * aparece en el negocio del cliente.
     *
     * Guardando solo las filas de la base, los valores de fábrica se
     * mezclan en cada lectura desde el código de HOY. Agregar un ajuste
     * nuevo nunca vuelve a exigir un `cache:clear`.
     *
     * La mezcla se memoriza en la petición porque Formato la consulta una
     * vez por cada número que se pinta — y una tabla de productos pinta
     * cientos.
     *
     * @return array<string, string>
     */
    public static function todos(?int $empresaId = null): array
    {
        $empresaId ??= Contexto::empresaId() ?? 0;

        if (isset(self::$memoria[$empresaId])) {
            return self::$memoria[$empresaId];
        }

        $guardados = Cache::rememberForever(
            self::CLAVE_CACHE . '.' . $empresaId,
            function () use ($empresaId) {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                $consulta = static::query();

                // La columna puede no existir todavía si alguien lee ajustes
                // en mitad de la migración de la fase 1.
                if (Schema::hasColumn('settings', 'empresa_id') && $empresaId > 0) {
                    $consulta->where('empresa_id', $empresaId);
                }

                return $consulta->pluck('value', 'key')->all();
            }
        );

        return self::$memoria[$empresaId] = array_merge(self::PREDETERMINADOS, $guardados);
    }

    /** Olvida lo memorizado y lo cacheado. */
    public static function olvidar(?int $empresaId = null): void
    {
        if ($empresaId === null) {
            // Sin empresa concreta se limpia todo lo memorizado y las claves
            // de las empresas que se hayan tocado en esta petición.
            foreach (array_keys(self::$memoria) as $id) {
                Cache::forget(self::CLAVE_CACHE . '.' . $id);
            }

            Cache::forget(self::CLAVE_CACHE . '.0');
            self::$memoria = [];

            return;
        }

        unset(self::$memoria[$empresaId]);
        Cache::forget(self::CLAVE_CACHE . '.' . $empresaId);
    }

    public static function guardar(array $valores, string $grupo = 'general', ?int $empresaId = null): void
    {
        $empresaId ??= Contexto::empresaId();

        foreach ($valores as $clave => $valor) {
            static::updateOrCreate(
                ['empresa_id' => $empresaId, 'key' => $clave],
                [
                    'value' => (string) $valor,
                    // El grupo sale de la propia clave cuando se puede, así
                    // no hay que pasarlo a mano y nunca queda descuadrado.
                    'group' => str_contains($clave, '.')
                        ? \Illuminate\Support\Str::before($clave, '.')
                        : $grupo,
                ]
            );
        }

        self::olvidar($empresaId);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::olvidar());
        static::deleted(fn () => self::olvidar());
    }
}
