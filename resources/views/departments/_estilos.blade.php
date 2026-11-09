@once
@push('styles')
<style>
    .rh-err{ display:flex; gap:8px; align-items:center; margin-bottom:14px; padding:10px 14px; border-radius:10px; background:rgba(220,38,38,.08); color:#b91c1c; font-size:13px; font-weight:600; }
    .rh-cards{ display:grid; gap:14px; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); }
    .rh-card{ display:flex; flex-direction:column; gap:6px; padding:16px; border-radius:15px; background:var(--card); border:1px solid var(--line);
              color:inherit; text-decoration:none; transition:border-color .18s, transform .18s, box-shadow .18s; }
    .rh-card:hover{ border-color:var(--a-400, #5b8fc2); transform:translateY(-1px); box-shadow:0 6px 16px rgba(16,24,40,.06); }
    .rh-card.is-off{ opacity:.6; }
    .rh-card__ico{ width:36px; height:36px; border-radius:10px; display:grid; place-items:center; background:rgba(46,110,168,.1); color:var(--a-600); margin-bottom:4px; }
    .rh-card b{ font-size:15px; color:var(--ink); }
    .rh-card__sub{ font-size:12.5px; color:var(--muted); line-height:1.45; }
    .rh-card__foot{ display:flex; flex-direction:column; gap:4px; margin-top:auto; padding-top:10px; border-top:1px solid var(--line-2); font-size:12px; color:var(--ink-2); }
    .rh-card__foot span{ display:inline-flex; gap:5px; align-items:center; }
</style>
@endpush
@endonce
