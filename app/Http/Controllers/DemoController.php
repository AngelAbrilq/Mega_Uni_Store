<?php

namespace App\Http\Controllers;

use App\Services\DemoService;
use App\Support\Contexto;
use App\Support\Rubros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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

    public function guardar(Request $request, DemoService $demos)
    {
        $datos = $request->validate([
            'negocio'  => ['required', 'string', 'max:120'],
            'nombre'   => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'rubro'    => ['required', Rule::in(array_keys(Rubros::paraEscoger()))],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'negocio.required'   => '¿Cómo se llama tu negocio?',
            'nombre.required'    => '¿Cómo te llamas?',
            'email.required'     => 'Necesitamos un correo para tu cuenta.',
            'email.unique'       => 'Ese correo ya tiene una cuenta. Entra con ella.',
            'rubro.required'     => 'Escoge qué clase de negocio tienes.',
            'rubro.in'           => 'Esa clase de negocio no está en la lista.',
            'password.required'  => 'Define una contraseña para entrar.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ]);

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
