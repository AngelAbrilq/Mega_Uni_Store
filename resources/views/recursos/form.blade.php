@php
    /* El horario guardado, por día, para rellenar los campos. */
    $guardado = ($item?->horarios ?? collect())->groupBy('dia_semana');
    $orden    = [1, 2, 3, 4, 5, 6, 0];   // lunes primero, como se lee un horario
@endphp

<div class="mrf">
    <label>
        <span>Nombre</span>
        <input type="text" name="nombre" value="{{ old('nombre', $item?->nombre) }}"
               required maxlength="120" placeholder="Laura Martínez · Silla 2 · Consultorio A">
        @error('nombre')<i>{{ $message }}</i>@enderror
    </label>

    <label>
        <span>Qué es</span>
        <select name="tipo" required>
            @foreach ($tipos as $clave => $texto)
                <option value="{{ $clave }}" @selected(old('tipo', $item?->tipo ?? 'persona') === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span>Color <small>así se ve en el calendario</small></span>
        <input type="color" name="color" value="{{ old('color', $item?->color ?? '#2E6EA8') }}" required>
        @error('color')<i>{{ $message }}</i>@enderror
    </label>

    <label>
        <span>Cuenta <small>si es alguien del equipo</small></span>
        <select name="user_id">
            <option value="">Sin cuenta</option>
            @foreach ($usuarios as $u)
                <option value="{{ $u->id }}" @selected(old('user_id', $item?->user_id) == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span>Orden <small>en el calendario</small></span>
        <input type="number" name="orden" min="0" max="999" value="{{ old('orden', $item?->orden ?? 0) }}">
    </label>

    <div class="mrf__toggle">
        <x-mus.toggle name="activo" label="Puede recibir citas"
                      hint="Los inactivos no aparecen en la agenda"
                      :checked="old('activo', $item?->activo ?? true)" />
    </div>

    <label class="mrf__ancho">
        <span>Notas</span>
        <input type="text" name="notas" maxlength="255" value="{{ old('notas', $item?->notas) }}"
               placeholder="Solo color y mechas · No atiende niños">
    </label>
</div>

{{-- ══════════ Horario ══════════ --}}
<div class="mrh">
    <header>
        <b>Cuándo trabaja</b>
        <span>Deja el día vacío si no trabaja. La segunda franja es para jornada partida.</span>
    </header>

    @foreach ($orden as $dia)
        @php
            $franjas = $guardado->get($dia, collect());
            $f1 = $franjas->get(0);
            $f2 = $franjas->get(1);
        @endphp
        <div class="mrh__dia">
            <span class="mrh__nom">{{ \App\Models\Horario::DIAS[$dia] }}</span>

            <div class="mrh__franja">
                <input type="time" name="horarios[{{ $dia }}][0][desde]"
                       value="{{ $f1 ? substr($f1->desde, 0, 5) : '' }}" aria-label="Desde">
                <em>a</em>
                <input type="time" name="horarios[{{ $dia }}][0][hasta]"
                       value="{{ $f1 ? substr($f1->hasta, 0, 5) : '' }}" aria-label="Hasta">
            </div>

            <div class="mrh__franja mrh__franja--dos">
                <input type="time" name="horarios[{{ $dia }}][1][desde]"
                       value="{{ $f2 ? substr($f2->desde, 0, 5) : '' }}" aria-label="Desde (segunda franja)">
                <em>a</em>
                <input type="time" name="horarios[{{ $dia }}][1][hasta]"
                       value="{{ $f2 ? substr($f2->hasta, 0, 5) : '' }}" aria-label="Hasta (segunda franja)">
            </div>
        </div>
    @endforeach
</div>

@push('styles')
<style>
    /* minmax(0,…): un nombre largo no puede ensanchar su columna. */
    /* Todo va con `>` a propósito. El interruptor de «Puede recibir citas»
       es un <label> con un <input> adentro: con selectores de descendiente
       (`.mrf label`) esta hoja le ganaba a la del componente, lo volvía
       `display:block` y el botón se montaba encima de su propio texto — y de
       paso estiraba la casilla escondida hasta taparle el clic a lo de al
       lado. Con `>` cada regla se queda en los campos de este formulario. */
    .mrf{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(200px,100%), 1fr)); gap:14px; }
    .mrf > label{ display:block; min-width:0; }
    .mrf > label > span{ display:block; margin-bottom:5px; font-size:12px; color:var(--ink-2); }
    .mrf > label small{ color:var(--muted); font-size:10.6px; }
    .mrf > label > input, .mrf > label > select{ width:100%; padding:9px 12px; border-radius:9px;
              border:1px solid var(--line); background:var(--card); color:var(--ink);
              font-size:13px; font-family:inherit; }
    .mrf > label > input[type=color]{ height:38px; padding:3px; cursor:pointer; }
    .mrf > label > input:focus, .mrf > label > select:focus{ outline:none; border-color:var(--a-500); }
    .mrf > label > i{ display:block; margin-top:4px; font-style:normal; font-size:11.4px; color:var(--bad); }
    .mrf__ancho{ grid-column:1 / -1; }
    .mrf__toggle{ align-self:end; }

    .mrh{ margin-top:20px; border:1px solid var(--line); border-radius:12px; overflow:hidden; }
    .mrh header{ padding:12px 15px; background:var(--paper); border-bottom:1px solid var(--line-2); }
    .mrh header b{ display:block; font-size:13.2px; color:var(--ink); }
    .mrh header span{ display:block; margin-top:2px; font-size:11.6px; color:var(--muted); }
    .mrh__dia{ display:grid; grid-template-columns:minmax(0,92px) minmax(0,1fr) minmax(0,1fr);
               gap:10px; align-items:center; padding:8px 15px; border-bottom:1px solid var(--line-2); }
    .mrh__dia:last-child{ border-bottom:0; }
    .mrh__nom{ font-size:12.6px; color:var(--ink-2); }
    .mrh__franja{ display:flex; align-items:center; gap:7px; min-width:0; }
    .mrh__franja input{ flex:1 1 0; min-width:0; padding:7px 9px; border-radius:8px;
                        border:1px solid var(--line); background:var(--card); color:var(--ink);
                        font-size:12.6px; font-family:inherit; }
    .mrh__franja input:focus{ outline:none; border-color:var(--a-500); }
    .mrh__franja em{ font-style:normal; font-size:11.4px; color:var(--muted); }
    .mrh__franja--dos input{ opacity:.72; }
    .mrh__franja--dos:focus-within input{ opacity:1; }

    @media (max-width:640px){
        .mrh__dia{ grid-template-columns:minmax(0,1fr); gap:6px; }
        .mrh__nom{ font-weight:600; color:var(--ink); }
    }
</style>
@endpush
