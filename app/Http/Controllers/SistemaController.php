<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\MrrMovimiento;
use App\Models\Plan;
use App\Services\MrrService;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * El panel de Angel: todos los clientes de un vistazo.
 *
 * ── Por qué está en el mismo sistema y no aparte ──
 *
 * Porque un panel aparte significa mantener dos aplicaciones, con dos
 * despliegues y dos formas de romperse. Y sobre todo: significaría que
 * «entrar a ver el negocio de un cliente» tendría que inventarse algún
 * puente entre las dos. Estando aquí, entrar es cambiar una variable de
 * sesión que el guardián de contexto ya sabe leer.
 *
 * ── Cómo se protege ──
 *
 * Con `sistema.superadmin`, el único permiso del sistema que no es «de un
 * negocio» sino «de todos los negocios». Lo tiene un solo rol —
 * Superadministrador — y el seeder se lo quita explícitamente al
 * Administrador, que es el rol que reciben los clientes.
 *
 * ── Todo lo de aquí corre sin filtro ──
 *
 * Es el único sitio del sistema donde eso es correcto, y por eso está
 * escrito con `Contexto::sinFiltro()` en cada consulta en vez de una vez
 * al principio: quien lea este archivo tiene que ver, línea por línea,
 * dónde se está levantando el aislamiento.
 */
class SistemaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:sistema.superadmin')];
    }

    /* ═══════════════ El tablero ═══════════════ */

    public function index(Request $request, MrrService $mrr)
    {
        $filtro = (string) $request->query('ver', 'todas');
        $q      = trim((string) $request->query('q', ''));

        $empresas = Contexto::sinFiltro(fn () => $this->listar($filtro, $q));

        return view('sistema.index', [
            'empresas' => $empresas,
            'filtro'   => $filtro,
            'q'        => $q,
            'resumen'  => Contexto::sinFiltro(fn () => $this->resumen($mrr)),
            'mes'      => Contexto::sinFiltro(fn () => $mrr->resumenDelMes()),
        ]);
    }

    /* ═══════════════ La ficha de un cliente ═══════════════ */

    public function empresa(Empresa $empresa, MrrService $mrr)
    {
        return view('sistema.empresa', Contexto::sinFiltro(fn () => [
            'empresa'    => $empresa->load('plan', 'tiendas'),
            'planes'     => Plan::orderBy('orden')->get(),
            'dueno'      => $empresa->dueno(),
            'usuarios'   => $empresa->usuarios()->orderBy('name')->get(),
            'historial'  => $empresa->movimientosMrr()->with('planDespues')->limit(20)->get(),
            'panorama'   => app(\App\Services\LimiteService::class)->panorama($empresa),
            'aporte'     => $empresa->mrr(),
        ]));
    }

    /* ═══════════════ Entrar a mirar ═══════════════ */

    /**
     * Mirar el negocio de un cliente con la propia cuenta.
     *
     * ── Por qué NO se suplanta al usuario ──
     *
     * Lo fácil sería iniciar sesión como el dueño. Y sería peor por dos
     * razones. La primera: todo lo que se tocara quedaría en la bitácora a
     * nombre del cliente, y el día que algo se dañe nadie sabría si fue él
     * o fue soporte. La segunda: una sesión iniciada como otra persona es
     * indistinguible de un robo de cuenta, incluso mirando el código.
     *
     * Así, Angel sigue siendo Angel. Cambia lo que MIRA, no quién es. La
     * auditoría queda con su nombre y la banda roja de arriba lo dice en
     * todas las pantallas.
     */
    public function entrar(Request $request, Empresa $empresa)
    {
        $request->session()->put('mus.mirando_empresa', $empresa->id);

        // La tienda se olvida: la que estuviera guardada es de otra empresa,
        // y dejarla puesta haría que el primer documento que se registre
        // caiga en el local equivocado.
        $request->session()->forget('mus.tienda_id');

        Contexto::olvidar();

        return redirect()->route('dashboard')
            ->with('success', 'Estás viendo «' . $empresa->nombre . '». Todo lo que hagas queda a tu nombre.');
    }

    public function salir(Request $request)
    {
        $request->session()->forget(['mus.mirando_empresa', 'mus.tienda_id']);

        Contexto::olvidar();

        return redirect()->route('sistema.index')->with('success', 'Volviste a tu propio negocio.');
    }

    /* ═══════════════ Acciones comerciales ═══════════════ */

    public function suscribir(Request $request, Empresa $empresa, MrrService $mrr)
    {
        $datos = $request->validate([
            'plan_id'        => ['nullable', 'integer', Rule::exists('planes', 'id')],
            'periodo'        => ['required', Rule::in(['mensual', 'anual'])],
            'precio_pactado' => ['nullable', 'numeric', 'min:0'],
            'tiendas'        => ['nullable', 'integer', 'min:1', 'max:99'],
            'motivo'         => ['nullable', 'string', 'max:200'],
        ], [
            'periodo.required' => 'Di si paga mes a mes o el año.',
        ]);

        /**
         * `??` en todos, y no solo en algunos.
         *
         * `validate()` devuelve únicamente las claves que VINIERON en la
         * petición: un campo `nullable` que el formulario no mandó no
         * aparece en el array, y leerlo revienta con «Undefined array key».
         *
         * Pasó de verdad: el formulario manda el precio pactado vacío, la
         * petición no lo trae, y guardar la suscripción daba error 500. Es
         * el fallo típico de `nullable` — se lee como «puede venir en
         * nulo» cuando en realidad significa «puede no venir».
         */
        $planId = $datos['plan_id'] ?? null;
        $precio = $datos['precio_pactado'] ?? null;

        $plan = $planId ? Plan::find($planId) : null;

        Contexto::sinFiltro(fn () => $mrr->cambiarPlan(
            $empresa,
            $plan,
            $datos['periodo'],
            $precio !== null ? (float) $precio : null,
            $datos['motivo'] ?? null,
            $datos['tiendas'] ?? null,
        ));

        return back()->with('success', 'Suscripción actualizada. Quedó anotada en el historial.');
    }

    public function suspender(Request $request, Empresa $empresa, MrrService $mrr)
    {
        $motivo = trim((string) $request->input('motivo')) ?: null;

        Contexto::sinFiltro(fn () => $mrr->darDeBaja($empresa, $motivo));

        return back()->with('success', '«' . $empresa->nombre . '» quedó suspendida. Sus datos siguen ahí.');
    }

    public function reactivar(Request $request, Empresa $empresa, MrrService $mrr)
    {
        $datos = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('planes', 'id')],
            'periodo' => ['required', Rule::in(['mensual', 'anual'])],
            'motivo'  => ['nullable', 'string', 'max:200'],
        ]);

        Contexto::sinFiltro(fn () => $mrr->reactivar(
            $empresa,
            Plan::findOrFail($datos['plan_id']),
            $datos['periodo'],
            null,
            $datos['motivo'] ?? null,
        ));

        return back()->with('success', '«' . $empresa->nombre . '» volvió a estar activa.');
    }

    /**
     * Regalarle más tiempo a una prueba.
     *
     * Pasa: el interesado se entusiasma, se le acaba a mitad de camino y
     * llama. Tener que crearle otra desde cero le borraría lo que ya
     * alcanzó a montar, que es justo lo que lo tenía interesado.
     */
    public function extender(Request $request, Empresa $empresa)
    {
        $datos = $request->validate([
            'minutos' => ['required', 'integer', 'min:15', 'max:10080'],
        ]);

        // Desde AHORA y no desde el vencimiento anterior: si venció ayer,
        // sumarle minutos a una fecha pasada la deja vencida igual.
        $desde = $empresa->expira_en && $empresa->expira_en->isFuture()
            ? $empresa->expira_en
            : now();

        $empresa->update([
            'expira_en' => $desde->copy()->addMinutes((int) $datos['minutos']),
            'estado'    => 'activa',
        ]);

        return back()->with('success', 'La prueba de «' . $empresa->nombre . '» va hasta '
            . $empresa->fresh()->expira_en->locale('es')->isoFormat('D MMM, h:mm a') . '.');
    }

    /* ═══════════════ Interno ═══════════════ */

    private function listar(string $filtro, string $q)
    {
        return Empresa::query()
            ->with('plan')
            ->withCount('tiendas', 'usuarios')
            ->when($q !== '', fn ($c) => $c->where(fn ($s) => $s
                ->where('nombre', 'like', "%{$q}%")
                ->orWhere('correo', 'like', "%{$q}%")
                ->orWhere('nit', 'like', "%{$q}%")))
            ->when($filtro === 'clientes', fn ($c) => $c->where('es_demo', false))
            ->when($filtro === 'pruebas', fn ($c) => $c->where('es_demo', true))
            ->when($filtro === 'suspendidas', fn ($c) => $c->where('estado', '!=', 'activa'))
            ->when($filtro === 'vencidas', fn ($c) => $c
                ->whereNotNull('expira_en')->where('expira_en', '<', now()))
            // Las pruebas vivas primero: son las que hay que atender hoy.
            ->orderByDesc('es_demo')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    /** @return array<string, int|float> */
    private function resumen(MrrService $mrr): array
    {
        $vivas = Empresa::where('es_demo', true)
            ->where('estado', 'activa')
            ->where(fn ($q) => $q->whereNull('expira_en')->orWhere('expira_en', '>', now()))
            ->count();

        return [
            'clientes'    => Empresa::where('es_demo', false)->where('estado', 'activa')->count(),
            'pruebas'     => $vivas,
            'suspendidas' => Empresa::where('estado', '!=', 'activa')->count(),
            'mrr'         => $mrr->mrrActual(),
            'arr'         => $mrr->arrActual(),
            'movimientos' => MrrMovimiento::delMes()->count(),
        ];
    }
}
