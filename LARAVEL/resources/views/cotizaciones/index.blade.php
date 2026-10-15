<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-600 font-mono">Comercial</span>
            <span class="text-slate-700">/</span>
            <h1 class="text-sm font-semibold text-white">Cotizaciones</h1>
        </div>
    </x-slot>

    @include('proyectos.partials.estilos')

    <div class="pf">
        <div class="pf-between" style="margin-bottom:18px">
            <div>
                <h2 class="pf-title" style="font-size:22px">Cotizaciones</h2>
                <p class="pf-sub">Crea la propuesta, haz seguimiento antes de que venza y conviértela en contrato.</p>
            </div>
            @can('cotizaciones.crear')
            <a href="{{ route('cotizaciones.create') }}" class="pf-btn pf-btn--primary"><x-pf-icon name="plus" /> Nueva cotización</a>
            @endcan
        </div>

        <div class="pf-tiles">
            <div class="pf-tile">
                <span class="pf-tile__icon"><x-pf-icon name="send" /></span>
                <div><div class="pf-tile__v">{{ $kpis['abiertas'] }}</div><div class="pf-tile__l">Abiertas · S/ {{ number_format($kpis['monto_abierto'], 0) }} en juego</div></div>
            </div>
            <div class="pf-tile">
                <span class="pf-tile__icon is-amber"><x-pf-icon name="clock" /></span>
                <div><div class="pf-tile__v">{{ $kpis['atencion'] }}</div><div class="pf-tile__l">Requieren seguimiento</div></div>
            </div>
            <div class="pf-tile">
                <span class="pf-tile__icon is-red"><x-pf-icon name="alert" /></span>
                <div><div class="pf-tile__v">{{ $kpis['vencidas'] }}</div><div class="pf-tile__l">Vencidas sin respuesta</div></div>
            </div>
            <div class="pf-tile">
                <span class="pf-tile__icon is-green"><x-pf-icon name="briefcase" /></span>
                <div><div class="pf-tile__v">{{ $kpis['contratos_mes'] }}</div><div class="pf-tile__l">Contratos este mes · S/ {{ number_format($kpis['monto_mes'], 0) }}</div></div>
            </div>
            <div class="pf-tile">
                <span class="pf-tile__icon is-violet"><x-pf-icon name="chart" /></span>
                <div><div class="pf-tile__v">{{ $kpis['tasa_cierre'] !== null ? $kpis['tasa_cierre'] . '%' : '—' }}</div><div class="pf-tile__l">Tasa de cierre (90 días)</div></div>
            </div>
        </div>

        @if($atencion->isNotEmpty())
        <section class="pf-card" style="margin-bottom:18px">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="alert" style="color:#fbbf24" /> Para atender hoy</h2>
                    <div class="pf-card__sub">Seguimientos que tocan hoy o están atrasados, y cotizaciones a punto de vencer.</div>
                </div>
            </header>
            <div class="pf-card__body pf-list">
                @foreach($atencion as $q)
                @php $a = collect($q->alertas())->sortBy(fn ($x) => ['danger' => 0, 'warning' => 1, 'info' => 2][$x['nivel']] ?? 3)->first(); @endphp
                <a href="{{ route('cotizaciones.show', $q) }}" class="pf-item pf-between" style="padding:12px 14px">
                    <div style="min-width:0">
                        <div class="pf-row">
                            <span class="mono" style="color:var(--pf-sky);font-weight:800">N° {{ $q->numero }}</span>
                            <span class="pf-item__title">{{ $q->client->nombre_comercial ?: $q->client->razon_social }}</span>
                        </div>
                        @if($a)<div class="pf-item__meta" style="color:{{ $a['nivel'] === 'danger' ? '#fca5a5' : ($a['nivel'] === 'warning' ? '#fde68a' : '#bae6fd') }}">{{ $a['texto'] }}</div>@endif
                    </div>
                    <div class="pf-row">
                        <span class="mono" style="font-weight:800">{{ $q->montoFormateado() }}</span>
                        <span class="pf-pill {{ $q->estadoColor() ? 'pf-pill--'.$q->estadoColor() : '' }}">{{ $q->statusLabel() }}</span>
                    </div>
                </a>
                @endforeach
            </div>
        </section>
        @endif

        <livewire:ventas.cotizaciones-list />
    </div>
</x-app-layout>
