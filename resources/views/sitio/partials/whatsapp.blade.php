{{-- Botón flotante de WhatsApp. Sin número en .env (SITIO_WHATSAPP) no se pinta. --}}
@php($wa = config('sitio.whatsapp'))
@if ($wa)
    <a class="wa" href="https://wa.me/{{ $wa }}?text={{ rawurlencode(config('sitio.whatsapp_mensaje')) }}"
       target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">
        <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M16 3C9 3 3.3 8.6 3.3 15.6c0 2.4.7 4.7 1.9 6.7L3 29l6.9-2.2c1.9 1 4 1.6 6.1 1.6 7 0 12.7-5.7 12.7-12.7S23 3 16 3Zm0 23.2c-2 0-3.9-.6-5.5-1.6l-.4-.2-4.1 1.3 1.3-4-.3-.4a10.4 10.4 0 0 1-1.7-5.7C5.3 9.9 10.1 5.2 16 5.2s10.6 4.7 10.6 10.5S21.9 26.2 16 26.2Zm5.8-7.8c-.3-.2-1.9-1-2.2-1-.3-.1-.5-.2-.7.2l-1 1.2c-.2.2-.4.2-.7.1a8.6 8.6 0 0 1-4.3-3.7c-.3-.6.3-.5.9-1.7.1-.2 0-.4 0-.5l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.2 1.1-1.2 2.8s1.2 3.2 1.4 3.5c.2.2 2.4 3.6 5.8 5 2.1.9 3 1 4 .8.7-.1 1.9-.8 2.2-1.5.3-.8.3-1.4.2-1.5-.1-.2-.3-.3-.6-.4Z"/>
        </svg>
        <span class="wa__txt">¿Dudas? Escríbenos</span>
    </a>
    <style>
        /* Verde WhatsApp oscurecido (#128C4A): texto blanco 4,6:1, AA. */
        .wa{position:fixed;right:18px;bottom:18px;z-index:60;display:inline-flex;align-items:center;gap:8px;
            min-height:56px;padding:0 18px 0 14px;border-radius:999px;background:#128C4A;color:#fff;
            text-decoration:none;font-weight:700;box-shadow:0 10px 28px rgba(0,0,0,.28)}
        .wa:hover{background:#0E7A40;color:#fff}
        @media (max-width:520px){ .wa__txt{display:none} .wa{width:56px;padding:0;justify-content:center} }
        body.cookies-visibles .wa{bottom:calc(18px + var(--alto-cookies, 0px))}
    </style>
@endif
