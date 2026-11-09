{{--
    Trampas anti-spam para formularios públicos (ver ProtegeFormulario).
    Uso: dentro del <form>, junto a @csrf →  <x-mus.antispam />
--}}
@php($cfg = config('sitio.antispam'))
<div aria-hidden="true" style="position:absolute!important;left:-10000px!important;width:1px;height:1px;overflow:hidden">
    <label for="{{ $cfg['campo_trampa'] }}">No llenes este campo</label>
    <input type="text" id="{{ $cfg['campo_trampa'] }}" name="{{ $cfg['campo_trampa'] }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ $cfg['campo_tiempo'] }}" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp) }}">
@error('antispam')
    <p role="alert" style="color:#FCA5A5;font-size:.875rem;margin:.5rem 0 0">{{ $message }}</p>
@enderror
