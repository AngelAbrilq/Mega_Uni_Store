<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\EnviarAvisoRequest;
use App\Notifications\AvisoDelSistema;
use App\Services\NotificacionService;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Notificaciones de cada usuario (SA-NOT-001).
 *
 * Leer y marcar son siempre sobre las PROPIAS: la consulta parte de
 * `$request->user()->notifications()`, así que el id de otra persona
 * simplemente no se encuentra.
 */
class NotificacionController extends Controller implements HasMiddleware
{
    public function __construct(private NotificacionService $notificaciones) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:notificaciones.enviar', only: ['create', 'store'])];
    }

    public function index(Request $request)
    {
        $soloNoLeidas = $request->boolean('no_leidas');

        return view('notifications.index', [
            'notificaciones' => $request->user()->notifications()
                ->when($soloNoLeidas, fn ($q) => $q->whereNull('read_at'))
                ->paginate(20)
                ->withQueryString(),
            'noLeidas'     => $request->user()->unreadNotifications()->count(),
            'soloNoLeidas' => $soloNoLeidas,
        ]);
    }

    /** Marcar como leída y llevar a donde apunta (si apunta a algún lado). */
    public function leer(Request $request, string $id)
    {
        $notificacion = $request->user()->notifications()->findOrFail($id);
        $notificacion->markAsRead();

        $url = $notificacion->data['url'] ?? null;

        return $url && str_starts_with($url, '/') ? redirect($url) : back();
    }

    public function leerTodas(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Todas las notificaciones quedaron como leídas.');
    }

    public function create()
    {
        return view('notifications.create', ['niveles' => AvisoDelSistema::NIVELES]);
    }

    public function store(EnviarAvisoRequest $request)
    {
        $datos = $request->validated();

        $n = $this->notificaciones->enviarAviso(
            $request->user(),
            $datos['alcance'] === 'todos' ? null : Contexto::empresaId(),
            $datos['titulo'],
            $datos['mensaje'],
            $datos['nivel'],
            $datos['url'] ?? null,
        );

        return redirect()->route('notifications.index')
            ->with('success', "Aviso enviado a {$n} " . ($n === 1 ? 'persona' : 'personas') . '.');
    }
}
