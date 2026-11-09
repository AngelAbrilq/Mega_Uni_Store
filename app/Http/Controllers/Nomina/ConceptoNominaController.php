<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nomina\GuardarConceptoRequest;
use App\Models\PayrollConcept;
use App\Models\PayrollItem;
use App\Services\Nomina\NominaService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** NR-NOM-002 · Catálogo de conceptos de nómina de la empresa. */
class ConceptoNominaController extends Controller implements HasMiddleware
{
    public function __construct(private NominaService $nomina) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:nomina.gestionar')];
    }

    public function index()
    {
        $this->nomina->asegurarConceptos();

        $conceptos = PayrollConcept::query()->orderByDesc('is_active')->orderByDesc('is_system')->orderBy('type')->orderBy('name')->get();
        $usos = PayrollItem::query()->whereIn('payroll_concept_id', $conceptos->pluck('id'))
            ->selectRaw('payroll_concept_id, COUNT(*) as n')->groupBy('payroll_concept_id')->pluck('n', 'payroll_concept_id');

        return view('nomina.conceptos.index', compact('conceptos', 'usos'));
    }

    public function create()
    {
        return view('nomina.conceptos.form', ['concepto' => null]);
    }

    public function store(GuardarConceptoRequest $request)
    {
        $c = PayrollConcept::create($request->datos());

        return redirect()->route('payroll_concepts.index')->with('success', "Concepto «{$c->name}» creado.");
    }

    public function edit(PayrollConcept $concepto)
    {
        return view('nomina.conceptos.form', compact('concepto'));
    }

    public function update(GuardarConceptoRequest $request, PayrollConcept $concepto)
    {
        $concepto->update($request->datos());

        return redirect()->route('payroll_concepts.index')->with('success', 'Concepto actualizado.');
    }

    /** E5 · Desactivar no borra: las nóminas viejas lo conservan. Los del sistema no se apagan. */
    public function estado(PayrollConcept $concepto)
    {
        if ($concepto->is_system) {
            return back()->with('error', 'Los conceptos de ley no se desactivan: los usa el cálculo de la nómina.');
        }

        $concepto->update(['is_active' => ! $concepto->is_active]);

        return back()->with('success', $concepto->is_active ? 'Concepto activado.' : 'Concepto desactivado: no aparecerá en nuevas liquidaciones.');
    }
}
