<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre del rol" :value="$item?->name" :required="true"
                     max="80" hint="Cómo se llama el cargo aquí: Estilista, Bodeguero de patio, Domiciliario" />
    </div>
</div>

@php
    /* Lo que ya tiene marcado: lo guardado, o lo que quedó tras un error. */
    $marcados = collect(old('permisos', $item?->permissions->pluck('name')->all() ?? []))->all();
@endphp

<div class="mperm">
    <header class="mperm__cab">
        <div>
            <b>Qué puede hacer</b>
            <span>Marca solo lo que este cargo necesita. Lo que no se marca, no aparece en su menú.</span>
        </div>
        <button type="button" class="mperm__todo" data-perm-todo>Marcar todo</button>
    </header>

    @foreach ($grupos as $titulo => $modulos)
        <section class="mperm__g">
            <h4>{{ $titulo }}</h4>

            @foreach ($modulos as $modulo => $permisos)
                <div class="mperm__m">
                    <span class="mperm__mn">{{ $modulo }}</span>
                    <div class="mperm__ops">
                        @foreach ($permisos as $clave => $etiqueta)
                            <label class="mperm__op">
                                <input type="checkbox" name="permisos[]" value="{{ $clave }}"
                                       @checked(in_array($clave, $marcados, true))>
                                <span>{{ $etiqueta }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>
    @endforeach
</div>

@push('styles')
<style>
    .mperm{ margin-top:18px; border:1px solid var(--line); border-radius:13px; overflow:hidden; }
    .mperm__cab{ display:flex; align-items:center; justify-content:space-between; gap:14px;
                 padding:13px 16px; background:var(--paper); border-bottom:1px solid var(--line-2); }
    .mperm__cab b{ display:block; font-size:13.4px; color:var(--ink); }
    .mperm__cab span{ display:block; margin-top:2px; font-size:11.8px; color:var(--muted); }
    .mperm__todo{ flex:none; padding:6px 11px; border:1px solid var(--line); border-radius:9px;
                  background:var(--card); color:var(--ink-2); font-size:12px; cursor:pointer;
                  transition:border-color .18s var(--e-soft); }
    .mperm__todo:hover{ border-color:var(--muted-2); }

    .mperm__g{ padding:13px 16px; border-bottom:1px solid var(--line-2); }
    .mperm__g:last-child{ border-bottom:0; }
    .mperm__g h4{ margin:0 0 9px; font-size:10.8px; font-weight:700; letter-spacing:.08em;
                  text-transform:uppercase; color:var(--muted); }

    /* minmax(0,…): sin esto una etiqueta larga ensancha la columna y la
       tabla de permisos empuja la página de lado en el celular. */
    .mperm__m{ display:grid; grid-template-columns:minmax(0,150px) minmax(0,1fr);
               gap:10px; align-items:start; padding:6px 0; }
    .mperm__mn{ font-size:12.6px; font-weight:600; color:var(--ink-2); padding-top:5px; }
    .mperm__ops{ display:flex; flex-wrap:wrap; gap:7px; min-width:0; }

    .mperm__op{ display:inline-flex; align-items:center; gap:6px; padding:5px 10px;
                border:1px solid var(--line); border-radius:99px; background:var(--card);
                font-size:12.2px; color:var(--ink-2); cursor:pointer;
                transition:border-color .16s, background .16s; }
    .mperm__op:hover{ border-color:var(--muted-2); }
    .mperm__op input{ width:14px; height:14px; accent-color:var(--a-500); cursor:pointer; }
    .mperm__op:has(input:checked){ border-color:var(--a-500); background:rgba(46,110,168,.07);
                                   color:var(--ink); }

    @media (max-width:640px){
        .mperm__m{ grid-template-columns:minmax(0,1fr); gap:5px; }
        .mperm__mn{ padding-top:0; }
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var boton = document.querySelector('[data-perm-todo]');
        var caja  = document.querySelector('.mperm');
        if (!boton || !caja) return;

        boton.addEventListener('click', function () {
            var casillas = caja.querySelectorAll('input[type=checkbox]');
            /* Si falta alguna por marcar, marca todas; si ya están todas,
               las quita. Un solo botón que hace lo que uno espera. */
            var faltan = Array.prototype.some.call(casillas, function (c) { return !c.checked; });
            Array.prototype.forEach.call(casillas, function (c) { c.checked = faltan; });
            boton.textContent = faltan ? 'Quitar todo' : 'Marcar todo';
        });
    })();
</script>
@endpush
