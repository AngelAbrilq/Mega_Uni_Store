<?php

namespace App\Http\Controllers;

use App\Support\CajonDelLocal;
use App\Models\Tienda;
use App\Http\Requests\Ventas\RegistrarVentaRequest;
use App\Models\AuditLog;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\CouponService;
use App\Services\PrinterService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

/**
 * Punto de venta: la pantalla donde se atiende al cliente.
 */
class PosController extends Controller implements HasMiddleware
{
    public function __construct(
        private SaleService $ventas,
        private PrinterService $hardware,
        private CouponService $cupones,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ventas.crear'),
        ];
    }

    public function index(Request $request)
    {
        $turno = CashSession::abiertaDe($request->user()->id);

        return view('pos.index', [
            'turno'     => $turno,
            // Cómo se abre el cajón de ESTE local: servidor, agente o nada.
            'cajon'     => CajonDelLocal::configuracion(),
            'medios'    => PaymentMethod::active()->orderBy('name')->get(['id', 'name']),
            'clientes'  => Customer::orderBy('first_name')->take(300)
                                   ->get(['id', 'first_name', 'last_name', 'document_number']),
            // enTienda(): solo lo que ESTE local vende. Un producto sin
            // fila de existencia aquí no existe para este mostrador — es lo
            // que permite que la ferretería del centro maneje cemento y la
            // del norte no, sin duplicar el catálogo.
            'productos' => Product::query()
                ->active()
                ->enTienda()
                ->with('tax:id,name,rate,type', 'extraTaxes:id,name,rate,type', 'unit:id,symbol')
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'barcode', 'price', 'cost', 'unit_id', 'tax_id', 'category_id', 'image_url'])
                ->map(fn (Product $p) => [
                    'id'     => $p->id,
                    'nombre' => $p->name,
                    'sku'    => $p->sku,
                    'codigo' => $p->barcode,
                    // El precio de este local, que puede pisar el del catálogo.
                    'precio' => (float) $p->precio_tienda,
                    'stock'  => (float) $p->stock,
                    'unidad' => $p->unit->symbol ?? 'und',
                    // AT-PRI-001: todos los impuestos del producto (principal + adicionales).
                    'imps'   => $p->impuestos()->map(fn ($t) => [
                        'nombre' => $t->name,
                        'tasa'   => (float) $t->rate,
                        'fijo'   => $t->type === 'fixed',
                    ])->values(),
                    'foto'   => $p->imagen,
                ])
                ->values(),
        ]);
    }

    /**
     * Vista previa de un cupón para el carrito (VD-CUP-001).
     *
     * Solo informa: no gasta usos. El descuento definitivo lo vuelve a
     * calcular SaleService al cobrar, con el cupón bloqueado.
     */
    public function cupon(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:40'],
            'base'   => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        try {
            ['cupon' => $cupon, 'descuento' => $descuento] = $this->cupones->evaluar($datos['codigo'], (float) $datos['base']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->errors()['cupon'][0] ?? 'Cupón inválido.'], 422);
        }

        return response()->json([
            'ok'        => true,
            'codigo'    => $cupon->code,
            'tipo'      => $cupon->type,
            'valor'     => (float) $cupon->value,
            'tope'      => $cupon->max_discount !== null ? (float) $cupon->max_discount : null,
            'minimo'    => $cupon->min_purchase !== null ? (float) $cupon->min_purchase : null,
            'descuento' => $descuento,
            'mensaje'   => "Cupón {$cupon->code} ({$cupon->etiqueta}) aplicado.",
        ]);
    }

    /** Registra la venta y manda al recibo. */
    public function store(RegistrarVentaRequest $request)
    {
        $datos = $request->validated();

        try {
            $venta = $this->ventas->registrar(
                lineas: $datos['items'],
                pagos: $datos['pagos'],
                userId: $request->user()->id,
                customerId: $datos['customer_id'] ?? null,
                notas: $datos['notes'] ?? null,
                // H-F01 · el precio enviado por el navegador solo se acepta con permiso.
                permitirCambioDePrecio: $request->user()->can('ventas.cambiar_precio'),
                codigoCupon: $datos['cupon'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        // imprimir=1 hace que el detalle abra el recibo apenas carga. Se
        // respeta lo que diga Configuración › Recibo: hay negocios que casi
        // nunca imprimen y para ellos esa ventana es un estorbo cada venta.
        $parametros = [$venta];

        if (\App\Models\Setting::activo('recibo.auto')) {
            $parametros['imprimir'] = 1;
        }

        return redirect()
            ->route('sales.show', $parametros)
            ->with('success', 'Venta ' . $venta->number . ' registrada por '
                            . \App\Support\Formato::moneda($venta->total) . '.');
    }

    /**
     * Pulso al cajón monedero.
     *
     * Va aparte de store() a propósito: el navegador la dispara sin esperar
     * respuesta mientras el formulario de la venta viaja por su lado. Así el
     * cajón se abre al instante del clic y no después del round-trip de la
     * venta + el redirect al recibo — que es medio segundo largo con el
     * cliente esperando el vuelto.
     *
     * Siempre responde 200. Un 500 aquí ensuciaría la consola del cajero por
     * algo que no le impide cobrar; el detalle del fallo queda en el log.
     */
    public function abrirCajon(Request $request): JsonResponse
    {
        $modo = CajonDelLocal::modo();

        /*
         * Modo agente: el pulso ya lo dio el agente del PC de caja; el
         * navegador solo avisa para dejar el rastro de la apertura manual.
         * El servidor (en la nube) no toca hardware que no puede alcanzar.
         */
        if ($modo !== Tienda::CAJON_SERVIDOR) {
            $ok = $modo === Tienda::CAJON_AGENTE && $request->boolean('ok');

            if ($modo === Tienda::CAJON_AGENTE && $request->boolean('manual')) {
                $this->registrarAperturaManual($request, $ok, 'agente');
            }

            return response()->json(['abierto' => $ok]);
        }

        $ok = $this->hardware->abrirCajon();

        // Abrir la caja "sin venta" es EL movimiento que un arqueo no puede
        // explicar solo: no deja rastro en ventas ni en movimientos de caja.
        // Si el cajero lo hizo a mano, queda escrito quién y cuándo.
        if ($request->boolean('manual')) {
            $this->registrarAperturaManual($request, $ok);
        }

        return response()->json([
            'abierto' => $ok,
            'estado'  => $this->hardware->estado(forzar: false),
        ]);
    }

    /**
     * Diagnóstico del cajón sin abrirlo, para el indicador del mostrador.
     */
    public function estadoCajon(Request $request): JsonResponse
    {
        // En modo agente el semáforo lo pinta el navegador preguntándole al agente.
        if (CajonDelLocal::modo() !== Tienda::CAJON_SERVIDOR) {
            return response()->json(['nivel' => 'caido', 'mensaje' => 'El cajón de este local no lo maneja el servidor.']);
        }

        return response()->json(
            $this->hardware->estado(forzar: $request->boolean('forzar'))
        );
    }

    /* ------------------------------------------------------------------ */

    private function registrarAperturaManual(Request $request, bool $ok, string $via = 'servidor'): void
    {
        $usuario = $request->user();
        $turno   = CashSession::abiertaDe($usuario->id);

        // audit_logs usa morphs() no nulos: se cuelga del turno si lo hay,
        // y si no, del propio usuario. Nunca queda huérfano.
        $sujeto = $turno ?? $usuario;

        AuditLog::create([
            'user_id'         => $usuario->id,
            'user_name'       => $usuario->name,
            'event'           => 'cajon_abierto',
            'auditable_type'  => $sujeto::class,
            'auditable_id'    => $sujeto->id,
            'auditable_label' => $turno
                ? 'Turno #' . $turno->id
                : 'Sin turno abierto',
            'new_values'      => [
                'origen'    => 'boton_manual',
                'via'       => $via,
                'resultado' => $ok ? 'pulso enviado' : 'sin respuesta del hardware',
            ],
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'url'        => substr($request->fullUrl(), 0, 255),
        ]);
    }
}
