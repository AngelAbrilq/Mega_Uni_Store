<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Recurso;
use App\Services\AgendaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * La agenda del día.
 *
 * ── Por qué el día y no el mes ──
 *
 * Porque la pregunta que se hace quien está en el mostrador es «¿qué sigue
 * ahora?», no «¿cómo va el mes?». Un calendario mensual se ve muy bien en
 * una captura de pantalla y es inservible con el teléfono sonando: las
 * citas del día quedan en un cuadrito de dos centímetros.
 *
 * La vista del día pone una columna por recurso y las horas en el costado,
 * que es exactamente el papel que la agenda viene a reemplazar. El mes
 * puede venir después; el día tiene que estar desde el principio.
 */
class AgendaController extends Controller implements HasMiddleware
{
    /** De qué hora a qué hora se dibuja la rejilla, si no hay horarios. */
    private const HORA_MIN = 7;
    private const HORA_MAX = 20;

    public function __construct(private AgendaService $agenda) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:citas.ver',    only: ['index', 'show']),
            new Middleware('permission:citas.crear',  only: ['store']),
            new Middleware('permission:citas.editar', only: ['mover', 'estado', 'cobrar']),
        ];
    }

    /* ═══════════════ La pantalla ═══════════════ */

    public function index(Request $request)
    {
        $dia = $this->diaPedido($request);

        $recursos = Recurso::activos()->with('horarios')->get();

        $citas = Cita::query()
            ->with('recurso:id,nombre,color', 'customer:id,first_name,last_name,phone', 'product:id,name')
            ->delDia($dia)
            ->orderBy('inicio')
            ->get();

        [$horaDesde, $horaHasta] = $this->franjaVisible($recursos, $dia, $citas);

        return view('agenda.index', [
            'dia'        => $dia,
            'recursos'   => $recursos,
            'citas'      => $citas,
            'horaDesde'  => $horaDesde,
            'horaHasta'  => $horaHasta,
            'ocupacion'  => $recursos->mapWithKeys(
                fn (Recurso $r) => [$r->id => $this->agenda->ocupacion($r, $dia)]
            ),
            'servicios'  => Product::servicios()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get(['id', 'name', 'price', 'duracion_minutos']),
            'clientes'   => Customer::orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'resumen'    => $this->resumen($citas),
        ]);
    }

    public function show(Cita $cita)
    {
        $cita->load('recurso', 'customer', 'product', 'sale', 'user');

        return view('agenda.cita', [
            'cita' => $cita,
            // Para el selector de «con qué paga». Va aquí y no en el
            // servicio porque el servicio no debe saber que existe una
            // pantalla — pero la pantalla sí tiene que ofrecer la opción.
            'medios' => PaymentMethod::active()->orderBy('id')->get(['id', 'name']),
        ]);
    }

    /* ═══════════════ Acciones ═══════════════ */

    public function store(Request $request)
    {
        $datos = $request->validate([
            'recurso_id'  => ['required', 'integer'],
            'inicio'      => ['required', 'date'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'product_id'  => ['nullable', 'integer', Rule::exists('products', 'id')],
            'titulo'      => ['nullable', 'string', 'max:150'],
            'minutos'     => ['nullable', 'integer', 'min:5', 'max:600'],
            'precio'      => ['nullable', 'numeric', 'min:0'],
            'notas'       => ['nullable', 'string', 'max:500'],
        ], [
            'recurso_id.required' => '¿Quién la atiende?',
            'inicio.required'     => '¿A qué hora?',
            'minutos.min'         => 'Una cita de menos de cinco minutos no es una cita.',
        ]);

        $cita = $this->agenda->agendar($datos, $request->user()?->id);

        return redirect()
            ->route('agenda.index', ['dia' => $cita->inicio->toDateString()])
            ->with('success', 'Cita ' . $cita->numero . ' agendada para ' . $cita->horas() . '.');
    }

    public function mover(Request $request, Cita $cita)
    {
        $datos = $request->validate([
            'inicio'     => ['required', 'date'],
            'recurso_id' => ['nullable', 'integer'],
            'minutos'    => ['nullable', 'integer', 'min:5', 'max:600'],
        ]);

        $this->agenda->mover(
            $cita,
            $datos['inicio'],
            $datos['recurso_id'] ?? null,
            $datos['minutos'] ?? null,
        );

        return back()->with('success', 'Cita ' . $cita->numero . ' movida.');
    }

    public function estado(Request $request, Cita $cita)
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_keys(Cita::ESTADOS))],
            'notas'  => ['nullable', 'string', 'max:500'],
        ]);

        $this->agenda->cambiarEstado($cita, $datos['estado'], $datos['notas'] ?? null);

        return back()->with('success', 'Cita ' . $cita->numero . ': ' . Cita::ESTADOS[$datos['estado']] . '.');
    }

    public function cobrar(Request $request, Cita $cita)
    {
        $datos = $request->validate([
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
        ]);

        $venta = $this->agenda->aVenta($cita, $request->user()->id, $datos['payment_method_id'] ?? null);

        return redirect()->route('sales.show', $venta)
            ->with('success', 'Venta ' . $venta->number . ' generada desde la cita ' . $cita->numero . '.');
    }

    /* ═══════════════ Apoyo ═══════════════ */

    private function diaPedido(Request $request): Carbon
    {
        try {
            return $request->filled('dia')
                ? Carbon::parse($request->query('dia'))->startOfDay()
                : Carbon::today();
        } catch (\Throwable) {
            // Una fecha ilegible en la dirección no puede tumbar la agenda:
            // se abre en hoy, que es lo que el usuario quería ver.
            return Carbon::today();
        }
    }

    /**
     * De qué hora a qué hora dibujar.
     *
     * Se toma del horario de los recursos, no de una constante: una
     * peluquería que abre a las 9 no tiene por qué ver dos horas vacías
     * arriba. Y si alguna cita se salió del horario —porque el horario
     * cambió después— la rejilla se estira para no esconderla.
     */
    private function franjaVisible($recursos, Carbon $dia, $citas): array
    {
        $desde = null;
        $hasta = null;

        foreach ($recursos as $recurso) {
            foreach ($recurso->franjasDe($dia) as $franja) {
                $h1 = (int) substr((string) $franja->desde, 0, 2);
                $h2 = (int) ceil($this->aHoraDecimal((string) $franja->hasta));

                $desde = $desde === null ? $h1 : min($desde, $h1);
                $hasta = $hasta === null ? $h2 : max($hasta, $h2);
            }
        }

        foreach ($citas as $cita) {
            $desde = $desde === null ? $cita->inicio->hour : min($desde, $cita->inicio->hour);
            $hasta = max($hasta ?? 0, (int) ceil($cita->fin->hour + ($cita->fin->minute > 0 ? 1 : 0)));
        }

        return [
            max(0, $desde ?? self::HORA_MIN),
            min(24, max(($desde ?? self::HORA_MIN) + 1, $hasta ?? self::HORA_MAX)),
        ];
    }

    private function aHoraDecimal(string $hora): float
    {
        [$h, $m] = array_pad(explode(':', $hora), 2, '0');

        return (int) $h + ((int) $m) / 60;
    }

    /** @return array<string, int|float> */
    private function resumen($citas): array
    {
        return [
            'total'      => $citas->count(),
            'confirmadas'=> $citas->where('estado', 'confirmada')->count(),
            'pendientes' => $citas->where('estado', 'pendiente')->count(),
            'atendidas'  => $citas->where('estado', 'atendida')->count(),
            'no_llego'   => $citas->where('estado', 'no_llego')->count(),
            // Lo que vale el día si todo el mundo llega. No es plata en
            // caja: es lo que está en juego, que es lo que hace que uno
            // llame a confirmar.
            'en_juego'   => (float) $citas->whereIn('estado', Cita::VIVAS)->sum('precio'),
        ];
    }
}
