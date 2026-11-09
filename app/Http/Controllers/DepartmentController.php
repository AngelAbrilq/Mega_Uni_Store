<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rrhh\GuardarAreaRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Services\RrhhService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

/** AT-ARE-001 · Áreas del local con responsable y empleados (RN-44). */
class DepartmentController extends Controller implements HasMiddleware
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
        return view('departments.index', [
            'areas' => Department::withCount('employees')->with('manager:id,first_name,last_name')
                ->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('departments.create', ['area' => null, 'empleados' => $this->empleados()]);
    }

    public function store(GuardarAreaRequest $request)
    {
        $area = Department::create($request->validated());

        return redirect()->route('departments.show', $area)->with('success', "Área «{$area->name}» creada.");
    }

    /** Escenario 4: los empleados del área. */
    public function show(Department $department)
    {
        $department->load(['manager', 'employees' => fn ($q) => $q->with('positions')->orderBy('first_name')]);

        return view('departments.show', ['area' => $department]);
    }

    public function edit(Department $department)
    {
        return view('departments.edit', ['area' => $department, 'empleados' => $this->empleados()]);
    }

    public function update(GuardarAreaRequest $request, Department $department)
    {
        $department->update($request->validated());

        return redirect()->route('departments.show', $department)->with('success', 'Área actualizada.');
    }

    public function destroy(Department $department)
    {
        try {
            $this->rrhh->eliminarArea($department);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('departments.index')->with('success', "Área «{$department->name}» eliminada.");
    }

    private function empleados()
    {
        return Employee::activos()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code'])
            ->mapWithKeys(fn ($e) => [$e->id => "{$e->nombre_completo} · {$e->code}"]);
    }
}
