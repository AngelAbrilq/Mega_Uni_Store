<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plataforma\ExtenderPruebaRequest;
use App\Http\Requests\Plataforma\ReactivarNegocioRequest;
use App\Http\Requests\Plataforma\SuscribirNegocioRequest;
use App\Http\Requests\Plataforma\CrearNegocioRequest;
use App\Models\Empresa;
use App\Models\MrrMovimiento;
use App\Models\Plan;
use App\Services\DemoService;
use App\Services\MrrService;
use App\Support\Contexto;
use App\Support\Rubros;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

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

    /* ═══════════════ Dar de alta un cliente ═══════════════ */

    /**
     * El formulario de alta.
     *
     * ── Por qué hacía falta ──
     *
     * Hasta ahora la única puerta para que naciera un negocio era /probar,
     * la demostración pública. Si el cliente llamaba por teléfono, tocaba
     * hacerle la demostración uno mismo con su correo y suscribirla
     * después — y el negocio nacía con los productos y la gente de ejemplo
     * del rubro adentro, que él tenía que borrar uno por uno.
     */
    public function nuevo()
    {
        return view('sistema.nuevo', Contexto::sinFiltro(fn () => [
            'rubros' => Rubros::paraEscoger(),
            'planes' => Plan::activos()->where('es_demo', false)->orderBy('orden')->get(),
        ]));
    }

    /**
     * Crea el negocio, su primer local y la cuenta del dueño.
     *
     * Reutiliza `DemoService` a propósito: es el mismo montaje que el de
     * la demostración, con otro plan, sin fecha de vencimiento y —si así
     * se pide— sin los productos de ejemplo. Dos caminos distintos para lo
     * mismo terminan siempre igual: uno de los dos se queda sin algo.
     */
    public function crear(CrearNegocioRequest $request, DemoService $montaje)
    {
        $datos = $request->validated();

        $plan = isset($datos['plan_id']) ? Plan::find($datos['plan_id']) : null;

        /**
         * Aquí NO se levanta el aislamiento, y es la única acción de este
         * controlador donde no se hace.
         *
         * `montar()` se mete en la empresa nueva con `Contexto::comoEmpresa`
         * y escribe todo el catálogo ahí adentro. Si además corriera sin
         * filtro, las consultas de ese montaje —«¿ya existe este ajuste?»,
         * «¿ya existe esta unidad?»— verían las de TODOS los clientes y
         * podrían encontrar la de otro y escribirle encima. Levantar el
         * filtro sirve para leer de todos; para escribir en uno, estorba.
         */
        $usuario = $montaje->montar(
            $datos,
            $plan,
            esDemo: false,
            conMuestra: $request->boolean('con_muestra'),
        );

        $empresa = $usuario->empresas()->first();

        return redirect()->route('sistema.empresa', $empresa)
            ->with('success', '«' . $empresa->nombre . '» quedó creado. '
                . 'El dueño entra con ' . $usuario->email . ' y la contraseña que definiste.');
    }

    /* ═══════════════ La ficha de un cliente ═══════════════ */

    public function empresa(Empresa $empresa, MrrService $mrr)
    {
        return view('sistema.empresa', Contexto::sinFiltro(fn () => [
            'empresa'    => $empresa->load('plan', 'tiendas'),
            'planes'     => Plan::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $empresa->plan_id))
                ->orderBy('orden')->get(),
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

    public function suscribir(SuscribirNegocioRequest $request, Empresa $empresa, MrrService $mrr)
    {
        $datos = $request->validated();

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

    public function reactivar(ReactivarNegocioRequest $request, Empresa $empresa, MrrService $mrr)
    {
        $datos = $request->validated();

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
    public function extender(ExtenderPruebaRequest $request, Empresa $empresa)
    {
        $datos = $request->validated();

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
