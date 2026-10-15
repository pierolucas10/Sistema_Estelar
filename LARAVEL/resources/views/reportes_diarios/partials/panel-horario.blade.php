{{-- Panel de horario en la página de Reportes diarios: hoy, próximos días, sin reportar y cumplimiento del equipo --}}
@include('proyectos.partials.estilos')

<div class="pf" style="margin-bottom:24px">

    {{-- Bloques de días pasados sin reportar --}}
    @if($sinReportar->isNotEmpty())
    <section class="pf-card" style="margin-bottom:16px;border-color:rgba(248,113,113,.35)">
        <header class="pf-card__head">
            <div>
                <h2 class="pf-card__title"><x-pf-icon name="alert" style="color:#f87171" /> Bloques sin reportar ({{ $sinReportar->count() }})</h2>
                <div class="pf-card__sub">Días anteriores de tu horario sin reporte. Repórtalos o justifica por qué no se hicieron.</div>
            </div>
        </header>
        <div class="pf-card__body pf-list">
            @foreach($sinReportar->groupBy(fn ($b) => $b->fecha->toDateString()) as $fechaDia => $delDia)
            <div class="pf-item" x-data="{ abierto: null }">
                <div class="pf-between">
                    <span class="pf-item__title">{{ \Illuminate\Support\Carbon::parse($fechaDia)->locale('es')->isoFormat('dddd D [de] MMMM') }}</span>
                    @can('reportes_diarios.crear')
                    <a href="{{ route('reportes_diarios.create', ['fecha' => $fechaDia]) }}" class="pf-btn pf-btn--primary pf-btn--sm">Hacer el reporte de ese día</a>
                    @endcan
                </div>
                @foreach($delDia as $b)
                <div style="margin-top:8px">
                    <div class="pf-req" style="border-top:0;padding:4px 0">
                        <span class="mono" style="color:var(--pf-sky);font-weight:700;font-size:12px">{{ $b->inicio() }}–{{ $b->fin() }}</span>
                        <span class="pf-req__t">{{ $b->actividad }}{{ $b->project ? ' · ' . $b->project->name : '' }}</span>
                        <button type="button" class="pf-btn pf-btn--sm" @click="abierto = abierto === {{ $b->id }} ? null : {{ $b->id }}">Justificar</button>
                    </div>
                    <form method="POST" action="{{ route('reportes_diarios.horario.justificar', $b) }}" class="pf-row" style="display:none;margin-top:6px" x-show="abierto === {{ $b->id }}">
                        @csrf @method('PATCH')
                        <input type="text" name="justificacion" class="pf-input" required maxlength="2000" style="flex:1;min-width:220px" placeholder="¿Por qué no se hizo este bloque?">
                        <button type="submit" class="pf-btn pf-btn--primary pf-btn--sm">Guardar</button>
                    </form>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <div class="pf-split">
        {{-- Mi horario de hoy --}}
        <section class="pf-card">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="clock" /> Mi horario de hoy</h2>
                    <div class="pf-card__sub">{{ today()->locale('es')->isoFormat('dddd D [de] MMMM') }}</div>
                </div>
                <a href="{{ route('reportes_diarios.horario') }}" class="pf-btn pf-btn--sm">
                    @can('reportes_diarios.horario')<x-pf-icon name="plus" /> Crear horario @else Ver semana @endcan
                </a>
            </header>
            <div class="pf-card__body">
                @forelse($bloquesHoy as $b)
                <div class="pf-req">
                    <span class="mono" style="color:var(--pf-sky);font-weight:700;font-size:12px">{{ $b->inicio() }}–{{ $b->fin() }}</span>
                    <span class="pf-req__t" title="{{ $b->descripcion }}">{{ $b->actividad }}{{ $b->project ? ' · ' . $b->project->name : '' }}</span>
                    <span class="pf-pill pf-pill--{{ $b->estadoColor() }}">{{ $b->estadoLabel() }}</span>
                </div>
                @empty
                <div class="pf-empty" style="padding:14px"><x-pf-icon name="clock" />No tienes bloques programados hoy.</div>
                @endforelse
            </div>
        </section>

        {{-- Pendientes de los próximos días --}}
        <section class="pf-card">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="calendar" /> Próximos días</h2>
                    <div class="pf-card__sub">Lo que tienes programado para la semana que viene.</div>
                </div>
            </header>
            <div class="pf-card__body">
                @forelse($proximos as $fechaDia => $delDia)
                <div style="margin-bottom:10px">
                    <div class="pf-section-title" style="margin-bottom:4px">{{ \Illuminate\Support\Carbon::parse($fechaDia)->locale('es')->isoFormat('dddd D/MM') }}</div>
                    @foreach($delDia as $b)
                    <div class="pf-req" style="padding:6px 0">
                        <span class="mono dim" style="font-size:12px">{{ $b->inicio() }}–{{ $b->fin() }}</span>
                        <span class="pf-req__t">{{ $b->actividad }}{{ $b->project ? ' · ' . $b->project->name : '' }}</span>
                    </div>
                    @endforeach
                </div>
                @empty
                <div class="pf-empty" style="padding:14px"><x-pf-icon name="calendar" />Nada programado en los próximos 7 días.</div>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Cumplimiento del equipo (solo quien gestiona) --}}
    @if($esGestor)
    <section class="pf-card" style="margin-top:16px">
        <header class="pf-card__head">
            <div>
                <h2 class="pf-card__title"><x-pf-icon name="chart" /> Cumplimiento del equipo · últimos 7 días</h2>
                <div class="pf-card__sub">Bloques del horario frente a lo reportado. Parcial cuenta como medio bloque.</div>
            </div>
        </header>
        @if($cumplimiento->isEmpty())
            <div class="pf-card__body"><div class="pf-empty" style="padding:14px"><x-pf-icon name="users" />Nadie tiene horario en los últimos 7 días. Créalo desde «Horario».</div></div>
        @else
        <div class="pf-table-wrap">
            <table class="pf-table">
                <thead>
                    <tr><th>Colaborador</th><th class="num">Bloques</th><th class="num">Cumplidos</th><th class="num hide-sm">Parciales</th><th class="num hide-sm">No cumplidos</th><th class="num">Sin reportar</th><th style="width:180px">Cumplimiento</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($cumplimiento as $fila)
                    @php $pct = $fila['porcentaje']; @endphp
                    <tr>
                        <td style="font-weight:700;color:#fff">{{ $fila['usuario']->name ?? '—' }}</td>
                        <td class="num">{{ $fila['total'] }}</td>
                        <td class="num" style="color:var(--pf-green)">{{ $fila['cumplidos'] }}</td>
                        <td class="num hide-sm" style="color:var(--pf-amber)">{{ $fila['parciales'] }}</td>
                        <td class="num hide-sm" style="color:var(--pf-red)">{{ $fila['no_cumplidos'] }}</td>
                        <td class="num" style="{{ $fila['sin_reportar'] ? 'color:var(--pf-red)' : '' }}">{{ $fila['sin_reportar'] }}</td>
                        <td>
                            @if($pct === null)
                                <span class="dim">Aún sin evaluar</span>
                            @else
                                <div class="pf-row" style="flex-wrap:nowrap">
                                    <div class="pf-bar pf-bar--thin {{ $pct >= 90 ? 'is-green' : ($pct >= 60 ? 'is-amber' : '') }}" style="flex:1"><span style="width: {{ $pct }}%;{{ $pct < 60 ? 'background:linear-gradient(90deg,#ef4444,#f87171)' : '' }}"></span></div>
                                    <span class="mono" style="font-weight:800;width:42px;text-align:right">{{ $pct }}%</span>
                                </div>
                            @endif
                        </td>
                        <td style="text-align:right"><a href="{{ route('reportes_diarios.horario', ['user_id' => $fila['usuario']->id ?? null]) }}" class="pf-btn pf-btn--ghost pf-btn--sm">Ver horario</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>
    @endif
</div>
