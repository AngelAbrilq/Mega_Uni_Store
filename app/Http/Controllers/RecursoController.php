<?php

namespace App\Http\Controllers;

use App\Models\Bloqueo;
use App\Models\Horario;
use App\Models\Recurso;
use App\Models\User;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Quién atiende, cuándo, y cuándo no.
 *
 * Los horarios se editan aquí y no en la agenda a propósito: el horario se
 * define una vez y se mira poco, mientras que la agenda se toca todo el
 * día. Mezclarlos haría que quien viene a agendar tenga que pasar por
 * encima de la configuración cada vez.
 */
class RecursoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:agenda.recursos')];
    }

    public function index()
    {
        return view('recursos.index', [
            'recursos' => Recurso::with('horarios', 'usuario:id,name')
                ->withCount(['citas as citas_futuras' => fn ($q) => $q->vivas()->where('inicio', '>=', now())])
                ->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    public function create()
    {
        return view('recursos.create', $this->listas());
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        $recurso = Recurso::create($datos + ['tienda_id' => Contexto::tiendaId()]);

        $this->guardarHorarios($request, $recurso);

        return redirect()->route('recursos.index')
            ->with('success', $recurso->nombre . ' quedó en la agenda.');
    }

    public function edit(Recurso $recurso)
    {
        return view('recursos.edit', $this->listas() + [
            'recurso'  => $recurso->load('horarios', 'bloqueos'),
        ]);
    }

    public function update(Request $request, Recurso $recurso)
    {
        $recurso->update($this->validar($request));

        $this->guardarHorarios($request, $recurso);

        return redirect()->route('recursos.index')
            ->with('success', $recurso->nombre . ' actualizado.');
    }

    public function destroy(Recurso $recurso)
    {
        $porDelante = $recurso->citas()->vivas()->where('inicio', '>=', now())->count();

        if ($porDelante > 0) {
            return back()->with('error',
                $recurso->nombre . ' tiene ' . $porDelante . ' cita(s) por delante. '
                . 'Muévelas o cancélalas antes de quitarlo — si no, esos clientes se quedan sin quien los atienda.');
        }

        $nombre = $recurso->nombre;
        $recurso->delete();

        return redirect()->route('recursos.index')->with('success', $nombre . ' salió de la agenda.');
    }

    /* ═══════════════ Bloqueos ═══════════════ */

    public function bloquear(Request $request, Recurso $recurso)
    {
        $datos = $request->validate([
            'inicio' => ['required', 'date'],
            'fin'    => ['required', 'date', 'after:inicio'],
            'motivo' => ['nullable', 'string', 'max:150'],
        ], [
            'fin.after' => 'El bloqueo tiene que terminar después de empezar.',
        ]);

        $chocan = $recurso->citas()->vivas()->quePisan($datos['inicio'], $datos['fin'])->count();

        if ($chocan > 0) {
            return back()->with('error',
                'Hay ' . $chocan . ' cita(s) agendada(s) en ese rato. '
                . 'Muévelas primero: bloquear encima las dejaría sin quien las atienda.');
        }

        Bloqueo::create($datos + ['recurso_id' => $recurso->id]);

        return back()->with('success', 'Bloqueo agregado.');
    }

    public function desbloquear(Recurso $recurso, Bloqueo $bloqueo)
    {
        abort_unless($bloqueo->recurso_id === $recurso->id, 404);

        $bloqueo->delete();

        return back()->with('success', 'Bloqueo quitado.');
    }

    /* ═══════════════ Apoyo ═══════════════ */

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'  => ['required', 'string', 'max:120'],
            'tipo'    => ['required', Rule::in(array_keys(Recurso::TIPOS))],
            'color'   => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'activo'  => ['nullable', 'boolean'],
            'orden'   => ['nullable', 'integer', 'min:0', 'max:999'],
            'notas'   => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.required' => '¿Cómo se llama?',
            'color.regex'     => 'El color va en formato #RRGGBB.',
        ]) + ['activo' => $request->boolean('activo')];
    }

    /**
     * Reemplaza el horario completo.
     *
     * Se borra y se vuelve a escribir en vez de ir comparando fila por
     * fila. Son a lo sumo catorce filas por recurso, y el código que
     * compara —«esta franja ya estaba, esta cambió, esta se fue»— es tres
     * veces más largo y se equivoca de maneras que nadie nota hasta que un
     * martes desaparece.
     */
    private function guardarHorarios(Request $request, Recurso $recurso): void
    {
        $franjas = $request->input('horarios', []);

        if (! is_array($franjas)) {
            return;
        }

        $recurso->horarios()->delete();

        foreach ($franjas as $dia => $lista) {
            if (! is_array($lista)) {
                continue;
            }

            foreach ($lista as $franja) {
                $desde = $franja['desde'] ?? null;
                $hasta = $franja['hasta'] ?? null;

                // Una franja a medio llenar se ignora en silencio: la fila
                // vacía del formulario es lo normal, no un error.
                if (! $desde || ! $hasta || $hasta <= $desde) {
                    continue;
                }

                Horario::create([
                    'recurso_id' => $recurso->id,
                    'dia_semana' => (int) $dia,
                    'desde'      => $desde,
                    'hasta'      => $hasta,
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function listas(): array
    {
        return [
            'tipos'    => Recurso::TIPOS,
            'usuarios' => User::deLaEmpresa()->orderBy('name')->get(['id', 'name']),
            'dias'     => Horario::DIAS,
        ];
    }
}
