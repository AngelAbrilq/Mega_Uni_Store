<?php

namespace App\Services;

use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Recurso;
use App\Support\Contexto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agendar, mover y cerrar citas.
 *
 * ── La única regla que importa ──
 *
 * Un recurso no puede tener dos citas a la vez. Suena obvio y es lo que
 * casi todas las agendas caseras hacen mal, porque lo comprueban en el
 * formulario y no al guardar: dos personas agendando desde dos mostradores
 * ven las 3 p.m. libre, las dos guardan, y el estilista tiene dos clientes
 * sentados esperando.
 *
 * Aquí la comprobación va DENTRO de la transacción y con la fila bloqueada
 * (`lockForUpdate`). El segundo en llegar espera al primero, vuelve a
 * mirar, y encuentra la franja ocupada. Cuesta un bloqueo de base de
 * datos; ahorra una discusión en el mostrador.
 *
 * ── Las otras tres comprobaciones ──
 *
 *   · que caiga dentro del horario del recurso,
 *   · que no pise un bloqueo (vacaciones, almuerzo, festivo),
 *   · que dure más de cero minutos.
 *
 * Las cuatro devuelven un mensaje que se entiende, no una excepción
 * técnica: quien está en el mostrador con el cliente enfrente necesita
 * saber qué hacer, no qué se rompió.
 */
class AgendaService
{
    public function __construct(private SaleService $ventas) {}

    /* ═══════════════ Agendar ═══════════════ */

    /**
     * @param  array{recurso_id:int, inicio:string|Carbon, customer_id?:?int,
     *               product_id?:?int, titulo?:?string, minutos?:?int,
     *               precio?:?float, notas?:?string, estado?:?string}  $datos
     */
    public function agendar(array $datos, ?int $userId = null): Cita
    {
        $recurso = $this->recursoDeAqui((int) $datos['recurso_id']);
        $inicio  = $this->aFecha($datos['inicio']);

        [$titulo, $minutos, $precio, $producto] = $this->desdeElServicio($datos);

        $fin = $inicio->copy()->addMinutes($minutos);

        return DB::transaction(function () use ($recurso, $inicio, $fin, $titulo, $precio, $producto, $datos, $userId) {
            $this->comprobar($recurso, $inicio, $fin);

            return Cita::create([
                'tienda_id'   => Contexto::tiendaId(),
                'numero'      => ConsecutivoService::siguiente('citas', Contexto::tiendaId()),
                'recurso_id'  => $recurso->id,
                'customer_id' => $datos['customer_id'] ?? null,
                'product_id'  => $producto?->id,
                'titulo'      => $titulo,
                'inicio'      => $inicio,
                'fin'         => $fin,
                'estado'      => $datos['estado'] ?? 'pendiente',
                'precio'      => $precio,
                'notas'       => $datos['notas'] ?? null,
                'user_id'     => $userId,
            ]);
        });
    }

    /**
     * Mover una cita a otra hora, u otro recurso.
     *
     * Es la operación que más se hace en una agenda de verdad —el cliente
     * llama y pide correrse— y por eso pasa por las mismas cuatro
     * comprobaciones que agendar. Una agenda que deja mover sin revisar es
     * una agenda que se puede romper con dos clics.
     */
    public function mover(Cita $cita, Carbon|string $nuevoInicio, ?int $recursoId = null, ?int $minutos = null): Cita
    {
        $recurso = $this->recursoDeAqui($recursoId ?? (int) $cita->recurso_id);
        $inicio  = $this->aFecha($nuevoInicio);
        $fin     = $inicio->copy()->addMinutes($minutos ?? $cita->duracion());

        return DB::transaction(function () use ($cita, $recurso, $inicio, $fin) {
            // Se excluye ella misma: si no, una cita chocaría consigo misma
            // al arrastrarla cinco minutos.
            $this->comprobar($recurso, $inicio, $fin, $cita->id);

            $cita->update([
                'recurso_id' => $recurso->id,
                'inicio'     => $inicio,
                'fin'        => $fin,
            ]);

            return $cita->fresh();
        });
    }

    /* ═══════════════ Cerrar ═══════════════ */

    public function cambiarEstado(Cita $cita, string $estado, ?string $notas = null): Cita
    {
        if (! array_key_exists($estado, Cita::ESTADOS)) {
            throw ValidationException::withMessages(['estado' => 'Ese estado no existe.']);
        }

        if ($cita->sale_id !== null && $estado !== 'atendida') {
            throw ValidationException::withMessages([
                'estado' => 'Esta cita ya se cobró (venta ' . $cita->sale->number . '). '
                    . 'Anula la venta antes de cambiarle el estado.',
            ]);
        }

        $cita->update([
            'estado'      => $estado,
            'notas'       => $notas ?? $cita->notas,
            'atendida_en' => $estado === 'atendida' ? ($cita->atendida_en ?? now()) : null,
        ]);

        return $cita->fresh();
    }

    /**
     * Convertir la cita en venta.
     *
     * ── Por qué no se cobra sola al marcarla atendida ──
     *
     * Porque atender y cobrar no siempre pasan juntos: el cliente sale, se
     * peina, vuelve al mostrador y ahí paga —a veces con dos medios, a
     * veces con un descuento que se acordó en la silla—. Cobrar automático
     * generaría ventas que después hay que anular, y una venta anulada
     * ensucia el consecutivo y la caja.
     *
     * Así, marcar atendida es un clic y cobrar es otro. El segundo lleva al
     * punto de venta de siempre, con todo lo que ya sabe hacer.
     */
    public function aVenta(Cita $cita, int $userId, ?int $paymentMethodId = null)
    {
        if (! $cita->sePuedeCobrar()) {
            throw ValidationException::withMessages([
                'cita' => match (true) {
                    $cita->sale_id !== null    => 'Esta cita ya se cobró.',
                    $cita->product_id === null => 'Esta cita no tiene un servicio asociado. '
                        . 'Cóbrala desde el punto de venta.',
                    $cita->estado !== 'atendida' => 'Primero márcala como atendida.',
                    default                    => 'Esta cita no tiene precio.',
                },
            ]);
        }

        $medio = $this->medioDePago($paymentMethodId);

        return DB::transaction(function () use ($cita, $userId, $medio) {
            $venta = $this->ventas->registrar(
                [[
                    'product_id' => $cita->product_id,
                    'quantity'   => 1,
                    // El precio de la cita, no el del catálogo: si se
                    // negoció en la silla, se cobra lo negociado.
                    'unit_price' => (float) $cita->precio,
                ]],
                [['payment_method_id' => $medio, 'amount' => (float) $cita->precio]],
                $userId,
                $cita->customer_id,
                'Cita ' . $cita->numero,
                // Un servicio no descuenta inventario. Si además gasta
                // producto —un tinte, por ejemplo—, eso se descuenta aparte
                // y a propósito, no de rebote al cobrar.
                descontarStock: $cita->product?->duracion_minutos === null,
            );

            $cita->update(['sale_id' => $venta->id]);

            return $venta;
        });
    }

    /**
     * Con qué se cobra.
     *
     * ── Por qué esto existe ──
     *
     * Una venta sin pago no es una venta: es un saldo pendiente, y este
     * botón no está para eso. `SaleService` lo sabe y rechaza la venta
     * cuando lo recibido no cubre el total — pero el mensaje que sale de
     * ahí es «lo recibido ($0) no alcanza», que en esta pantalla no explica
     * nada: quien la lee no puso ningún cero en ninguna parte.
     *
     * Así que el medio se resuelve aquí. Si vino uno, ese. Si no, el
     * primero activo del negocio, que en el mostrador es lo que pasa: se
     * cobra en efectivo y punto. Y si el negocio no tiene ninguno
     * configurado, el error dice exactamente eso y adónde ir.
     */
    private function medioDePago(?int $paymentMethodId): int
    {
        if ($paymentMethodId !== null) {
            $medio = PaymentMethod::active()->find($paymentMethodId);

            if ($medio === null) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Ese medio de pago no existe o está inactivo.',
                ]);
            }

            return $medio->id;
        }

        $primero = PaymentMethod::active()->orderBy('id')->value('id');

        if ($primero === null) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'No hay medios de pago activos. '
                    . 'Crea al menos uno en Configuración → Medios de pago antes de cobrar.',
            ]);
        }

        return (int) $primero;
    }

    /* ═══════════════ Consultar ═══════════════ */

    /**
     * Los huecos libres de un recurso en un día.
     *
     * Se arma restando: se toma su horario del día y se le quitan las
     * citas vivas y los bloqueos. Lo que queda son los huecos.
     *
     * Se devuelven los huecos y no una lista de horas de media en media
     * hora porque los servicios duran distinto: ofrecer «3:00, 3:30, 4:00»
     * para un servicio de 45 minutos propone horas que no caben.
     *
     * @return array<int, array{desde:Carbon, hasta:Carbon, minutos:int}>
     */
    public function huecos(Recurso $recurso, Carbon $dia, int $minutosMinimos = 15): array
    {
        $ocupado = $this->ocupacionDe($recurso, $dia);
        $huecos  = [];

        foreach ($recurso->franjasDe($dia) as $franja) {
            $cursor = $franja->inicioEn($dia);
            $tope   = $franja->finEn($dia);

            foreach ($ocupado as [$desde, $hasta]) {
                if ($hasta->lessThanOrEqualTo($cursor) || $desde->greaterThanOrEqualTo($tope)) {
                    continue;   // fuera de esta franja
                }

                if ($desde->greaterThan($cursor)) {
                    $this->agregarHueco($huecos, $cursor, $desde, $minutosMinimos);
                }

                if ($hasta->greaterThan($cursor)) {
                    $cursor = $hasta->copy();
                }
            }

            $this->agregarHueco($huecos, $cursor, $tope, $minutosMinimos);
        }

        return $huecos;
    }

    /**
     * Cuánto de su jornada tiene vendido, en porcentaje.
     *
     * Es el número que contesta «¿me sobra gente o me falta?», que es la
     * decisión de plata que una agenda tiene que ayudar a tomar.
     */
    public function ocupacion(Recurso $recurso, Carbon $dia): int
    {
        $jornada = 0;

        foreach ($recurso->franjasDe($dia) as $franja) {
            $jornada += $franja->minutos();
        }

        if ($jornada <= 0) {
            return 0;
        }

        $vendido = Cita::vivas()
            ->where('recurso_id', $recurso->id)
            ->delDia($dia)
            ->get()
            ->sum(fn (Cita $c) => $c->duracion());

        return (int) min(100, round($vendido / $jornada * 100));
    }

    /* ═══════════════ Las comprobaciones ═══════════════ */

    /**
     * Las cuatro, en orden de lo más barato a lo más caro de consultar.
     *
     * @throws ValidationException
     */
    private function comprobar(Recurso $recurso, Carbon $inicio, Carbon $fin, ?int $exceptoCita = null): void
    {
        /* 1 · Que dure algo. */
        if ($fin->lessThanOrEqualTo($inicio)) {
            throw ValidationException::withMessages([
                'minutos' => 'La cita tiene que durar más de cero minutos.',
            ]);
        }

        /* 2 · Que el recurso trabaje a esa hora. */
        if (! $recurso->trabajaEl($inicio)) {
            throw ValidationException::withMessages([
                'inicio' => $recurso->nombre . ' no trabaja los '
                    . mb_strtolower($inicio->locale('es')->isoFormat('dddd')) . '.',
            ]);
        }

        if (! $recurso->cabeEnSuHorario($inicio, $fin)) {
            $franjas = $recurso->franjasDe($inicio)->map(fn ($f) => $f->texto())->implode(' y ');

            throw ValidationException::withMessages([
                'inicio' => 'Esa hora se sale del horario de ' . $recurso->nombre
                    . ', que ese día atiende de ' . $franjas . '.',
            ]);
        }

        /* 3 · Que no pise un bloqueo. */
        $bloqueo = Bloqueo::where('recurso_id', $recurso->id)
            ->quePisan($inicio, $fin)
            ->first();

        if ($bloqueo) {
            throw ValidationException::withMessages([
                'inicio' => $recurso->nombre . ' no está disponible en ese rato'
                    . ($bloqueo->motivo ? ' (' . $bloqueo->motivo . ')' : '') . '.',
            ]);
        }

        /* 4 · Que no haya otra cita.
           `lockForUpdate` es lo que hace segura esta comprobación cuando
           dos personas agendan a la vez: la segunda espera a que la
           primera termine, y entonces sí ve la franja ocupada. */
        $choca = Cita::vivas()
            ->where('recurso_id', $recurso->id)
            ->quePisan($inicio, $fin)
            ->when($exceptoCita, fn ($q) => $q->whereKeyNot($exceptoCita))
            ->lockForUpdate()
            ->first();

        if ($choca) {
            throw ValidationException::withMessages([
                'inicio' => $recurso->nombre . ' ya tiene «' . $choca->titulo . '» de '
                    . $choca->horas() . '. Escoge otra hora.',
            ]);
        }
    }

    /* ═══════════════ Interno ═══════════════ */

    /**
     * Lo que ya está tomado ese día: citas vivas y bloqueos, en orden.
     *
     * @return array<int, array{0:Carbon, 1:Carbon}>
     */
    private function ocupacionDe(Recurso $recurso, Carbon $dia): array
    {
        $inicioDia = $dia->copy()->startOfDay();
        $finDia    = $dia->copy()->endOfDay();

        $tomado = Cita::vivas()
            ->where('recurso_id', $recurso->id)
            ->entre($inicioDia, $finDia)
            ->get()
            ->map(fn (Cita $c) => [$c->inicio->copy(), $c->fin->copy()])
            ->all();

        foreach (Bloqueo::where('recurso_id', $recurso->id)->quePisan($inicioDia, $finDia)->get() as $b) {
            $tomado[] = [$b->inicio->copy(), $b->fin->copy()];
        }

        usort($tomado, fn ($a, $b) => $a[0] <=> $b[0]);

        return $tomado;
    }

    private function agregarHueco(array &$huecos, Carbon $desde, Carbon $hasta, int $minimos): void
    {
        $minutos = (int) $desde->diffInMinutes($hasta, false);

        if ($minutos >= $minimos) {
            $huecos[] = ['desde' => $desde->copy(), 'hasta' => $hasta->copy(), 'minutos' => $minutos];
        }
    }

    /**
     * De dónde salen el título, la duración y el precio.
     *
     * Del servicio si se escogió uno, y lo que se mande a mano manda sobre
     * eso — porque en la vida real se negocia: «te lo dejo en 30 y me
     * demoro menos».
     *
     * @return array{0:string, 1:int, 2:float, 3:?Product}
     */
    private function desdeElServicio(array $datos): array
    {
        $producto = ! empty($datos['product_id']) ? Product::find($datos['product_id']) : null;

        $titulo = trim((string) ($datos['titulo'] ?? '')) ?: ($producto?->name ?? 'Cita');

        $minutos = (int) ($datos['minutos'] ?? $producto?->duracion_minutos ?? 30);

        $precio = isset($datos['precio'])
            ? (float) $datos['precio']
            : (float) ($producto?->precio_tienda ?? 0);

        return [$titulo, max(0, $minutos), $precio, $producto];
    }

    /**
     * El recurso, comprobando que sea de este negocio.
     *
     * El filtro global ya lo acota, así que un id ajeno simplemente no
     * aparece — pero se comprueba igual y con un mensaje claro, porque el
     * id llega de un formulario y del formulario llega cualquier cosa.
     */
    private function recursoDeAqui(int $id): Recurso
    {
        $recurso = Recurso::with('horarios')->find($id);

        if (! $recurso) {
            throw ValidationException::withMessages([
                'recurso_id' => 'Ese recurso no es de este negocio.',
            ]);
        }

        if (! $recurso->activo) {
            throw ValidationException::withMessages([
                'recurso_id' => $recurso->nombre . ' está inactivo. Actívalo antes de agendarle.',
            ]);
        }

        return $recurso;
    }

    private function aFecha(Carbon|string $valor): Carbon
    {
        return $valor instanceof Carbon ? $valor->copy() : Carbon::parse($valor);
    }
}
