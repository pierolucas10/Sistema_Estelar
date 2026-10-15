{{-- Vista de solo lectura para roles sin finanzas ni desarrollo (p. ej. ventas) --}}
@php
    $reqs = $proyecto->requirements;
    $reqsOk = $reqs->where('status', 'completado')->count();
@endphp
<div class="pf-grid">
    <div class="pf-col">
        <section class="pf-card">
            <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="chart" /> Avance</h2></header>
            <div class="pf-card__body">
                <div class="pf-row" style="gap:18px">
                    <div class="pf-ring" style="--p: {{ $proyecto->progress }}; --c: {{ $proyecto->progress >= 100 ? '#10b981' : '#0ea5e9' }}">
                        <div class="pf-ring__inner">
                            <div class="pf-ring__value">{{ $proyecto->progress }}%</div>
                            <div class="pf-ring__label">avance</div>
                        </div>
                    </div>
                    <div class="pf-kv" style="flex:1;min-width:220px">
                        <div class="pf-kv__row"><span class="pf-kv__k">Estado</span><span class="pf-kv__v">{{ $proyecto->statusLabel() }}</span></div>
                        <div class="pf-kv__row"><span class="pf-kv__k">Requerimientos</span><span class="pf-kv__v">{{ $reqsOk }} / {{ $reqs->count() }} completados</span></div>
                        <div class="pf-kv__row"><span class="pf-kv__k">Fecha objetivo</span><span class="pf-kv__v mono">{{ $proyecto->fechaObjetivo()?->format('d/m/Y') ?? '—' }}</span></div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <aside class="pf-col">
        <section class="pf-card">
            <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="info" /> Información</h2></header>
            <div class="pf-card__body">
                <div class="pf-kv">
                    <div class="pf-kv__row"><span class="pf-kv__k">Cliente</span><span class="pf-kv__v">{{ $proyecto->client->razon_social ?? '—' }}</span></div>
                    <div class="pf-kv__row"><span class="pf-kv__k">Responsable</span><span class="pf-kv__v">{{ $proyecto->responsible->name ?? 'Sin asignar' }}</span></div>
                    <div class="pf-kv__row"><span class="pf-kv__k">Creado</span><span class="pf-kv__v mono">{{ $proyecto->created_at?->format('d/m/Y') }}</span></div>
                </div>
            </div>
        </section>
    </aside>
</div>
