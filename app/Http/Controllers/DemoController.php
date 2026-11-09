<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plataforma\CrearDemoRequest;
use App\Services\DemoService;
use App\Support\Contexto;
use App\Support\Rubros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * «Quiero probarlo» sin que nadie tenga que estar disponible.
 *
 * ── El flujo entero ──
 *
 * Un interesado llega a /probar, dice cómo se llama su negocio y de qué
 * clase es, y en el siguiente clic está adentro con su catálogo puesto y
 * 2 h 30 por delante. No hay correo de confirmación, no hay cita, no hay
 * que esperar a nadie.
 *
 * ── Por qué no se pide confirmar el correo ──
 *
 * Porque el correo sin confirmar no hace daño aquí: la cuenta se muere
 * sola en dos horas y media. Pedir confirmación agregaría un paso —salir
 * del sitio, abrir el correo, volver— justo en el momento de mayor
 * intención, que es cuando menos hay que interrumpir. Si el correo es
 * falso, se perdió un contacto; si el paso extra hace que se vaya, se
 * perdió el cliente.
 */
class DemoController extends Controller
{
    public function crear()
    {
        return view('demo.crear', [
            'rubros' => Rubros::paraEscoger(),
            'minutos' => DemoService::MINUTOS,
        ]);
    }

    public function guardar(CrearDemoRequest $request, DemoService $demos)
    {
        $datos = $request->validated();

        $usuario = $demos->crear($datos);

        Auth::login($usuario);

        $request->session()->regenerate();

        // El contexto se pone a mano: la sesión acaba de nacer y el
        // middleware ya corrió para esta petición. Sin esto, la primera
        // pantalla se dibujaría antes de saber en qué negocio está.
        $empresa = $usuario->empresas()->first();
        Contexto::usar($empresa->id, $usuario->tiendas()->value('tiendas.id'));

        return redirect()->route('dashboard')
            ->with('mus_welcome', $usuario->name)
            ->with('mus_welcome_new', true)
            ->with('success', 'Tu prueba está lista. Tienes ' . DemoService::MINUTOS . ' minutos.');
    }
}
