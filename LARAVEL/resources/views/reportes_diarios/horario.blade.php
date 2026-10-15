<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('reportes_diarios.index') }}" class="text-slate-600 hover:text-slate-400 font-mono">Reportes diarios</a>
            <span class="text-slate-700">/</span>
            <span class="text-white font-semibold">Horario</span>
        </div>
    </x-slot>

    @include('proyectos.partials.estilos')

    @php
        $esPropio = $persona->id === auth()->id();
        $paramsUsuario = $esPropio ? [] : ['user_id' => $persona->id];
        $horasPlan = $resumen->sum(fn ($b) => $b->horas());
        $cumplidos = $resumen->where('estado', 'cumplido')->count();
        $parciales = $resumen->where('estado', 'parcial')->count();
        $fallidos = $resumen->filter(fn ($b) => $b->estado === 'no_cumplido' || $b->atrasado())->count();
    @endphp

    <style>
        .hz-week { display: grid; grid-template-columns: repeat(7, minmax(132px, 1fr)); gap: 10px; overflow-x: auto; padding-bottom: 6px; }
        .hz-day { background: var(--pf-card); border: 1px solid var(--pf-line); border-radius: 16px; min-height: 180px; display: flex; flex-direction: column; }
        .hz-day.is-today { border-color: rgba(56,189,248,.5); box-shadow: 0 10px 30px -20px rgba(14,165,233,.9); }
        .hz-day.is-past { opacity: .92; }
        .hz-day__h { padding: 10px 12px; border-bottom: 1px solid var(--pf-line); display: flex; justify-content: space-between; align-items: baseline; }
        .hz-day__n { font-weight: 800; color: #fff; font-size: 13px; }
        .hz-day__f { font-size: 11.5px; color: var(--pf-dim); font-family: 'JetBrains Mono', monospace; }
        .hz-day.is-today .hz-day__n { color: var(--pf-sky); }
        .hz-day__b { padding: 8px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .hz-blk { border-radius: 12px; padding: 9px 10px; background: var(--pf-card-2); border: 1px solid var(--pf-line); border-left: 3px solid var(--pf-sky); font-size: 12.5px; }
        .hz-blk.c-green { border-left-color: var(--pf-green); }
        .hz-blk.c-amber { border-left-color: var(--pf-amber); }
        .hz-blk.c-red   { border-left-color: var(--pf-red); }
        .hz-blk__t { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--pf-muted); }
        .hz-blk__a { font-weight: 800; color: #fff; margin-top: 3px; overflow-wrap: anywhere; line-height: 1.3; }
        .hz-blk__p { font-size: 11.5px; color: var(--pf-sky); margin-top: 2px; }
        .hz-blk__d { font-size: 11.5px; color: var(--pf-muted); margin-top: 5px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .hz-empty { font-size: 12px; color: #475569; text-align: center; padding: 18px 4px; }
        .hz-dias { display: flex; gap: 6px; flex-wrap: wrap; }
        .hz-dias label { cursor: pointer; }
        .hz-dias input { position: absolute; opacity: 0; pointer-events: none; }
        .hz-dias span { display: inline-block; padding: 7px 11px; border-radius: 10px; border: 1px solid var(--pf-line-2); font-size: 12.5px; font-weight: 700; color: var(--pf-muted); }
        .hz-dias input:checked + span { background: rgba(56,189,248,.15); color: var(--pf-sky); border-color: rgba(56,189,248,.4); }
    </style>

    <div class="pf" x-data="{ crear: {{ $errors->any() ? 'true' : 'false' }} }">

        {{-- ── Encabezado ─────────────────────────────────────── --}}
        <div class="pf-between" style="margin-bottom:16px">
            <div>
                <h2 class="pf-title" style="font-size:22px">{{ $esPropio ? 'Mi horario' : 'Horario de ' . $persona->name }}</h2>
                <p class="pf-sub">Semana del {{ $lunes->format('d/m') }} al {{ $lunes->copy()->addDays(6)->format('d/m/Y') }} · el reporte diario rinde cuentas de cada bloque.</p>
            </div>
            <div class="pf-row">
                @if($esGestor)
                <form method="GET" class="pf-row">
                    <input type="hidden" name="semana" value="{{ $lunes->toDateString() }}">
                    <select name="user_id" class="pf-input" style="width:auto" onchange="this.form.submit()">
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" @selected($u->id === $persona->id)>{{ $u->name }}{{ $u->id === auth()->id() ? ' (yo)' : '' }}</option>
                        @endforeach
                    </select>
                </form>
                @endif
                @if($puedeCrear)
                <button type="button" class="pf-btn pf-btn--primary" @click="crear = !crear"><x-pf-icon name="plus" /> <span x-text="crear ? 'Cerrar' : 'Crear horario'">Crear horario</span></button>
                @endif
            </div>
        </div>

        @include('cotizaciones.partials.errores')

        {{-- ── Crear bloques ──────────────────────────────────── --}}
        @if($puedeCrear)
        <section class="pf-card" style="margin-bottom:16px;{{ $errors->any() ? '' : 'display:none' }}" x-show="crear" x-transition>
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="calendar" /> Nuevo bloque de horario</h2>
                    <div class="pf-card__sub">Ej.: de 08:00 a 12:00 · Proyecto A · qué se va a hacer. Puedes repetirlo en varios días.</div>
                </div>
            </header>
            <div class="pf-card__body" x-data="{ repetir: {{ old('hasta') ? 'true' : 'false' }} }">
                <form method="POST" action="{{ route('reportes_diarios.horario.store') }}" class="pf-form pf-form--3">
                    @csrf
                    @if($esGestor)
                    <label class="pf-field">
                        <span class="pf-label">Para</span>
                        <select name="user_id" class="pf-input">
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}" @selected((int) old('user_id', $persona->id) === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @endif
                    <label class="pf-field">
                        <span class="pf-label">Proyecto</span>
                        <select name="project_id" class="pf-input">
                            <option value="">— Sin proyecto —</option>
                            @foreach($proyectos as $p)
                                <option value="{{ $p->id }}" @selected((int) old('project_id') === $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="pf-field {{ $esGestor ? '' : 'full' }}" @if(!$esGestor) style="grid-column: span 2" @endif>
                        <span class="pf-label">Actividad</span>
                        <input type="text" name="actividad" class="pf-input" required maxlength="200" value="{{ old('actividad') }}" placeholder="Ej.: Desarrollo del módulo de ventas">
                    </label>
                    <label class="pf-field full">
                        <span class="pf-label">¿Qué se debe hacer? (opcional)</span>
                        <textarea name="descripcion" class="pf-input" rows="2" maxlength="2000" placeholder="Detalle o entregable esperado de este bloque">{{ old('descripcion') }}</textarea>
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Desde la hora</span>
                        <input type="time" name="hora_inicio" class="pf-input" required value="{{ old('hora_inicio', '08:00') }}">
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Hasta la hora</span>
                        <input type="time" name="hora_fin" class="pf-input" required value="{{ old('hora_fin', '12:00') }}">
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Fecha</span>
                        <input type="date" name="desde" class="pf-input" required value="{{ old('desde', today()->toDateString()) }}">
                    </label>
                    <label class="pf-row full" style="gap:8px;cursor:pointer">
                        <input type="checkbox" x-model="repetir" style="accent-color:#0ea5e9;width:16px;height:16px">
                        <span class="pf-label" style="margin:0">Repetir en varios días</span>
                    </label>
                    <label class="pf-field" x-show="repetir" style="{{ old('hasta') ? '' : 'display:none' }}">
                        <span class="pf-label">Repetir hasta</span>
                        <input type="date" name="hasta" class="pf-input" :disabled="!repetir" value="{{ old('hasta', today()->addDays(13)->toDateString()) }}">
                    </label>
                    <div class="pf-field" x-show="repetir" style="grid-column: span 2;{{ old('hasta') ? '' : 'display:none' }}">
                        <span class="pf-label">Días de la semana</span>
                        <div class="hz-dias">
                            @foreach($nombresDia as $n => $nombre)
                            <label><input type="checkbox" name="dias[]" value="{{ $n }}" :disabled="!repetir" @checked(in_array($n, old('dias', [1, 2, 3, 4, 5])))><span>{{ $nombre }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="pf-form__actions">
                        <button type="button" class="pf-btn pf-btn--ghost" @click="crear = false">Cancelar</button>
                        <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="check" /> Programar</button>
                    </div>
                </form>
            </div>
        </section>
        @endif

        {{-- ── Resumen de la semana ───────────────────────────── --}}
        <div class="pf-tiles">
            <div class="pf-tile"><span class="pf-tile__icon"><x-pf-icon name="calendar" /></span><div><div class="pf-tile__v">{{ $resumen->count() }}</div><div class="pf-tile__l">Bloques · {{ rtrim(rtrim(number_format($horasPlan, 1), '0'), '.') }} h planificadas</div></div></div>
            <div class="pf-tile"><span class="pf-tile__icon is-green"><x-pf-icon name="check-circle" /></span><div><div class="pf-tile__v">{{ $cumplidos }}</div><div class="pf-tile__l">Cumplidos</div></div></div>
            <div class="pf-tile"><span class="pf-tile__icon is-amber"><x-pf-icon name="clock" /></span><div><div class="pf-tile__v">{{ $parciales }}</div><div class="pf-tile__l">Parciales</div></div></div>
            <div class="pf-tile"><span class="pf-tile__icon is-red"><x-pf-icon name="alert" /></span><div><div class="pf-tile__v">{{ $fallidos }}</div><div class="pf-tile__l">No cumplidos o sin reportar</div></div></div>
        </div>

        {{-- ── Navegación de semanas ──────────────────────────── --}}
        <div class="pf-between" style="margin-bottom:10px">
            <a class="pf-btn pf-btn--sm" href="{{ route('reportes_diarios.horario', ['semana' => $lunes->copy()->subWeek()->toDateString()] + $paramsUsuario) }}">‹ Semana anterior</a>
            <a class="pf-btn pf-btn--sm pf-btn--ghost" href="{{ route('reportes_diarios.horario', $paramsUsuario) }}">Esta semana</a>
            <a class="pf-btn pf-btn--sm" href="{{ route('reportes_diarios.horario', ['semana' => $lunes->copy()->addWeek()->toDateString()] + $paramsUsuario) }}">Semana siguiente ›</a>
        </div>

        {{-- ── Agenda semanal ─────────────────────────────────── --}}
        <div class="hz-week">
            @foreach($dias as $dia)
            @php $delDia = $bloques->get($dia->toDateString(), collect()); @endphp
            <div class="hz-day {{ $dia->isToday() ? 'is-today' : '' }} {{ $dia->lt(today()) ? 'is-past' : '' }}">
                <div class="hz-day__h">
                    <span class="hz-day__n">{{ $nombresDia[$dia->dayOfWeekIso] }}{{ $dia->isToday() ? ' · hoy' : '' }}</span>
                    <span class="hz-day__f">{{ $dia->format('d/m') }}</span>
                </div>
                <div class="hz-day__b">
                    @forelse($delDia as $b)
                    <div class="hz-blk c-{{ $b->estadoColor() }}" x-data="{ just: false }">
                        <div class="pf-between" style="gap:4px">
                            <span class="hz-blk__t">{{ $b->inicio() }}–{{ $b->fin() }}</span>
                            <span class="pf-pill pf-pill--{{ $b->estadoColor() }}" style="padding:2px 7px;font-size:10px">{{ $b->estadoLabel() }}</span>
                        </div>
                        <div class="hz-blk__a">{{ $b->actividad }}</div>
                        @if($b->project)<div class="hz-blk__p">{{ $b->project->name }}</div>@endif
                        @if($b->reportado())
                            @if($b->detalle)<div class="hz-blk__d" title="{{ $b->detalle }}">✓ {{ $b->detalle }}</div>@endif
                            @if($b->justificacion)<div class="hz-blk__d" style="color:#fca5a5" title="{{ $b->justificacion }}">⚠ {{ $b->justificacion }}</div>@endif
                            <div class="pf-row" style="gap:6px;margin-top:6px">
                                @if($b->archivos->count())<span class="dim" style="font-size:11px">{{ $b->archivos->count() }} evidencia(s)</span>@endif
                                @if($b->reporte_diario_id)<a href="{{ route('reportes_diarios.show', $b->reporte_diario_id) }}" style="font-size:11px;color:var(--pf-sky);font-weight:700">Ver reporte →</a>@endif
                            </div>
                        @elseif($b->descripcion)
                            <div class="hz-blk__d" title="{{ $b->descripcion }}">{{ $b->descripcion }}</div>
                        @endif

                        @unless($b->reportado())
                        <div class="pf-row" style="gap:4px;margin-top:6px">
                            @if($b->atrasado() && $esPropio)
                            <button type="button" class="pf-btn pf-btn--sm" style="padding:4px 8px;font-size:11px" @click="just = !just">Justificar</button>
                            @endif
                            @if($esGestor || ($esPropio && $puedeCrear))
                            <form method="POST" action="{{ route('reportes_diarios.horario.destroy', $b) }}" x-data @submit.prevent="if (confirm('¿Quitar este bloque del horario?')) $el.submit()" style="margin-left:auto">
                                @csrf @method('DELETE')
                                <button type="submit" class="pf-btn pf-btn--ghost pf-btn--sm" style="padding:4px" title="Quitar"><x-pf-icon name="trash" style="color:#f87171;width:13px;height:13px" /></button>
                            </form>
                            @endif
                        </div>
                        @if($b->atrasado() && $esPropio)
                        <form method="POST" action="{{ route('reportes_diarios.horario.justificar', $b) }}" x-show="just" style="display:none;margin-top:6px">
                            @csrf @method('PATCH')
                            <textarea name="justificacion" class="pf-input" rows="2" required maxlength="2000" style="font-size:12px;min-height:60px" placeholder="¿Por qué no se hizo?"></textarea>
                            <button type="submit" class="pf-btn pf-btn--primary pf-btn--sm pf-btn--block" style="margin-top:4px">Guardar</button>
                        </form>
                        @endif
                        @endunless
                    </div>
                    @empty
                    <div class="hz-empty">Sin bloques</div>
                    @endforelse
                </div>
            </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
