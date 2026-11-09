<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rrhh\GuardarCargoRequest;
use App\Models\Position;
use App\Services\RrhhService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** AT-CGO-001 · Cargos del local con nivel jerárquico (RN-44). */
class PositionController extends Controller implements HasMiddleware
{
    public function __construct(private RrhhService $rrhh)
    {
    }

    public static function middleware(): array
    {
        return [new Middleware('permission:rrhh.configurar')];
    }

    public function index()
    {
        $cargos = Position::query()
            ->withCount(['employees as activos_count' => fn ($q) => $q->where('employee_position.is_active', true)])
            ->orderByDesc('is_active')->orderBy('level')->orderBy('name')->get();

        return view('positions.index', compact('cargos'));
    }

    public function create()
    {
        return view('positions.create', ['cargo' => null]);
    }

    public function store(GuardarCargoRequest $request)
    {
        $cargo = Position::create($request->validated());

        return redirect()->route('positions.index')->with('success', "Cargo «{$cargo->name}» creado.");
    }

    public function edit(Position $position)
    {
        return view('positions.edit', ['cargo' => $position]);
    }

    public function update(GuardarCargoRequest $request, Position $position)
    {
        $position->update($request->validated());

        return redirect()->route('positions.index')->with('success', 'Cargo actualizado.');
    }

    public function destroy(Position $position)
    {
        $borrado = $this->rrhh->eliminarCargo($position);

        return redirect()->route('positions.index')->with('success', $borrado
            ? "Cargo «{$position->name}» eliminado."
            : "«{$position->name}» tiene historia en el equipo: quedó desactivado.");
    }
}
