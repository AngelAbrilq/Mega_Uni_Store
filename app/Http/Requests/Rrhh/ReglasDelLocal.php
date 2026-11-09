<?php

namespace App\Http\Requests\Rrhh;

use App\Support\Contexto;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/** Reglas «del local activo» para RRHH (RN-44): nada se cruza entre locales. */
trait ReglasDelLocal
{
    protected function localId(): ?int
    {
        return Contexto::tiendaId();
    }

    protected function existeEnLocal(string $tabla): Exists
    {
        return Rule::exists($tabla, 'id')->where('tienda_id', $this->localId());
    }

    protected function unicoEnLocal(string $tabla, string $columna): Unique
    {
        return Rule::unique($tabla, $columna)->where('tienda_id', $this->localId());
    }
}
