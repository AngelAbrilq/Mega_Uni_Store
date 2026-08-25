<x-mus.page :title="__('mus.caja.abrir_titulo')" :subtitle="__('mus.caja.abrir_subtitulo')" icon="money"
            :crumbs="[__('mus.caja.titulo') => route('cash.index'), __('mus.caja.abrir') => null]">

    <form method="POST" action="{{ route('cash.store') }}" novalidate>
        @csrf

        <x-mus.panel :title="__('mus.caja.base_inicial')" :sub="__('mus.caja.base_sub')">
            <div class="mf">
                <div class="mf__grid">
                    <x-mus.field name="opening_amount" :label="__('mus.caja.base_monto')" type="number"
                                 step="1" min="0" prefix="$" :required="true" :value="0"
                                 :hint="__('mus.caja.base_hint')" />

                    <x-mus.textarea name="notes" :label="__('mus.campos.observaciones')" :rows="2"
                                    :hint="__('mus.caja.obs_hint')" />
                </div>
            </div>

            <div class="cnota">
                <x-mus.icon name="info" :w="15" />
                <p>{{ __('mus.caja.abrir_aviso') }}</p>
            </div>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('cash.index') }}" icon="back">{{ __('mus.acciones.cancelar') }}</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">{{ __('mus.caja.abrir') }}</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>

    @push('styles')
    <style>
        .cnota{ display:flex; gap:10px; align-items:flex-start; margin-top:14px; padding:12px 14px;
                border:1px solid var(--line); border-radius:11px; background:var(--paper); }
        .cnota > svg{ flex:none; margin-top:1px; color:var(--a-500); }
        .cnota p{ margin:0; font-size:12.5px; color:var(--muted); line-height:1.6; }
    </style>
    @endpush
</x-mus.page>
