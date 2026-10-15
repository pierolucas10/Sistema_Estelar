{{-- Estilos de la ficha de proyecto. CSS propio (no depende de recompilar Tailwind). --}}
<style>
    .pf {
        --pf-bg: #020617;
        --pf-card: #0b1222;
        --pf-card-2: #111a2e;
        --pf-line: rgba(148, 163, 184, .12);
        --pf-line-2: rgba(148, 163, 184, .22);
        --pf-text: #e2e8f0;
        --pf-muted: #94a3b8;
        --pf-dim: #64748b;
        --pf-sky: #38bdf8;
        --pf-sky-2: #0ea5e9;
        --pf-green: #34d399;
        --pf-amber: #fbbf24;
        --pf-red: #f87171;
        --pf-violet: #a78bfa;
        --pf-radius: 18px;
        color: var(--pf-text);
        font-size: 14px;
    }
    .pf *, .pf *::before, .pf *::after { box-sizing: border-box; }
    .pf a { color: inherit; text-decoration: none; }
    .pf .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
    .pf .muted { color: var(--pf-muted); }
    .pf .dim { color: var(--pf-dim); }

    /* ── Hero ─────────────────────────────────────────────── */
    .pf-hero {
        position: relative; overflow: hidden;
        border: 1px solid var(--pf-line); border-radius: 22px;
        background:
            radial-gradient(700px 220px at 0% 0%, rgba(14,165,233,.16), transparent 60%),
            radial-gradient(500px 200px at 100% 0%, rgba(167,139,250,.10), transparent 60%),
            linear-gradient(180deg, #0c1528, #0a1120);
        padding: 24px; margin-bottom: 18px;
    }
    .pf-hero__top { display: flex; gap: 18px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; }
    .pf-hero__id { display: flex; gap: 16px; align-items: flex-start; min-width: 0; flex: 1 1 420px; }
    .pf-avatar {
        width: 54px; height: 54px; border-radius: 16px; flex: none;
        display: grid; place-items: center; font-weight: 800; font-size: 18px; color: #fff;
        background: linear-gradient(135deg, #0ea5e9, #6366f1);
        box-shadow: 0 10px 30px -10px rgba(14,165,233,.7);
    }
    .pf-title { font-size: 24px; line-height: 1.2; font-weight: 800; color: #fff; margin: 0; letter-spacing: -.01em; overflow-wrap: anywhere; }
    .pf-sub { margin-top: 4px; color: var(--pf-muted); font-size: 14px; }
    .pf-desc { margin-top: 10px; color: var(--pf-muted); font-size: 13.5px; line-height: 1.6; max-width: 70ch; }
    .pf-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .pf-meta { display: flex; flex-wrap: wrap; gap: 8px 22px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--pf-line); }
    .pf-meta__item { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .pf-meta__label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--pf-dim); font-weight: 700; }
    .pf-meta__value { font-size: 13.5px; color: var(--pf-text); font-weight: 600; }
    .pf-meta__value.is-late { color: var(--pf-red); }

    /* ── Pills / chips ────────────────────────────────────── */
    .pf-pill {
        display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        padding: 4px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700;
        background: rgba(148,163,184,.12); color: #cbd5e1; border: 1px solid rgba(148,163,184,.18);
    }
    .pf-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .9; }
    .pf-pill--plain::before { display: none; }
    .pf-pill--sky    { background: rgba(56,189,248,.10); color: var(--pf-sky);    border-color: rgba(56,189,248,.25); }
    .pf-pill--green  { background: rgba(52,211,153,.10); color: var(--pf-green);  border-color: rgba(52,211,153,.25); }
    .pf-pill--amber  { background: rgba(251,191,36,.10); color: var(--pf-amber);  border-color: rgba(251,191,36,.25); }
    .pf-pill--red    { background: rgba(248,113,113,.10); color: var(--pf-red);   border-color: rgba(248,113,113,.25); }
    .pf-pill--violet { background: rgba(167,139,250,.10); color: var(--pf-violet); border-color: rgba(167,139,250,.25); }

    /* ── Botones ──────────────────────────────────────────── */
    .pf-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 9px 15px; border-radius: 12px; font-size: 13px; font-weight: 700; line-height: 1.2;
        border: 1px solid var(--pf-line-2); background: rgba(15,23,42,.7); color: #cbd5e1;
        cursor: pointer; transition: background .15s, border-color .15s, color .15s, transform .1s;
        white-space: nowrap; font-family: inherit;
    }
    .pf-btn:hover { background: #172036; color: #fff; border-color: rgba(148,163,184,.35); }
    .pf-btn:active { transform: scale(.98); }
    .pf-btn:disabled { opacity: .45; cursor: not-allowed; transform: none; }
    .pf-btn svg { width: 16px; height: 16px; flex: none; }
    .pf-btn--primary { background: linear-gradient(135deg, #0ea5e9, #0284c7); border-color: transparent; color: #fff; box-shadow: 0 8px 24px -10px rgba(14,165,233,.8); }
    .pf-btn--primary:hover { background: linear-gradient(135deg, #38bdf8, #0ea5e9); border-color: transparent; }
    .pf-btn--success { background: linear-gradient(135deg, #10b981, #059669); border-color: transparent; color: #fff; }
    .pf-btn--success:hover { background: linear-gradient(135deg, #34d399, #10b981); border-color: transparent; }
    .pf-btn--danger { color: var(--pf-red); border-color: rgba(248,113,113,.25); background: transparent; }
    .pf-btn--danger:hover { background: rgba(248,113,113,.10); color: #fecaca; border-color: rgba(248,113,113,.4); }
    .pf-btn--ghost { background: transparent; border-color: transparent; color: var(--pf-muted); }
    .pf-btn--ghost:hover { background: rgba(148,163,184,.08); border-color: transparent; }
    .pf-btn--sm { padding: 6px 10px; font-size: 12px; border-radius: 10px; }
    .pf-btn--sm svg { width: 14px; height: 14px; }
    .pf-btn--icon { padding: 7px; }
    .pf-btn--block { width: 100%; }

    /* ── Pestañas ─────────────────────────────────────────── */
    .pf-tabs { display: inline-flex; gap: 4px; padding: 4px; border-radius: 14px; background: #0b1222; border: 1px solid var(--pf-line); margin-bottom: 18px; flex-wrap: wrap; }
    .pf-tab { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 10px; font-weight: 700; font-size: 13px; color: var(--pf-muted); transition: background .15s, color .15s; }
    .pf-tab svg { width: 16px; height: 16px; }
    .pf-tab:hover { color: #fff; background: rgba(148,163,184,.08); }
    .pf-tab.is-active { color: #fff; background: linear-gradient(135deg, rgba(14,165,233,.25), rgba(99,102,241,.2)); box-shadow: inset 0 0 0 1px rgba(56,189,248,.3); }

    /* ── Layout ───────────────────────────────────────────── */
    .pf-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 18px; align-items: start; }
    .pf-col { display: flex; flex-direction: column; gap: 18px; min-width: 0; }
    @media (max-width: 1180px) { .pf-grid { grid-template-columns: minmax(0, 1fr); } }

    /* ── Tarjetas ─────────────────────────────────────────── */
    .pf-card { background: var(--pf-card); border: 1px solid var(--pf-line); border-radius: var(--pf-radius); scroll-margin-top: 90px; }
    .pf-card__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--pf-line); flex-wrap: wrap; }
    .pf-card__title { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 800; color: #fff; margin: 0; }
    .pf-card__title svg { width: 18px; height: 18px; color: var(--pf-sky); flex: none; }
    .pf-card__sub { font-size: 12.5px; color: var(--pf-dim); margin-top: 2px; font-weight: 500; }
    .pf-card__body { padding: 18px 20px; }
    .pf-card__body + .pf-card__body { border-top: 1px solid var(--pf-line); }

    /* ── KPIs y progreso ──────────────────────────────────── */
    .pf-kpis { display: grid; grid-template-columns: auto repeat(3, minmax(0, 1fr)); gap: 14px; align-items: center; }
    @media (max-width: 760px) { .pf-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .pf-kpis .pf-ring { grid-column: span 2; justify-self: center; } }
    .pf-kpi { padding: 14px 16px; border-radius: 14px; background: var(--pf-card-2); border: 1px solid var(--pf-line); min-width: 0; }
    .pf-kpi__label { font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: var(--pf-dim); font-weight: 700; }
    .pf-kpi__value { margin-top: 6px; font-size: 20px; font-weight: 800; color: #fff; font-family: 'JetBrains Mono', monospace; overflow-wrap: anywhere; }
    .pf-kpi__value.is-green { color: var(--pf-green); }
    .pf-kpi__value.is-amber { color: var(--pf-amber); }
    .pf-ring {
        --p: 0; --c: var(--pf-sky-2);
        width: 112px; height: 112px; border-radius: 50%; flex: none;
        background: conic-gradient(var(--c) calc(var(--p) * 1%), rgba(148,163,184,.12) 0);
        display: grid; place-items: center; position: relative;
    }
    .pf-ring::before { content: ''; position: absolute; inset: 10px; border-radius: 50%; background: var(--pf-card); }
    .pf-ring__inner { position: relative; text-align: center; line-height: 1.1; }
    .pf-ring__value { font-size: 24px; font-weight: 800; color: #fff; font-family: 'JetBrains Mono', monospace; }
    .pf-ring__label { font-size: 10px; text-transform: uppercase; letter-spacing: .1em; color: var(--pf-dim); font-weight: 700; margin-top: 3px; }
    .pf-ring--sm { width: 64px; height: 64px; }
    .pf-ring--sm::before { inset: 7px; }
    .pf-ring--sm .pf-ring__value { font-size: 14px; }

    .pf-bar { height: 8px; border-radius: 999px; background: rgba(148,163,184,.12); overflow: hidden; }
    .pf-bar > span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #0ea5e9, #22d3ee); transition: width .6s ease; }
    .pf-bar.is-green > span { background: linear-gradient(90deg, #10b981, #34d399); }
    .pf-bar.is-amber > span { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .pf-bar--thin { height: 5px; }

    /* ── Alertas ──────────────────────────────────────────── */
    .pf-alerts { display: flex; flex-direction: column; gap: 8px; }
    .pf-alert { display: flex; gap: 10px; align-items: flex-start; padding: 11px 14px; border-radius: 12px; font-size: 13px; line-height: 1.45; border: 1px solid; }
    .pf-alert svg { width: 17px; height: 17px; flex: none; margin-top: 1px; }
    .pf-alert--danger  { background: rgba(248,113,113,.08); border-color: rgba(248,113,113,.25); color: #fecaca; }
    .pf-alert--danger svg { color: var(--pf-red); }
    .pf-alert--warning { background: rgba(251,191,36,.07); border-color: rgba(251,191,36,.22); color: #fde68a; }
    .pf-alert--warning svg { color: var(--pf-amber); }
    .pf-alert--info    { background: rgba(56,189,248,.07); border-color: rgba(56,189,248,.2); color: #bae6fd; }
    .pf-alert--info svg { color: var(--pf-sky); }
    .pf-alert--ok      { background: rgba(52,211,153,.07); border-color: rgba(52,211,153,.2); color: #a7f3d0; }
    .pf-alert--ok svg { color: var(--pf-green); }

    /* ── Cuotas ───────────────────────────────────────────── */
    .pf-cuotas { display: flex; flex-direction: column; }
    .pf-cuota { border-top: 1px solid var(--pf-line); }
    .pf-cuota:first-child { border-top: 0; }
    .pf-cuota__row { display: grid; grid-template-columns: 34px minmax(0, 1.6fr) minmax(0, 1fr) auto auto; gap: 14px; align-items: center; padding: 14px 20px; }
    @media (max-width: 760px) {
        .pf-cuota__row { grid-template-columns: 30px minmax(0, 1fr) auto; }
        .pf-cuota__row .pf-cuota__estado { grid-column: 2; }
        .pf-cuota__row .pf-cuota__acciones { grid-column: 1 / -1; justify-content: flex-start; }
    }
    .pf-cuota__n { width: 30px; height: 30px; border-radius: 10px; display: grid; place-items: center; font-weight: 800; font-size: 12px; background: var(--pf-card-2); color: var(--pf-muted); border: 1px solid var(--pf-line); }
    .pf-cuota.is-pagada .pf-cuota__n { background: rgba(52,211,153,.12); color: var(--pf-green); border-color: rgba(52,211,153,.25); }
    .pf-cuota.is-vencida .pf-cuota__n { background: rgba(248,113,113,.12); color: var(--pf-red); border-color: rgba(248,113,113,.25); }
    .pf-cuota__nombre { font-weight: 700; color: #fff; overflow-wrap: anywhere; }
    .pf-cuota__det { font-size: 12px; color: var(--pf-dim); margin-top: 2px; }
    .pf-cuota__monto { font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 15px; color: #fff; text-align: right; }
    .pf-cuota__acciones { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
    .pf-cuota__panel { padding: 4px 20px 18px 68px; }
    @media (max-width: 760px) { .pf-cuota__panel { padding: 4px 16px 16px; } }

    /* ── Formularios ──────────────────────────────────────── */
    .pf-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 14px; }
    .pf-form--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .pf-form .full { grid-column: 1 / -1; }
    @media (max-width: 640px) { .pf-form, .pf-form--3 { grid-template-columns: minmax(0, 1fr); } }
    .pf-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .pf-label { font-size: 11.5px; font-weight: 700; color: var(--pf-muted); letter-spacing: .02em; }
    .pf-hint { font-size: 11.5px; color: var(--pf-dim); }
    .pf-input {
        width: 100%; background: #0f172a; border: 1px solid #22304a; color: #f1f5f9;
        border-radius: 11px; padding: 10px 12px; font-size: 13.5px; font-family: inherit;
        transition: border-color .15s, box-shadow .15s;
    }
    .pf-input::placeholder { color: #55657f; }
    .pf-input:focus { outline: none; border-color: var(--pf-sky-2); box-shadow: 0 0 0 3px rgba(14,165,233,.18); }
    textarea.pf-input { resize: vertical; min-height: 90px; line-height: 1.55; }
    select.pf-input { cursor: pointer; }
    input[type=file].pf-input { padding: 7px; color: var(--pf-muted); cursor: pointer; }
    input[type=file].pf-input::file-selector-button {
        margin-right: 12px; padding: 7px 12px; border-radius: 8px; border: 0; cursor: pointer;
        background: rgba(56,189,248,.14); color: var(--pf-sky); font-weight: 700; font-family: inherit; font-size: 12.5px;
    }
    .pf-form__actions { display: flex; gap: 8px; justify-content: flex-end; grid-column: 1 / -1; flex-wrap: wrap; }
    .pf-error { color: var(--pf-red); font-size: 12px; }
    .pf-box { padding: 16px; border-radius: 14px; background: var(--pf-card-2); border: 1px solid var(--pf-line); }
    .pf-box--dashed { border-style: dashed; border-color: var(--pf-line-2); background: rgba(15,23,42,.4); }

    /* ── Archivos ─────────────────────────────────────────── */
    .pf-files { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
    .pf-file { display: flex; gap: 12px; align-items: center; padding: 11px 12px; border-radius: 13px; background: var(--pf-card-2); border: 1px solid var(--pf-line); min-width: 0; transition: border-color .15s; }
    .pf-file:hover { border-color: rgba(56,189,248,.35); }
    .pf-file__ext { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; flex: none; font-size: 10px; font-weight: 800; letter-spacing: .03em; background: rgba(56,189,248,.12); color: var(--pf-sky); font-family: 'JetBrains Mono', monospace; }
    .pf-file__ext.is-pdf { background: rgba(248,113,113,.12); color: var(--pf-red); }
    .pf-file__ext.is-img { background: rgba(167,139,250,.12); color: var(--pf-violet); }
    .pf-file__ext.is-doc { background: rgba(96,165,250,.12); color: #93c5fd; }
    .pf-file__ext.is-xls { background: rgba(52,211,153,.12); color: var(--pf-green); }
    .pf-file__info { min-width: 0; flex: 1; }
    .pf-file__name { font-weight: 700; font-size: 13px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
    .pf-file__meta { font-size: 11.5px; color: var(--pf-dim); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pf-file__acts { display: flex; gap: 2px; flex: none; }

    .pf-empty { text-align: center; padding: 26px 16px; color: var(--pf-dim); font-size: 13px; }
    .pf-empty svg { width: 34px; height: 34px; margin: 0 auto 8px; color: #334155; display: block; }

    /* ── Lista clave/valor ────────────────────────────────── */
    .pf-kv { display: flex; flex-direction: column; }
    .pf-kv__row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-top: 1px solid var(--pf-line); font-size: 13px; }
    .pf-kv__row:first-child { border-top: 0; padding-top: 0; }
    .pf-kv__k { color: var(--pf-dim); flex: none; }
    .pf-kv__v { color: var(--pf-text); font-weight: 600; text-align: right; min-width: 0; overflow-wrap: anywhere; }

    /* ── Stepper ──────────────────────────────────────────── */
    .pf-stepper { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 18px; }
    @media (max-width: 900px) { .pf-stepper { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .pf-step { position: relative; display: flex; gap: 12px; align-items: flex-start; padding: 14px; border-radius: 16px; background: var(--pf-card); border: 1px solid var(--pf-line); text-align: left; cursor: pointer; color: inherit; font-family: inherit; transition: border-color .15s, background .15s; }
    .pf-step:hover { border-color: var(--pf-line-2); }
    .pf-step__n { width: 34px; height: 34px; border-radius: 11px; flex: none; display: grid; place-items: center; font-weight: 800; font-size: 14px; background: var(--pf-card-2); border: 1px solid var(--pf-line-2); color: var(--pf-muted); }
    .pf-step__n svg { width: 17px; height: 17px; }
    .pf-step__t { font-weight: 800; color: #fff; font-size: 13.5px; }
    .pf-step__d { font-size: 11.5px; color: var(--pf-dim); margin-top: 3px; line-height: 1.35; }
    .pf-step.is-done .pf-step__n { background: rgba(52,211,153,.14); color: var(--pf-green); border-color: rgba(52,211,153,.3); }
    .pf-step.is-current { border-color: rgba(56,189,248,.45); background: linear-gradient(180deg, rgba(14,165,233,.10), var(--pf-card)); box-shadow: 0 10px 30px -18px rgba(14,165,233,.8); }
    .pf-step.is-current .pf-step__n { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: #fff; border-color: transparent; }
    .pf-step.is-locked { opacity: .55; }
    .pf-step.is-open { outline: 2px solid rgba(56,189,248,.35); outline-offset: 2px; }

    .pf-locked { display: flex; gap: 12px; align-items: center; padding: 14px 16px; border-radius: 14px; background: rgba(148,163,184,.06); border: 1px dashed var(--pf-line-2); color: var(--pf-muted); font-size: 13px; margin-bottom: 14px; }
    .pf-locked svg { width: 20px; height: 20px; flex: none; color: var(--pf-dim); }

    /* ── Reuniones / items de lista ───────────────────────── */
    .pf-list { display: flex; flex-direction: column; gap: 10px; }
    .pf-item { padding: 14px 16px; border-radius: 14px; background: var(--pf-card-2); border: 1px solid var(--pf-line); }
    .pf-item__head { display: flex; gap: 10px; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; }
    .pf-item__title { font-weight: 800; color: #fff; overflow-wrap: anywhere; }
    .pf-item__meta { font-size: 12px; color: var(--pf-dim); margin-top: 3px; }
    .pf-item__text { margin-top: 10px; font-size: 13px; line-height: 1.6; color: #cbd5e1; white-space: pre-wrap; overflow-wrap: anywhere; }
    .pf-item__label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; font-weight: 800; color: var(--pf-dim); margin-top: 12px; }

    .pf-req { display: flex; gap: 10px; align-items: center; padding: 10px 0; border-top: 1px solid var(--pf-line); font-size: 13px; }
    .pf-req:first-child { border-top: 0; }
    .pf-req__t { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pf-req.is-done .pf-req__t { color: var(--pf-dim); text-decoration: line-through; }

    .pf-sprint { padding: 16px; border-radius: 14px; background: var(--pf-card-2); border: 1px solid var(--pf-line); }
    .pf-sprint.is-activo { border-color: rgba(56,189,248,.35); background: linear-gradient(180deg, rgba(14,165,233,.08), var(--pf-card-2)); }

    .pf-check { display: flex; gap: 10px; align-items: center; padding: 10px 12px; border-radius: 12px; background: var(--pf-card-2); border: 1px solid var(--pf-line); font-size: 13px; }
    .pf-check__box { width: 20px; height: 20px; border-radius: 6px; border: 2px solid #334155; display: grid; place-items: center; flex: none; background: transparent; cursor: pointer; padding: 0; color: #fff; }
    .pf-check__box svg { width: 12px; height: 12px; }
    .pf-check.is-done .pf-check__box { background: #10b981; border-color: #10b981; }
    .pf-check.is-done span { color: var(--pf-dim); text-decoration: line-through; }

    .pf-split { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    @media (max-width: 760px) { .pf-split { grid-template-columns: minmax(0, 1fr); } }
    .pf-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .pf-between { display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    .pf-gap-sm > * + * { margin-top: 8px; }
    .pf-gap > * + * { margin-top: 14px; }
    .pf-section-title { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; font-weight: 800; color: var(--pf-dim); margin: 0 0 10px; }
    .pf-big-number { font-size: 34px; font-weight: 800; color: #fff; font-family: 'JetBrains Mono', monospace; line-height: 1; }

    /* ── Indicadores en fila ──────────────────────────────── */
    .pf-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 18px; }
    .pf-tile { padding: 16px; border-radius: 16px; background: var(--pf-card); border: 1px solid var(--pf-line); display: flex; gap: 12px; align-items: center; min-width: 0; }
    .pf-tile__icon { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; flex: none; background: rgba(56,189,248,.12); color: var(--pf-sky); }
    .pf-tile__icon svg { width: 20px; height: 20px; }
    .pf-tile__icon.is-green { background: rgba(52,211,153,.12); color: var(--pf-green); }
    .pf-tile__icon.is-amber { background: rgba(251,191,36,.12); color: var(--pf-amber); }
    .pf-tile__icon.is-red   { background: rgba(248,113,113,.12); color: var(--pf-red); }
    .pf-tile__icon.is-violet { background: rgba(167,139,250,.12); color: var(--pf-violet); }
    .pf-tile__v { font-size: 20px; font-weight: 800; color: #fff; font-family: 'JetBrains Mono', monospace; line-height: 1.1; overflow-wrap: anywhere; }
    .pf-tile__l { font-size: 11.5px; color: var(--pf-dim); margin-top: 3px; }

    /* ── Tabla ────────────────────────────────────────────── */
    .pf-table-wrap { overflow-x: auto; }
    .pf-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pf-table th { text-align: left; padding: 12px 16px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--pf-dim); font-weight: 800; border-bottom: 1px solid var(--pf-line); white-space: nowrap; }
    .pf-table td { padding: 14px 16px; border-bottom: 1px solid var(--pf-line); vertical-align: middle; }
    .pf-table tr:last-child td { border-bottom: 0; }
    .pf-table tbody tr { transition: background .12s; }
    .pf-table tbody tr:hover { background: rgba(148,163,184,.04); }
    .pf-table .num { text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; white-space: nowrap; }
    .pf-table .is-link { cursor: pointer; }
    @media (max-width: 760px) { .pf-table .hide-sm { display: none; } }

    .pf-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--pf-line); }
    .pf-toolbar .pf-input { width: auto; }
    .pf-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .pf-chip-btn { padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; border: 1px solid var(--pf-line-2); color: var(--pf-muted); background: transparent; cursor: pointer; font-family: inherit; transition: all .15s; }
    .pf-chip-btn:hover { color: #fff; border-color: rgba(148,163,184,.4); }
    .pf-chip-btn.is-active { background: rgba(56,189,248,.14); color: var(--pf-sky); border-color: rgba(56,189,248,.35); }
    .pf-chip-btn .n { margin-left: 4px; opacity: .7; }

    .pf-timeline { display: flex; flex-direction: column; }
    .pf-tl { position: relative; padding: 0 0 16px 22px; }
    .pf-tl::before { content: ''; position: absolute; left: 5px; top: 6px; bottom: -2px; width: 2px; background: var(--pf-line); }
    .pf-tl:last-child::before { display: none; }
    .pf-tl::after { content: ''; position: absolute; left: 0; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: var(--pf-card); border: 2px solid var(--pf-sky); }
    .pf-tl__h { font-size: 12px; color: var(--pf-dim); }
    .pf-tl__t { font-size: 13px; color: #cbd5e1; margin-top: 4px; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
</style>
