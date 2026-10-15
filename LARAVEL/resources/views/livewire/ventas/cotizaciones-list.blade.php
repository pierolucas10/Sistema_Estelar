<section class="pf-card">
    <div class="pf-toolbar">
        <div class="pf-chips">
            @foreach($filtros as $clave => $etiqueta)
            <button type="button" wire:click="setFiltro('{{ $clave }}')" class="pf-chip-btn {{ $filtro === $clave ? 'is-active' : '' }}">
                {{ $etiqueta }}@if($conteos[$clave] !== null)<span class="n">{{ $conteos[$clave] }}</span>@endif
            </button>
            @endforeach
        </div>
        <input wire:model.live.debounce.300ms="search" type="search" class="pf-input" style="margin-left:auto;min-width:240px"
               placeholder="Buscar N°, cliente, RUC o servicio…">
    </div>

    <div class="pf-table-wrap" wire:loading.class="opacity-50">
        <table class="pf-table">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Cliente</th>
                    <th class="hide-sm">Servicio</th>
                    <th class="num">Precio</th>
                    <th class="hide-sm">Vigencia</th>
                    <th>Estado</th>
                    <th class="hide-sm">Seguimiento</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotes as $q)
                @php
                    $dias = $q->diasParaVencer();
                    $url = route('cotizaciones.show', $q);
                @endphp
                <tr class="is-link" onclick="if (!event.target.closest('a,button')) window.location = '{{ $url }}'">
                    <td><a href="{{ $url }}" class="mono" style="color:var(--pf-sky);font-weight:800">{{ $q->numero }}</a></td>
                    <td style="min-width:180px">
                        <div style="font-weight:700;color:#fff">{{ $q->client->nombre_comercial ?: $q->client->razon_social }}</div>
                        <div class="dim mono" style="font-size:11.5px">{{ $q->client->numero_documento }}</div>
                    </td>
                    <td class="hide-sm">
                        <div>{{ $q->servicio ?: '—' }}</div>
                        <div class="dim" style="font-size:11.5px">{{ $q->tipo_visita }}</div>
                    </td>
                    <td class="num">{{ $q->montoFormateado() }}</td>
                    <td class="hide-sm" style="white-space:nowrap">
                        @if($dias === null)
                            <span class="dim">{{ $q->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</span>
                        @elseif($dias < 0)
                            <span style="color:var(--pf-red);font-weight:700">Venció hace {{ abs($dias) }} d</span>
                        @elseif($dias === 0)
                            <span style="color:var(--pf-red);font-weight:700">Vence hoy</span>
                        @elseif($dias <= 2)
                            <span style="color:var(--pf-amber);font-weight:700">Vence en {{ $dias }} d</span>
                        @else
                            <span class="muted">Vence {{ $q->fecha_vencimiento->format('d/m') }} · {{ $dias }} d</span>
                        @endif
                    </td>
                    <td>
                        <span class="pf-pill {{ $q->estadoColor() ? 'pf-pill--'.$q->estadoColor() : '' }}">{{ $q->statusLabel() }}</span>
                    </td>
                    <td class="hide-sm" style="white-space:nowrap">
                        @if($q->estaConvertida() && $q->project)
                            <a href="{{ route('proyectos.show', $q->project) }}" style="color:var(--pf-green);font-weight:700">Ver proyecto →</a>
                        @elseif($q->seguimientoAtrasado())
                            <span style="color:var(--pf-amber);font-weight:700">Atrasado ({{ $q->proximo_seguimiento->format('d/m') }})</span>
                        @elseif($q->seguimientoHoy())
                            <span style="color:var(--pf-sky);font-weight:700">Hoy</span>
                        @elseif($q->estaAbierta() && $q->proximo_seguimiento)
                            <span class="muted">{{ $q->proximo_seguimiento->format('d/m') }}</span>
                        @else
                            <span class="dim">{{ $q->seguimientos_count ? $q->seguimientos_count . ' registro(s)' : '—' }}</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        @can('cotizaciones.pdf')
                        <a href="{{ route('cotizaciones.pdf', $q) }}" target="_blank" rel="noopener" class="pf-btn pf-btn--ghost pf-btn--sm" title="Ver PDF"><x-pf-icon name="doc" /></a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="pf-empty">
                            <x-pf-icon name="doc" />
                            No hay cotizaciones {{ $filtro !== 'todas' ? 'en «' . $filtros[$filtro] . '»' : '' }}.
                            @if($search)<div><button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" wire:click="$set('search','')">Limpiar búsqueda</button></div>@endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($quotes->hasPages())
    <div class="pf-card__body">{{ $quotes->links() }}</div>
    @endif
</section>
