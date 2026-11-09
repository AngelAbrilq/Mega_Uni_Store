<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Models\Tienda;
use App\Support\Contexto;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * El contexto del portal público: el negocio lo dice la dirección.
 *
 *     /tienda/ferreteria-el-tornillo/...  → empresa con slug «ferreteria-el-tornillo»
 *
 * ── Por qué corre ANTES de la autenticación ──
 *
 * El guard `cliente` busca al cliente con el modelo `Customer`, que tiene
 * el filtro por empresa. Si la autenticación corriera primero, buscaría
 * SIN contexto —o con el del empleado logueado en el navegador— y un
 * cliente de la ferretería quedaría autenticado en la tienda del
 * supermercado. Por eso este middleware va en la lista de prioridad antes
 * de `AuthenticatesRequests` (ver bootstrap/app.php).
 *
 * Como corre antes que SubstituteBindings, el parámetro `{empresa}` todavía
 * es texto: se resuelve aquí y se deja puesto el modelo en la ruta, para
 * que el binding implícito no lo vuelva a buscar.
 *
 * ── Falla cerrado ──
 *
 * Slug inexistente, empresa suspendida o vencida, o sin ningún local activo:
 * 404. No se le cuenta a un visitante que la cuenta de un negocio venció.
 */
class ContextoDeTienda
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = $this->resolverEmpresa($request);

        if (! $empresa || ! $empresa->estaActiva()) {
            abort(404);
        }

        // Primero el contexto y después la tienda: si `Tienda` llegara a
        // tener filtro por empresa, sin esto devolvería la del empleado.
        Contexto::fijar($empresa->id);

        $tienda = $this->tiendaDeVenta($empresa);

        if (! $tienda) {
            abort(404);
        }

        Contexto::fijar($empresa->id, $tienda->id);

        $request->route()->setParameter('empresa', $empresa);

        $this->descartarClienteAjeno($empresa);

        View::share('tiendaEmpresa', $empresa);
        View::share('tiendaLocal', $tienda);

        try {
            return $next($request);
        } finally {
            // El contexto es estático: que no sobreviva a la petición (cola,
            // Octane, o la siguiente petición dentro de una misma prueba).
            Contexto::olvidar();
        }
    }

    /**
     * Segunda llave, por si la primera falla.
     *
     * Normalmente el filtro de `Customer` ya impide cargar un cliente de otra
     * empresa desde la sesión. Pero si el guard ya trae un usuario en
     * memoria (cargado antes, o puesto a mano), se comprueba explícitamente.
     * `forgetUser()` solo lo suelta para ESTA petición: no le cierra la
     * sesión en la tienda donde sí es cliente.
     */
    private function descartarClienteAjeno(Empresa $empresa): void
    {
        $guard   = Auth::guard('cliente');
        $cliente = $guard->user();

        if ($cliente && (int) $cliente->empresa_id !== (int) $empresa->id) {
            $guard->forgetUser();
        }
    }

    private function resolverEmpresa(Request $request): ?Empresa
    {
        $valor = $request->route('empresa');

        if ($valor instanceof Empresa) {
            return $valor;
        }

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        return Empresa::query()->where('slug', $valor)->first();
    }

    /**
     * El local que surte la tienda en línea: el principal activo.
     *
     * Hoy es uno solo por empresa. Si mañana se quiere escoger, este es el
     * único sitio que cambia.
     */
    private function tiendaDeVenta(Empresa $empresa): ?Tienda
    {
        return $empresa->tiendas()
            ->where('activa', true)
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->first();
    }
}
