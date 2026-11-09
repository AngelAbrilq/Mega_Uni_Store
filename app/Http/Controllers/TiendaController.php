<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\GuardarTiendaRequest;
use App\Models\Existencia;
use App\Models\Sale;
use App\Models\Tienda;
use App\Services\ConsecutivoService;
use App\Services\LimiteService;
use App\Services\TiendaService;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Los locales del negocio.
 *
 * ── Por qué esta pantalla llegó de última ──
 *
 * Porque todo lo demás del multitienda —existencias por local, traslados,
 * el selector de contexto, el aislamiento, los consecutivos con prefijo—
 * se construyó primero, contra la única tienda que crea el instalador. Se
 * podía trabajar así; lo que no se podía era abrir la segunda sin entrar
 * a la base de datos a mano.
 *
 * ── El aislamiento se escribe aquí, no se hereda ──
 *
 * `Tienda` no lleva el filtro global por empresa que sí llevan las ventas
 * o los productos, y es a propósito: el panel de superadministrador tiene
 * que poder listar los locales de todos. La consecuencia es que ESTE
 * controlador es responsable de filtrar por la empresa activa en cada
 * consulta, y de comprobar que la tienda que llega por la URL sea suya
 * antes de tocarla. Eso es `suya()`, y pasa por ahí todo lo que recibe un
 * parámetro de ruta.
 */
class TiendaController extends Controller implements HasMiddleware
{
    public function __construct(
        private TiendaService $tiendas,
        private LimiteService $limites,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:tiendas.ver',      only: ['index', 'show']),
            new Middleware('permission:tiendas.crear',    only: ['create', 'store']),
            new Middleware('permission:tiendas.editar',   only: ['edit', 'update', 'principal', 'tokenAgente']),
            new Middleware('permission:tiendas.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $tiendas = $this->deLaEmpresa()
            ->when($q !== '', fn ($c) => $c->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('ciudad', 'like', "%{$q}%")
                    ->orWhere('direccion', 'like', "%{$q}%");
            }))
            ->withCount('usuarios')
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->paginate(12)
            ->withQueryString();

        return view('tiendas.index', [
            'tiendas'   => $tiendas,
            'q'         => $q,
            'activaId'  => Contexto::tiendaId(),
            // Para esconder el botón en vez de dejar que el usuario lo
            // apriete y reciba un error: el tope del plan no es un fallo
            // suyo, es una condición conocida de antemano.
            'puedeMas'  => $this->limites->permite('tiendas'),
            'panorama'  => $this->limites->panorama()['tiendas'] ?? null,
        ]);
    }

    public function create()
    {
        $this->limites->exigir('tiendas');

        return view('tiendas.create');
    }

    public function store(GuardarTiendaRequest $request)
    {
        // Tope del plan: GuardarTiendaRequest::prepareForValidation(), antes de validar.
        $datos = $request->validated();

        $tienda = $this->tiendas->crear(
            $datos,
            $request->user(),
            $request->boolean('copiar_catalogo'),
        );

        return redirect()->route('tiendas.index')
            ->with('success', 'Local «' . $tienda->nombre . '» abierto. '
                . 'Cámbiate a él con el selector de arriba para empezar a trabajar ahí.');
    }

    public function show(Tienda $tienda)
    {
        $this->suya($tienda);

        return view('tiendas.show', [
            'tienda'      => $tienda->load('usuarios'),
            'disponibles' => Existencia::withoutGlobalScopes()->where('tienda_id', $tienda->id)->count(),
            'conStock'    => Existencia::withoutGlobalScopes()->where('tienda_id', $tienda->id)->where('stock', '>', 0)->count(),
            'ventas'      => Sale::withoutGlobalScopes()->where('tienda_id', $tienda->id)->count(),
            'esLaActiva'  => Contexto::tiendaId() === $tienda->id,
        ]);
    }

    public function edit(Tienda $tienda)
    {
        $this->suya($tienda);

        return view('tiendas.edit', compact('tienda'));
    }

    public function update(GuardarTiendaRequest $request, Tienda $tienda)
    {
        $this->suya($tienda);

        $datos = $request->validated();

        $codigo = trim((string) ($datos['codigo'] ?? ''));

        $tienda->update([
            'nombre'       => trim($datos['nombre']),
            'codigo'       => $codigo === '' ? null : mb_strtoupper($codigo),
            'razon_social' => $datos['razon_social'] ?? null,
            'nit'          => $datos['nit']          ?? null,
            'direccion'    => $datos['direccion']    ?? null,
            'ciudad'       => $datos['ciudad']       ?? null,
            'telefono'     => $datos['telefono']     ?? null,
            'correo'       => $datos['correo']       ?? null,
            'activa'       => $request->boolean('activa'),
            'drawer_mode'  => $datos['drawer_mode'] ?? $tienda->drawer_mode,
            'agent_port'   => $datos['agent_port']  ?? $tienda->agent_port,
        ]);

        if ($tienda->drawer_mode === Tienda::CAJON_AGENTE) {
            $tienda->asegurarTokenDeAgente();
        }

        /**
         * El prefijo de los consecutivos vive en memoria durante la
         * petición. Si alguien le cambia el código al local y no se
         * olvida, la siguiente venta de esa misma petición saldría
         * numerada con el código viejo.
         */
        ConsecutivoService::olvidar();

        return redirect()->route('tiendas.index')
            ->with('success', 'Local «' . $tienda->nombre . '» actualizado.');
    }

    /**
     * Token nuevo para el agente del cajón. Se usa si el PC de caja se
     * cambió o el token se filtró: el agente con el token viejo deja de
     * obedecer hasta que se le copie el nuevo.
     */
    public function tokenAgente(Tienda $tienda)
    {
        $this->suya($tienda);

        $tienda->regenerarTokenDeAgente();

        return redirect()->route('tiendas.edit', $tienda)
            ->with('success', 'Token del agente renovado. Cópialo en el config.json del PC de caja.');
    }

    /**
     * Nombrar principal a otro local.
     *
     * Va en su propia ruta y no en el formulario porque no es un dato del
     * local sino una decisión sobre el negocio: mueve la herencia de la
     * identidad —NIT, razón social— y el prefijo de los documentos. Un
     * interruptor perdido entre el teléfono y la ciudad no se lee como lo
     * que es.
     */
    public function principal(Tienda $tienda)
    {
        $this->suya($tienda);

        if ($tienda->es_principal) {
            return back()->with('success', '«' . $tienda->nombre . '» ya era la sede principal.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($tienda) {
            Tienda::where('empresa_id', $tienda->empresa_id)->update(['es_principal' => false]);

            $tienda->update(['es_principal' => true, 'activa' => true]);
        });

        return back()->with('success', '«' . $tienda->nombre . '» es ahora la sede principal.');
    }

    public function destroy(Tienda $tienda)
    {
        $this->suya($tienda);

        if ($motivo = $this->tiendas->motivoParaNoEliminar($tienda)) {
            return back()->with('error', $motivo);
        }

        $nombre = $tienda->nombre;
        $tienda->delete();

        return redirect()->route('tiendas.index')
            ->with('success', 'Local «' . $nombre . '» cerrado.');
    }

    /* ═══════════════ Interno ═══════════════ */

    /** @return \Illuminate\Database\Eloquent\Builder<Tienda> */
    private function deLaEmpresa()
    {
        return Tienda::query()->where('empresa_id', Contexto::empresaId());
    }

    /**
     * Que la tienda de la URL sea de la empresa en la que estoy parado.
     *
     * Sin esto, cambiar el número de la dirección —`/tiendas/47/edit`—
     * dejaría editar el local de otro negocio. El enlace de modelo de
     * Laravel encuentra la fila por id y no sabe nada de empresas.
     */
    private function suya(Tienda $tienda): void
    {
        abort_unless($tienda->empresa_id === Contexto::empresaId(), 404);
    }
}
