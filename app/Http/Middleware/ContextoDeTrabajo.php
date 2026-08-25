<?php

namespace App\Http\Middleware;

use App\Models\Tienda;
use App\Support\Contexto;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decide —y comprueba— en qué negocio y en qué local trabaja esta petición.
 *
 * Es la otra mitad del aislamiento. El filtro de los modelos se encarga de
 * que una consulta no vea lo de otra empresa; este middleware se encarga
 * de que la empresa que el filtro va a usar sea una a la que el usuario de
 * verdad pertenece.
 *
 * Los dos son necesarios y hacen cosas distintas: sin el filtro, la
 * consulta ve de más; sin esta comprobación, bastaría con cambiar un
 * número en la sesión para ver el negocio del vecino.
 *
 * ── Falla cerrado ──
 *
 * Si algo no cuadra —usuario sin empresa, empresa suspendida, local que no
 * es de esa empresa— la petición se detiene. La alternativa, dejar pasar
 * sin contexto, significaría trabajar sin filtro: exactamente lo contrario
 * de lo que este archivo existe para evitar.
 */
class ContextoDeTrabajo
{
    /** Métodos que escriben. Son los que lleva el guardián de pestañas. */
    private const ESCRITURA = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Rutas que escriben pero no en el negocio.
     *
     * Cambiar de local, cambiar de idioma y salir del sistema no tocan
     * ningún dato del cliente, y exigirles la firma al día dejaría al
     * usuario encerrado en una pestaña vieja: no podría ni cambiarse ni
     * cerrar sesión sin recargar primero.
     */
    private const SIN_SELLO = [
        'contexto.cambiar', 'idioma.cambiar', 'logout',
        'sistema.entrar', 'sistema.salir',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Sin sesión iniciada no hay contexto que resolver: la pantalla de
        // ingreso y el portal público pasan de largo.
        if (! $usuario) {
            return $next($request);
        }

        $empresa = $this->resolverEmpresa($request, $usuario);
        $tienda  = $this->resolverTienda($request, $usuario, $empresa->id);

        Contexto::usar($empresa->id, $tienda?->id);

        $this->guardianDePestanas($request);

        return $next($request);
    }

    /* ═══════════ Empresa ═══════════ */

    private function resolverEmpresa(Request $request, $usuario)
    {
        /**
         * El superadministrador mirando el negocio de un cliente.
         *
         * Es la única puerta por la que se entra a una empresa a la que uno
         * no pertenece, y por eso pide dos cosas a la vez: el permiso
         * `sistema.superadmin` Y que la sesión traiga la empresa elegida.
         * Ninguna de las dos sola alcanza.
         *
         * Se salta la comprobación de «está al día» a propósito: entrar a
         * una cuenta suspendida o vencida es justamente lo que hace falta
         * para averiguar por qué el cliente llamó.
         */
        $mirando = (int) $request->session()->get('mus.mirando_empresa', 0);

        if ($mirando && $usuario->can('sistema.superadmin')) {
            $ajena = \App\Models\Empresa::find($mirando);

            if ($ajena) {
                return $ajena;
            }

            // Se quedó apuntando a una empresa que ya no existe: se limpia
            // en vez de dejar al superadministrador atrapado.
            $request->session()->forget('mus.mirando_empresa');
        }

        $suyas = $usuario->empresas()->get();

        if ($suyas->isEmpty()) {
            abort(403, 'Tu usuario no está asignado a ningún negocio. Pídele al administrador que te agregue.');
        }

        $pedida = (int) $request->session()->get('mus.empresa_id', 0);

        // La comprobación que importa: que la empresa guardada en la sesión
        // sea una de las suyas. Sin esto, cambiar un número en la sesión
        // daría acceso al negocio de otro cliente.
        $empresa = $suyas->firstWhere('id', $pedida) ?: $suyas->first();

        if (! $empresa->estaActiva()) {
            // Se busca otra suya que sí lo esté antes de rendirse: un dueño
            // con dos negocios no debe quedarse por fuera porque uno venció.
            $otra = $suyas->first(fn ($e) => $e->estaActiva());

            if (! $otra) {
                abort(403, $this->porQueNoEntra($empresa));
            }

            $empresa = $otra;
        }

        return $empresa;
    }

    private function porQueNoEntra($empresa): string
    {
        if ($empresa->haVencido()) {
            return 'La prueba de «' . $empresa->nombre . '» se venció. Escríbenos para activar tu cuenta.';
        }

        return 'La cuenta de «' . $empresa->nombre . '» está suspendida. Escríbenos para reactivarla.';
    }

    /* ═══════════ Tienda ═══════════ */

    private function resolverTienda(Request $request, $usuario, int $empresaId): ?Tienda
    {
        $pedida = (int) $request->session()->get('mus.tienda_id', 0);

        $disponibles = $this->tiendasDelUsuario($usuario, $empresaId);

        if ($disponibles->isEmpty()) {
            // Sin local no se puede vender ni mover inventario, pero sí
            // consultar. Se deja pasar sin tienda y que el servicio que la
            // necesite sea el que se queje, con un mensaje que se entienda.
            return null;
        }

        // Que la tienda de la sesión sea de ESTA empresa. Si alguien cambió
        // de negocio y quedó la anterior guardada, estaría escribiendo en el
        // local de otro.
        return $disponibles->firstWhere('id', $pedida) ?: $disponibles->first();
    }

    /**
     * Los locales donde este usuario puede trabajar.
     *
     * La regla vive en el modelo `User` y no aquí, para que el selector de
     * la barra superior ofrezca exactamente los mismos locales que este
     * guardián acepta.
     */
    private function tiendasDelUsuario($usuario, int $empresaId)
    {
        return $usuario->tiendasEn($empresaId);
    }

    /* ═══════════ Guardián de pestañas ═══════════ */

    /**
     * Impide que una pestaña vieja escriba en el negocio equivocado.
     *
     * El caso: William abre la ferretería en una pestaña, abre otra y
     * cambia al almacén. Vuelve a la primera —que sigue mostrando la
     * ferretería— y registra una venta. Como la sesión es una sola para
     * todo el navegador, esa venta caería en el almacén.
     *
     * Cada pantalla lleva escondida la firma del contexto con el que se
     * dibujó. Si al escribir no coincide con el actual, no se guarda nada:
     * se avisa y se pide recargar.
     *
     * Solo se comprueba cuando la firma viene. Una petición sin ella —un
     * cliente de la API, un formulario sin JavaScript— no se bloquea; para
     * eso está el resto del aislamiento. Aquí se cubre el error humano,
     * que es el que de verdad pasa.
     */
    private function guardianDePestanas(Request $request): void
    {
        if (! in_array($request->method(), self::ESCRITURA, true)) {
            return;
        }

        // Tres cosas quedan por fuera, y las tres por la misma razón: no
        // escriben en el negocio, y exigirles estar al día dejaría al
        // usuario atrapado en una pestaña vieja sin poder ni cambiarse ni
        // salir.
        if ($request->routeIs(...self::SIN_SELLO)) {
            return;
        }

        $firmaPagina = (string) $request->input('_ctx', '');

        if ($firmaPagina === '' || $firmaPagina === Contexto::firma()) {
            return;
        }

        abort(409, 'Esta pestaña quedó abierta en otro negocio o en otro local. '
                 . 'Recárgala antes de guardar, para que lo que escribas no caiga donde no es.');
    }
}
