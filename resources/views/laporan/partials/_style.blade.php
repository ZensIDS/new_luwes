<style>
    .rpt-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:22px; }
    .rpt-toolbar .rpt-hint { color:#6b7280; margin:0; font-size:14px; }
    .rpt-search { position:relative; width:280px; max-width:100%; }
    .rpt-search .fa { position:absolute; left:12px; top:11px; color:#9ca3af; }
    .rpt-search input { padding-left:34px; border-radius:20px; height:36px; }

    .rpt-group { margin-bottom:26px; }
    .rpt-group-title { font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6b7280; margin:0 0 12px; }

    .rpt-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:14px; }
    .rpt-card { display:flex; flex-direction:column; text-align:left; width:100%; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; cursor:pointer; transition:border-color .15s, box-shadow .15s, transform .15s; }
    .rpt-card:hover, .rpt-card:focus { border-color:var(--c); box-shadow:0 4px 14px rgba(17,24,39,.08); transform:translateY(-1px); outline:none; }
    .rpt-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; color:var(--c); background:var(--bg); margin-bottom:12px; }
    .rpt-card h4 { margin:0 0 4px; font-size:15px; font-weight:600; color:#111827; }
    .rpt-card p { margin:0; font-size:12.5px; line-height:1.5; color:#6b7280; flex:1; }
    .rpt-card .rpt-open { margin-top:12px; font-size:12.5px; font-weight:600; color:var(--c); }
    .rpt-card .rpt-open .fa { margin-left:4px; transition:margin .15s; }
    .rpt-card:hover .rpt-open .fa { margin-left:8px; }

    .rpt-c-blue   { --c:#2563eb; --bg:#eff6ff; }
    .rpt-c-green  { --c:#059669; --bg:#ecfdf5; }
    .rpt-c-amber  { --c:#d97706; --bg:#fffbeb; }
    .rpt-c-red    { --c:#dc2626; --bg:#fef2f2; }
    .rpt-c-purple { --c:#7c3aed; --bg:#f5f3ff; }
    .rpt-c-teal   { --c:#0d9488; --bg:#f0fdfa; }

    .rpt-empty { display:none; text-align:center; color:#9ca3af; padding:40px 0; }

    .rpt-modal .modal-content { border-radius:10px; border:0; }
    .rpt-modal .modal-header { border-bottom:1px solid #f0f0f0; padding:16px 20px; }
    .rpt-modal .modal-title { font-weight:600; display:flex; align-items:center; gap:10px; }
    .rpt-modal .modal-title .rpt-icon { width:32px; height:32px; font-size:15px; margin:0; }
    .rpt-modal .modal-body { padding:20px; }
    .rpt-modal .modal-footer { border-top:1px solid #f0f0f0; padding:14px 20px; }
    .rpt-modal label { font-size:12.5px; color:#374151; font-weight:600; }
    .rpt-desc { color:#6b7280; font-size:13px; margin:0 0 16px; }
    .rpt-presets { margin:-4px 0 14px; }
    .rpt-presets .btn { border-radius:14px; padding:2px 10px; font-size:12px; margin:0 4px 4px 0; }
    .rpt-error { display:none; margin:0 0 12px; font-size:12.5px; color:#dc2626; }
</style>