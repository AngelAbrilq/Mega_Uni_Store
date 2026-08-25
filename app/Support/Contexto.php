<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\Tienda;
use Illuminate\Support\Facades\Schema;

/**
 * En qué negocio y en qué local está trabajando quien hizo esta petición.
 *
 * Es la pieza que en la fase 3 va a alimentar el filtro automático de
 * todas las consultas. En la fase 1 todavía no filtra nada —solo hay una
 * empresa— pero ya existe y ya responde, para que los servicios que la
 * necesitan (contadores, ajustes, recibo) se escriban una sola vez.
 *
 * ── El orden en que resuelve ──
 *
 *   1. Lo que se escogió en el selector, guardado en la sesión.
 *   2. La primera empresa a la que pertenece el usuario.
 *   3. La primera empresa que exista, para los comandos de consola y las
 *      instalaciones de un solo negocio, donde no hay nada que escoger.
 *
 * ── Lo que NO hace, a propósito ──
 *
 * No decide si el usuario TIENE PERMISO de estar en esa empresa. Eso lo
 * hace el guardián de la fase 3, y tiene que ser una comprobación aparte:
 * mezclar «dónde está» con «dónde puede estar» es como se cuelan los
 * errores de aislamiento.
 */
class Contexto
{
    private static ?int $empresaId = null;
    private static ?int $tiendaId  = null;
    private static bool $resuelto  = false;

    private static ?Empresa $empresa = null;
    private static ?Tienda  $tienda  = null;

    /** Los locales de la empresa actual, para el filtro de documentos. */
    private static ?array $tiendaIds = null;

    /** Profundidad de `sinFiltro()`. Es un contador y no un booleano
        porque las llamadas se pueden anidar. */
    private static int $sinFiltro = 0;

    /* ─────────────── Lectura ─────────────── */

    public static function empresaId(): ?int
    {
        self::resolver();

        return self::$empresaId;
    }

    public static function tiendaId(): ?int
    {
        self::resolver();

        return self::$tiendaId;
    }

    /**
     * Los ids de los locales de la empresa actual.
     *
     * Devuelve null cuando no hay contexto —consola, colas— y ahí el
     * filtro no se aplica. Devuelve [] cuando la empresa no tiene ningún
     * local, y entonces el filtro no deja pasar nada: es distinto «no sé
     * de quién es esto» que «no tiene locales».
     *
     * @return array<int>|null
     */
    public static function tiendaIds(): ?array
    {
        if (self::$tiendaIds !== null) {
            return self::$tiendaIds;
        }

        $empresaId = self::empresaId();

        if ($empresaId === null) {
            return null;
        }

        return self::$tiendaIds = Tienda::where('empresa_id', $empresaId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function empresa(): ?Empresa
    {
        if (self::$empresa === null && self::empresaId() !== null) {
            self::$empresa = Empresa::find(self::$empresaId);
        }

        return self::$empresa;
    }

    public static function tienda(): ?Tienda
    {
        if (self::$tienda === null && self::tiendaId() !== null) {
            self::$tienda = Tienda::with('empresa')->find(self::$tiendaId);
        }

        return self::$tienda;
    }

    /* ─────────────── Escritura ─────────────── */

    /**
     * Cambia de negocio o de local.
     *
     * Guarda en la sesión y limpia lo memorizado. Quien llama a esto es
     * responsable de haber verificado antes que el usuario pertenece a esa
     * empresa — aquí no se comprueba.
     */
    public static function usar(?int $empresaId, ?int $tiendaId = null): void
    {
        if (function_exists('session') && app()->bound('session')) {
            session([
                'mus.empresa_id' => $empresaId,
                'mus.tienda_id'  => $tiendaId,
            ]);
        }

        self::olvidar();

        self::$empresaId = $empresaId;
        self::$tiendaId  = $tiendaId;
        self::$resuelto  = true;
    }

    /** Lo que va escondido en cada formulario, para detectar las pestañas. */
    public static function firma(): string
    {
        return (self::empresaId() ?? 0) . ':' . (self::tiendaId() ?? 0);
    }

    /** Vuelve a resolver desde cero. Lo usan las pruebas y los comandos. */
    public static function olvidar(): void
    {
        self::$empresaId = null;
        self::$tiendaId  = null;
        self::$empresa   = null;
        self::$tienda    = null;
        self::$tiendaIds = null;
        self::$resuelto  = false;
    }

    /* ─────────────── Levantar el filtro, a propósito ─────────────── */

    /** ¿Estamos dentro de un bloque sin filtro? Lo pregunta el scope. */
    public static function sinFiltroActivo(): bool
    {
        return self::$sinFiltro > 0;
    }

    /**
     * Corre algo viendo TODAS las empresas.
     *
     *     Contexto::sinFiltro(fn () => Product::count());   // de todos
     *
     * Es para el panel de superadministrador y para los comandos que
     * recorren clientes. El `finally` es lo importante: si lo de adentro
     * lanza una excepción, el filtro se vuelve a poner igual. Sin eso, un
     * error en un informe dejaría el resto de la petición sin aislamiento.
     */
    public static function sinFiltro(callable $fn): mixed
    {
        self::$sinFiltro++;

        try {
            return $fn();
        } finally {
            self::$sinFiltro--;
        }
    }

    /**
     * Corre algo como si fuera otra empresa, y deja todo como estaba.
     *
     * Lo usan el alta de clientes nuevos y los comandos que siembran
     * catálogos: hay que escribir dentro de una empresa que no es la del
     * usuario que está ejecutando.
     */
    public static function comoEmpresa(int $empresaId, ?int $tiendaId, callable $fn): mixed
    {
        $empresaAntes = self::$empresaId;
        $tiendaAntes  = self::$tiendaId;
        $resueltoAntes = self::$resuelto;

        self::olvidar();
        self::$empresaId = $empresaId;
        self::$tiendaId  = $tiendaId;
        self::$resuelto  = true;

        try {
            return $fn();
        } finally {
            self::olvidar();
            self::$empresaId = $empresaAntes;
            self::$tiendaId  = $tiendaAntes;
            self::$resuelto  = $resueltoAntes;
        }
    }

    /* ─────────────── Interno ─────────────── */

    private static function resolver(): void
    {
        if (self::$resuelto) {
            return;
        }

        self::$resuelto = true;

        // Durante `migrate` sobre una base recién creada las tablas todavía
        // no existen. Sin esta guarda, cualquier cosa que pida el contexto
        // rompería la instalación desde cero.
        if (! Schema::hasTable('empresas') || ! Schema::hasTable('tiendas')) {
            return;
        }

        self::$empresaId = self::resolverEmpresa();

        if (self::$empresaId === null) {
            return;
        }

        self::$tiendaId = self::resolverTienda(self::$empresaId);
    }

    private static function resolverEmpresa(): ?int
    {
        $deSesion = self::deSesion('mus.empresa_id');

        if ($deSesion !== null) {
            return $deSesion;
        }

        $usuario = self::usuario();

        if ($usuario && method_exists($usuario, 'empresas')) {
            $suya = $usuario->empresas()->orderBy('empresas.id')->value('empresas.id');

            if ($suya) {
                return (int) $suya;
            }
        }

        /**
         * Último recurso: adivinar.
         *
         * Y solo se adivina cuando no hay nada que adivinar —una sola
         * empresa—. Con dos o más se devuelve null, que significa «no sé de
         * quién es esto» y hace que el filtro no se aplique.
         *
         * Suena al revés, pero es lo correcto: quien llega hasta aquí es un
         * comando de consola o una tarea en cola, sin usuario y sin sesión.
         * Si en ese caso se devolviera «la primera empresa», el comando
         * nocturno de stock bajo revisaría el negocio de William y no
         * revisaría nunca el de Diego —sin error, sin aviso, sin nadie que
         * se entere—. Es peor equivocarse en silencio que no responder: un
         * comando que ve todo se nota y se arregla; uno que ve la mitad, no.
         *
         * Los comandos que sí necesitan pararse en una empresa lo dicen
         * explícitamente con `Contexto::comoEmpresa()`.
         */
        $empresas = Empresa::query()->take(2)->pluck('id');

        return $empresas->count() === 1 ? (int) $empresas->first() : null;
    }

    private static function resolverTienda(int $empresaId): ?int
    {
        $deSesion = self::deSesion('mus.tienda_id');

        if ($deSesion !== null) {
            // Que la tienda guardada sea de ESTA empresa. Si alguien cambió
            // de negocio y quedó la tienda anterior en la sesión, el sistema
            // estaría escribiendo en el local de otro.
            $vale = Tienda::where('id', $deSesion)->where('empresa_id', $empresaId)->exists();

            if ($vale) {
                return $deSesion;
            }
        }

        $usuario = self::usuario();

        if ($usuario && method_exists($usuario, 'tiendas')) {
            $suya = $usuario->tiendas()
                ->where('tiendas.empresa_id', $empresaId)
                ->where('tiendas.activa', true)
                ->orderByDesc('tiendas.es_principal')
                ->orderBy('tiendas.id')
                ->value('tiendas.id');

            if ($suya) {
                return (int) $suya;
            }
        }

        return Tienda::where('empresa_id', $empresaId)
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->value('id');
    }

    private static function deSesion(string $clave): ?int
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return null;
        }

        $valor = session($clave);

        return $valor === null ? null : (int) $valor;
    }

    private static function usuario()
    {
        return app()->bound('auth') ? auth()->user() : null;
    }
}
