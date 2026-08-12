<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $units = Unit::latest()->paginate(10);
        return view('units.index', compact('units'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return view('units.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'symbol' => 'required|string|max:10',
            'type' => 'required|string|max:50',
            ]);
            Unit::create($data);
            return redirect()->route('units.index')->with('success', 'Unidad creada exitosamente!');
            }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit)
    {
        //
        return view('units.show', compact('unit'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Unit $unit)
    {
        //
        return view ('units.edit', compact ('unit'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Unit $unit)
    {
        //
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'symbol' => 'required|string|max:10',
            'type' => 'required|string|max:50',
        ]);
        $unit->update($data);
        return redirect()->route('units.index')->with ('success', 'unidad actualizada exitosamente!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit)
    {
        //
        $unit->delete();
        return redirect()->route('units.index')->with('success', 'Unidad eliminada exitosamente!');
    }
}
