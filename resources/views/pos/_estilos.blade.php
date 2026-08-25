<style>
    /* ══════════ Aviso de caja sin abrir ══════════ */
    .pos-aviso{ display:flex; gap:12px; align-items:flex-start; margin-bottom:16px;
                padding:14px 16px; border:1px solid rgba(150,112,60,.35); border-radius:12px;
                background:rgba(150,112,60,.06); }
    .pos-aviso > span{ display:grid; place-items:center; width:30px; height:30px; flex:none;
                       border-radius:9px; background:var(--warn); color:#fff; }
    .pos-aviso b{ display:block; font-size:13.4px; color:var(--ink); }
    .pos-aviso p{ margin:3px 0 0; font-size:12.5px; color:var(--muted); line-height:1.5; }

    /* ══════════ Rejilla general ══════════ */
    .pos{ display:grid; gap:16px; grid-template-columns:minmax(0,1fr) 392px; align-items:start; }
    @media (max-width:1100px){ .pos{ grid-template-columns:minmax(0,1fr); } }

    /* ══════════ Catálogo ══════════ */
    .pos-cat{ background:var(--card); border:1px solid var(--line); border-radius:14px;
              padding:14px; min-height:520px; }
    .pos-cat__top{ display:flex; align-items:center; gap:12px; margin-bottom:13px; }
    .pos-cat__n{ font-size:12px; color:var(--muted); white-space:nowrap; }

    .pos-buscar{ display:flex; align-items:center; gap:9px; flex:1; height:42px; padding:0 14px;
                 border:1px solid var(--line); border-radius:11px; background:var(--paper);
                 color:var(--muted); transition:border-color .18s var(--e-soft), box-shadow .18s var(--e-soft); }
    .pos-buscar:focus-within{ border-color:var(--a-400); background:#fff;
                              box-shadow:0 0 0 3px rgba(46,110,168,.12); }
    .pos-buscar input{ flex:1; border:0; outline:0; background:transparent; font:inherit;
                       font-size:14px; color:var(--ink); }
    .pos-buscar kbd{ font:inherit; font-size:10.4px; font-weight:700; letter-spacing:.04em;
                     padding:3px 6px; border-radius:5px; background:var(--line-2); color:var(--muted-2); }

    .pos-grid{ display:grid; gap:10px; grid-template-columns:repeat(auto-fill,minmax(158px,1fr));
               max-height:min(66vh,620px); overflow-y:auto; padding-right:4px; }
    .pos-grid::-webkit-scrollbar{ width:6px; }
    .pos-grid::-webkit-scrollbar-thumb{ background:var(--line); border-radius:3px; }

    .pos-item{ position:relative; display:flex; flex-direction:column; gap:2px; padding:11px 12px 12px;
               text-align:left; border:1px solid var(--line); border-radius:12px; background:#fff;
               cursor:pointer; font:inherit; color:inherit;
               transition:border-color .16s var(--e-soft), transform .16s var(--e-soft),
                          box-shadow .16s var(--e-soft); }
    .pos-item:hover{ border-color:var(--a-400); transform:translateY(-2px);
                     box-shadow:0 10px 24px -16px rgba(16,24,37,.55); }
    .pos-item:active{ transform:translateY(0) scale(.985); }
    .pos-item.is-off{ opacity:.5; cursor:not-allowed; }
    .pos-item.is-off:hover{ transform:none; border-color:var(--line); box-shadow:none; }

    .pos-item__n{ padding-right:46px; font-size:12.8px; font-weight:600; color:var(--ink); line-height:1.3;
                  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .pos-item__s{ font-size:10.8px; color:var(--muted-2); font-family:ui-monospace,Menlo,monospace; }
    .pos-item__p{ margin-top:6px; font-size:15.5px; font-weight:700; color:var(--a-600);
                  letter-spacing:-.02em; font-variant-numeric:tabular-nums; }
    .pos-item__st{ position:absolute; top:9px; right:9px; font-size:10px; font-weight:700;
                   padding:2px 6px; border-radius:5px; background:var(--line-2); color:var(--muted); }
    .pos-item__st.bajo{ background:rgba(150,112,60,.14); color:var(--warn); }
    .pos-item__st.cero{ background:rgba(150,80,79,.14); color:var(--bad); }

    .pos-vacio{ display:grid; place-items:center; gap:10px; padding:60px 0; color:var(--muted-2); }
    .pos-vacio[hidden]{ display:none; }
    .pos-vacio p{ margin:0; font-size:13px; }

    /* ══════════ Tiquete ══════════ */
    .pos-tic{ position:sticky; top:16px; display:flex; flex-direction:column;
              background:var(--card); border:1px solid var(--line); border-radius:14px;
              overflow:hidden; max-height:calc(100vh - 120px); }
    .pos-tic__head{ display:flex; align-items:center; justify-content:space-between; gap:10px;
                    padding:14px 16px; border-bottom:1px solid var(--line-2); }
    .pos-tic__head h3{ margin:0; font-size:14px; font-weight:600; color:var(--ink); }
    .pos-tic__head p{ margin:2px 0 0; font-size:11.8px; color:var(--muted); }

    .pos-tic__body{ flex:1; overflow-y:auto; padding:6px 10px; min-height:0; }
    .pos-tic__body::-webkit-scrollbar{ width:6px; }
    .pos-tic__body::-webkit-scrollbar-thumb{ background:var(--line); border-radius:3px; }

    .pos-tic__empty{ display:grid; place-items:center; gap:9px; padding:44px 0; color:var(--muted-2); }
    .pos-tic__empty[hidden]{ display:none; }
    .pos-tic__empty p{ margin:0; font-size:12.6px; }

    .pos-l{ display:grid; grid-template-columns:1fr auto; gap:4px 10px; padding:11px 6px;
            border-bottom:1px solid var(--line-2); animation:posEntra .28s var(--e-soft) both; }
    .pos-l:last-child{ border-bottom:0; }
    @keyframes posEntra{ from{ opacity:0; transform:translateX(10px); } }

    .pos-l__n{ font-size:12.7px; font-weight:600; color:var(--ink); line-height:1.35; }
    .pos-l__x{ border:0; background:transparent; cursor:pointer; color:var(--muted-2); padding:0 2px;
               font:inherit; font-size:16px; line-height:1; transition:color .15s; }
    .pos-l__x:hover{ color:var(--bad); }
    .pos-l__row{ grid-column:1/-1; display:flex; align-items:center; justify-content:space-between; gap:10px; }

    .pos-cant{ display:flex; align-items:center; gap:0; border:1px solid var(--line);
               border-radius:8px; overflow:hidden; background:#fff; }
    .pos-cant button{ width:26px; height:26px; border:0; background:transparent; cursor:pointer;
                      font:inherit; font-size:14px; color:var(--ink-2); line-height:1;
                      transition:background .15s; }
    .pos-cant button:hover{ background:var(--line-2); }
    .pos-cant input{ width:46px; height:26px; border:0; border-left:1px solid var(--line);
                     border-right:1px solid var(--line); text-align:center; font:inherit;
                     font-size:12.4px; color:var(--ink); outline:0; -moz-appearance:textfield; }
    .pos-cant input::-webkit-outer-spin-button, .pos-cant input::-webkit-inner-spin-button{
        -webkit-appearance:none; margin:0; }

    .pos-l__t{ font-size:13.4px; font-weight:700; color:var(--ink);
               font-variant-numeric:tabular-nums; white-space:nowrap; }
    .pos-l__u{ font-size:11.2px; color:var(--muted); }

    /* ══════════ Totales ══════════ */
    .pos-tot{ padding:13px 16px; border-top:1px solid var(--line-2); background:var(--paper); }
    .pos-tot > div{ display:flex; justify-content:space-between; align-items:baseline;
                    font-size:12.6px; color:var(--muted); padding:3px 0; }
    .pos-tot > div b{ color:var(--ink-2); font-weight:600; font-variant-numeric:tabular-nums; }
    .pos-tot__big{ margin-top:7px; padding-top:9px !important; border-top:1px solid var(--line);
                   font-size:14px !important; color:var(--ink) !important; }
    .pos-tot__big b{ font-size:23px !important; font-weight:700 !important; letter-spacing:-.03em;
                     color:var(--a-600) !important; }

    .pos-tic__foot{ padding:13px 16px 16px; border-top:1px solid var(--line-2); display:grid; gap:10px; }
    .pos-sel{ height:38px; padding:0 11px; border:1px solid var(--line); border-radius:9px;
              background:#fff; font:inherit; font-size:12.8px; color:var(--ink); width:100%; }
    #posCobrar{ height:48px; font-size:14.5px; gap:9px; }
    #posCobrar kbd{ font:inherit; font-size:10px; font-weight:700; padding:2px 5px; border-radius:4px;
                    background:rgba(255,255,255,.2); }
    #posCobrar:disabled{ opacity:.45; cursor:not-allowed; }

    /* ══════════ Modal de cobro ══════════ */
    .pos-modal{ position:fixed; inset:0; z-index:120; display:grid; place-items:center; padding:20px;
                background:rgba(7,13,20,.62); backdrop-filter:blur(4px);
                opacity:0; pointer-events:none; transition:opacity .26s var(--e-soft); }
    .pos-modal.is-on{ opacity:1; pointer-events:auto; }
    .pos-modal__box{ width:min(440px,100%); background:var(--card); border-radius:16px;
                     box-shadow:0 40px 90px -40px rgba(0,0,0,.6); overflow:hidden;
                     transform:translateY(14px) scale(.98); transition:transform .3s var(--e-back); }
    .pos-modal.is-on .pos-modal__box{ transform:none; }

    .pos-modal__box header{ display:flex; align-items:center; justify-content:space-between;
                            padding:15px 18px; border-bottom:1px solid var(--line-2); }
    .pos-modal__box header h3{ margin:0; font-size:15px; font-weight:600; color:var(--ink); }
    .pos-modal__box header button{ border:0; background:transparent; cursor:pointer;
                                   color:var(--muted-2); padding:4px; border-radius:6px; }
    .pos-modal__box header button:hover{ color:var(--bad); background:var(--line-2); }

    .pos-cobro{ padding:16px 18px; display:grid; gap:13px; }
    .pos-cobro__tot{ text-align:center; padding:14px; border-radius:12px;
                     background:linear-gradient(150deg,var(--n-800),var(--n-900)); color:#fff; }
    .pos-cobro__tot span{ display:block; font-size:11px; letter-spacing:.1em; text-transform:uppercase;
                          color:var(--a-300); font-weight:600; }
    .pos-cobro__tot b{ display:block; margin-top:4px; font-size:32px; font-weight:700;
                       letter-spacing:-.035em; font-variant-numeric:tabular-nums; }

    .pos-cobro__medios{ display:grid; gap:8px; max-height:206px; overflow-y:auto;
                        padding-right:5px; scrollbar-width:thin; }
    .pos-cobro__medios::-webkit-scrollbar{ width:6px; }
    .pos-cobro__medios::-webkit-scrollbar-thumb{ background:var(--line); border-radius:3px; }
    .pos-medio{ display:flex; align-items:center; justify-content:space-between; gap:12px;
                padding:9px 12px; border:1px solid var(--line); border-radius:10px; background:var(--paper); }
    .pos-medio__n{ font-size:12.8px; color:var(--ink-2); }
    .pos-medio__in{ display:flex; align-items:center; gap:5px; }
    .pos-medio__in i{ font-style:normal; font-size:12.4px; color:var(--muted-2); }
    .pos-medio__in input{ width:112px; height:34px; padding:0 9px; text-align:right;
                          border:1px solid var(--line); border-radius:8px; background:#fff;
                          font:inherit; font-size:13.4px; color:var(--ink);
                          font-variant-numeric:tabular-nums; outline:0; }
    .pos-medio__in input:focus{ border-color:var(--a-400); box-shadow:0 0 0 3px rgba(46,110,168,.12); }

    .pos-cobro__res{ display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
    .pos-cobro__res > div{ text-align:center; padding:9px 6px; border:1px solid var(--line);
                           border-radius:10px; background:var(--paper); }
    .pos-cobro__res span{ display:block; font-size:10.6px; letter-spacing:.05em;
                          text-transform:uppercase; color:var(--muted-2); font-weight:600; }
    .pos-cobro__res b{ display:block; margin-top:3px; font-size:15px; font-weight:700;
                       color:var(--ink); font-variant-numeric:tabular-nums; }
    .pos-cobro__res b.ok{ color:var(--ok); }
    .pos-cobro__res b.bad{ color:var(--bad); }

    .pos-notas{ width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:9px;
                background:#fff; font:inherit; font-size:12.6px; color:var(--ink); resize:vertical; }

    .pos-modal__box footer{ display:flex; gap:9px; justify-content:flex-end;
                            padding:13px 18px; border-top:1px solid var(--line-2); background:var(--paper); }
    #posConfirmar:disabled{ opacity:.45; cursor:not-allowed; }

    /* ══════════ Barra de cobro para celular ══════════
       Cuando la pantalla es angosta el tiquete queda debajo del catálogo,
       así que el total y el botón de cobrar viajan en una barra fija al
       pie: se vende sin tener que subir y bajar la página. */
    .pos-movil{ display:none; }

    @media (max-width:1100px){
        /* El catálogo y el tiquete ya no compiten por el alto de la
           pantalla: se deja que la página entera haga el scroll. */
        .pos-cat{ min-height:0; }
        .pos-grid{ max-height:none; overflow:visible; padding-right:0; }
        .pos-tic{ position:static; max-height:none; }
        .pos-tic__body{ overflow:visible; }

        .pos-movil{
            display:flex; align-items:center; gap:12px;
            position:fixed; left:0; right:0; bottom:0; z-index:110;
            padding:10px 14px calc(10px + env(safe-area-inset-bottom));
            background:rgba(255,255,255,.94);
            backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
            border-top:1px solid var(--line);
            box-shadow:0 -12px 30px -22px rgba(16,24,37,.6);
            transform:translateY(102%); transition:transform .3s var(--e-soft);
        }
        .pos-movil.is-on{ transform:none; }

        /* Espacio para que la barra no tape el último botón */
        body.pos-con-barra .mus-page{ padding-bottom:96px; }

        .pos-movil__t{ flex:1; min-width:0; text-align:left; border:0; background:transparent;
                       font:inherit; padding:0; cursor:pointer; }
        .pos-movil__t span{ display:block; font-size:11px; color:var(--muted); }
        .pos-movil__t b{ display:block; font-size:20px; font-weight:700; letter-spacing:-.03em;
                         color:var(--a-600); font-variant-numeric:tabular-nums; }
        .pos-movil .mb{ flex:none; height:46px; padding:0 18px; font-size:14px; }

        /* En celular el tiquete no necesita repetir el botón de cobrar */
        .pos-tic__foot #posCobrar{ display:none; }
    }

    @media (max-width:760px){
        .pos-grid{ grid-template-columns:repeat(auto-fill,minmax(136px,1fr)); gap:8px; }
        .pos-cat__top{ flex-wrap:wrap; }
        .pos-cat__n{ order:3; }
        .pos-buscar{ flex:1 0 100%; height:46px; }
        .pos-buscar input{ font-size:16px; }   /* si no, el navegador hace zoom */
        .pos-buscar kbd{ display:none; }

        /* Los botones de cantidad, del tamaño de un dedo */
        .pos-cant button{ width:34px; height:34px; font-size:16px; }
        .pos-cant input{ width:52px; height:34px; font-size:14px; }
        .pos-l__x{ font-size:22px; padding:0 6px; }

        /* El modal de cobro se vuelve una hoja de abajo hacia arriba */
        .pos-modal{ padding:0; place-items:end stretch; }
        .pos-modal__box{ width:100%; max-height:96vh; overflow-y:auto;
                         border-radius:18px 18px 0 0; }
        .pos-cobro__tot b{ font-size:28px; }
        .pos-medio__in input{ width:104px; height:40px; font-size:16px; }
        .pos-cobro__res{ grid-template-columns:minmax(0,1fr); }
        .pos-cobro__res > div{ display:flex; align-items:center; justify-content:space-between;
                               text-align:left; padding:9px 12px; }
        .pos-cobro__res b{ margin-top:0; }
        .pos-modal__box footer{ position:sticky; bottom:0; }
        .pos-modal__box footer .mb{ flex:1; }
    }
</style>
