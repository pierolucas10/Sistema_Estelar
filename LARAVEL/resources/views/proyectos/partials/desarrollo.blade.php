{{-- Vista de desarrollo (desarrollador / practicante): flujo por pasos --}}
@php
    $pasos    = $proyecto->pasos();
    $actual   = $proyecto->pasoActual();
    $abierto  = min($actual, 4);
    $alertas  = $proyecto->alertasDesarrollo(auth()->user());
    $reqs     = $proyecto->requirements;
    $reqsOk   = $reqs->where('status', 'completado')->count();
    $reqsPct  = $reqs->count() ? (int) round($reqsOk / $reqs->count() * 100) : 0;
    $docs     = $proyecto->archivos->where('categoria', 'documentacion');
    $sprintActivo = $proyecto->sprints->firstWhere('status', 'activo');
    $bloqueado = fn (int $n) => $actual < 5 && $n > $actual;
    $iconosPaso = [1 => 'chat', 2 => 'clock', 3 => 'bolt', 4 => 'flag'];
@endphp

<div x-data="{ abierto: {{ $abierto }} }">

    {{-- ── Stepper ───────────────────────────────────────────── --}}
    <div class="pf-stepper" role="tablist" aria-label="Pasos del proyecto">
        @foreach($pasos as $n => $paso)
        @php
            $clase = $paso['completo'] ? 'is-done' : ($n === $actual ? 'is-current' : ($bloqueado($n) ? 'is-locked' : ''));
        @endphp
        <button type="button" role="tab" class="pf-step {{ $clase }}"
                :class="{ 'is-open': abierto === {{ $n }} }" :aria-selected="abierto === {{ $n }}"
                @click="abierto = {{ $n }}">
            <span class="pf-step__n">
                @if($paso['completo']) <x-pf-icon name="check" />
                @elseif($bloqueado($n)) <x-pf-icon name="lock" />
                @else {{ $n }} @endif
            </span>
            <span>
                <span class="pf-step__t">Paso {{ $n }} · {{ $paso['titulo'] }}</span>
                <span class="pf-step__d" style="display:block">{{ $paso['descripcion'] }}</span>
            </span>
        </button>
        @endforeach
    </div>

    <div class="pf-grid">
        <div class="pf-col" id="paso-actual" style="scroll-margin-top:90px">

            {{-- ══ PASO 1 · Requerimientos ══════════════════════════ --}}
            <div class="pf-col" x-show="abierto === 1" @if($abierto !== 1) style="display:none" @endif>

                @php $sinInicio = $proyecto->reuniones->where('tipo', 'inicio')->isEmpty(); @endphp
                <section class="pf-card" x-data="{ form: {{ $sinInicio || old('titulo') ? 'true' : 'false' }} }">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="chat" /> Reuniones y actas</h2>
                            <div class="pf-card__sub">Empieza con la reunión de inicio: deja el acta con lo que pidió el cliente.</div>
                        </div>
                        <button type="button" class="pf-btn pf-btn--primary pf-btn--sm" @click="form = !form" x-show="!form">
                            <x-pf-icon name="plus" /> Nueva reunión
                        </button>
                    </header>

                    <div class="pf-card__body" x-show="form" x-transition @if(!$sinInicio && !old('titulo')) style="display:none" @endif>
                        @include('proyectos.partials.reunion-form', [
                            'reunion'       => null,
                            'tipoDefault'   => $sinInicio ? 'inicio' : 'requerimientos',
                            'tituloDefault' => $sinInicio ? 'Reunión de inicio con el cliente' : '',
                        ])
                    </div>

                    <div class="pf-card__body">
                        @if($proyecto->reuniones->isEmpty())
                            <div class="pf-empty"><x-pf-icon name="chat" />Aún no hay reuniones registradas.</div>
                        @else
                        <div class="pf-list">
                            @foreach($proyecto->reuniones as $reunion)
                            <article class="pf-item" x-data="{ form: false, ver: {{ $loop->first ? 'true' : 'false' }} }">
                                <div class="pf-item__head">
                                    <div style="min-width:0">
                                        <div class="pf-row">
                                            <span class="pf-item__title">{{ $reunion->titulo }}</span>
                                            <span class="pf-pill {{ $reunion->tipo === 'inicio' ? 'pf-pill--sky' : ($reunion->tipo === 'entrega' ? 'pf-pill--green' : 'pf-pill--violet') }}">{{ $reunion->tipoLabel() }}</span>
                                            @unless($reunion->tieneActa())<span class="pf-pill pf-pill--amber">Sin acta</span>@endunless
                                        </div>
                                        <div class="pf-item__meta">
                                            {{ $reunion->fecha->format('d/m/Y H:i') }}
                                            @if($reunion->createdBy) · registró {{ $reunion->createdBy->name }} @endif
                                        </div>
                                    </div>
                                    <div class="pf-row" style="gap:4px">
                                        <button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" @click="ver = !ver" x-text="ver ? 'Ocultar' : 'Ver acta'">Ver acta</button>
                                        <button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" title="Editar" @click="form = !form; ver = false"><x-pf-icon name="pencil" /></button>
                                        <form method="POST" action="{{ route('proyectos.reuniones.destroy', [$proyecto, $reunion]) }}"
                                              x-data @submit.prevent="if (confirm('¿Eliminar esta reunión y sus adjuntos?')) $el.submit()">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="pf-btn pf-btn--ghost pf-btn--sm" title="Eliminar"><x-pf-icon name="trash" style="color:#f87171" /></button>
                                        </form>
                                    </div>
                                </div>

                                <div x-show="ver" x-transition @unless($loop->first) style="display:none" @endunless>
                                    @if($reunion->asistentes)
                                        <div class="pf-item__label">Asistentes</div>
                                        <div class="pf-item__text" style="margin-top:4px">{{ $reunion->asistentes }}</div>
                                    @endif
                                    @if($reunion->acta)
                                        <div class="pf-item__label">Acta</div>
                                        <div class="pf-item__text" style="margin-top:4px">{{ $reunion->acta }}</div>
                                    @endif
                                    @if($reunion->pendientes)
                                        <div class="pf-item__label">Compromisos</div>
                                        <div class="pf-item__text" style="margin-top:4px">{{ $reunion->pendientes }}</div>
                                    @endif
                                    @if($reunion->archivos->isNotEmpty())
                                        <div class="pf-item__label">Adjuntos</div>
                                        <div class="pf-files" style="margin-top:8px">
                                            @foreach($reunion->archivos as $archivo)
                                                @include('proyectos.partials.archivo', ['puedeBorrar' => true, 'vista' => 'desarrollo'])
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div x-show="form" x-transition style="display:none;margin-top:12px">
                                    @include('proyectos.partials.reunion-form', ['reunion' => $reunion])
                                </div>
                            </article>
                            @endforeach
                        </div>
                        @endif

                        @if($proyecto->notas_reunion)
                        <div class="pf-item" style="margin-top:12px">
                            <div class="pf-item__label" style="margin-top:0">Notas anteriores</div>
                            <div class="pf-item__text">{{ $proyecto->notas_reunion }}</div>
                        </div>
                        @endif
                    </div>
                </section>

                {{-- Requerimientos --}}
                <section class="pf-card" x-data="{ form: {{ $reqs->isEmpty() ? 'true' : 'false' }} }">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="list" /> Requerimientos</h2>
                            <div class="pf-card__sub">{{ $reqsOk }} de {{ $reqs->count() }} completados · lo que salió de las reuniones</div>
                        </div>
                        <div class="pf-row" style="gap:6px">
                            @can('requerimientos.crear')
                            <button type="button" class="pf-btn pf-btn--sm" @click="form = !form"><x-pf-icon name="plus" /> Agregar</button>
                            @endcan
                            @can('requerimientos.ver')
                            <a href="{{ route('requerimientos.index', $proyecto) }}" class="pf-btn pf-btn--sm"><x-pf-icon name="external" /> Gestionar</a>
                            @endcan
                        </div>
                    </header>

                    @can('requerimientos.crear')
                    <div class="pf-card__body" x-show="form" x-transition @unless($reqs->isEmpty()) style="display:none" @endunless>
                        <form method="POST" action="{{ route('requerimientos.store', $proyecto) }}" class="pf-box pf-form pf-form--3">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ $proyecto->id }}">
                            <input type="hidden" name="status" value="pendiente">
                            <label class="pf-field full">
                                <span class="pf-label">Requerimiento</span>
                                <input type="text" name="title" class="pf-input" required maxlength="200" placeholder="Ej.: El usuario puede pagar con Yape">
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Tipo</span>
                                <select name="type" class="pf-input">
                                    @foreach(\App\Models\Requirement::TYPE_LABELS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Prioridad</span>
                                <select name="priority" class="pf-input">
                                    <option value="critica">Crítica</option>
                                    <option value="alta">Alta</option>
                                    <option value="media" selected>Media</option>
                                    <option value="baja">Baja</option>
                                </select>
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Detalle (opcional)</span>
                                <input type="text" name="description" class="pf-input" placeholder="Criterio de aceptación">
                            </label>
                            <div class="pf-form__actions">
                                <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="plus" /> Agregar requerimiento</button>
                            </div>
                        </form>
                    </div>
                    @endcan

                    <div class="pf-card__body">
                        @if($reqs->isEmpty())
                            <div class="pf-empty"><x-pf-icon name="list" />Sin requerimientos todavía.</div>
                        @else
                            <div class="pf-bar {{ $reqsPct >= 100 ? 'is-green' : '' }}" style="margin-bottom:12px"><span style="width: {{ $reqsPct }}%"></span></div>
                            @foreach($reqs->sortBy(fn ($r) => array_search($r->priority, \App\Models\Requirement::PRIORITIES))->take(10) as $req)
                            @php
                                $pc = ['critica' => 'red', 'alta' => 'amber', 'media' => 'sky'][$req->priority] ?? '';
                                $sc = ['completado' => 'green', 'en_progreso' => 'sky', 'en_revision' => 'violet', 'rechazado' => 'red'][$req->status] ?? '';
                            @endphp
                            <div class="pf-req {{ $req->status === 'completado' ? 'is-done' : '' }}">
                                <span class="pf-pill pf-pill--plain {{ $pc ? 'pf-pill--'.$pc : '' }}" style="min-width:64px;justify-content:center">{{ ucfirst($req->priority) }}</span>
                                <span class="pf-req__t" title="{{ $req->title }}">{{ $req->title }}</span>
                                @if($req->assignedTo)<span class="dim" style="font-size:12px">{{ \Illuminate\Support\Str::before($req->assignedTo->name, ' ') }}</span>@endif
                                <span class="pf-pill {{ $sc ? 'pf-pill--'.$sc : '' }}">{{ $req->statusLabel() }}</span>
                            </div>
                            @endforeach
                            @if($reqs->count() > 10)
                                <div class="pf-hint" style="margin-top:8px">y {{ $reqs->count() - 10 }} más en «Gestionar».</div>
                            @endif
                        @endif
                    </div>
                </section>
            </div>

            {{-- ══ PASO 2 · Estimación ══════════════════════════════ --}}
            <div class="pf-col" x-show="abierto === 2" @if($abierto !== 2) style="display:none" @endif>
                @if($bloqueado(2))
                <div class="pf-locked"><x-pf-icon name="lock" /><span>Completa el <strong>paso 1</strong> (reunión de inicio con acta y al menos un requerimiento) para estimar el tiempo.</span></div>
                @endif

                <section class="pf-card">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="clock" /> ¿Cuánto tiempo tomará?</h2>
                            <div class="pf-card__sub">Estima en días hábiles (lunes a viernes). Calculamos la fecha de entrega.</div>
                        </div>
                        @if($proyecto->tieneEstimacion())<span class="pf-pill pf-pill--green">Estimado</span>@endif
                    </header>

                    @if($proyecto->tieneEstimacion())
                    @php
                        $difPactada = $proyecto->end_date && $proyecto->estimacion_fecha_fin
                            ? (int) $proyecto->end_date->diffInDays($proyecto->estimacion_fecha_fin, false) : null;
                    @endphp
                    <div class="pf-card__body">
                        <div class="pf-split">
                            <div class="pf-box">
                                <div class="pf-kpi__label">Duración estimada</div>
                                <div class="pf-row" style="margin-top:8px;align-items:baseline">
                                    <span class="pf-big-number">{{ $proyecto->estimacion_dias }}</span>
                                    <span class="muted">días hábiles</span>
                                </div>
                                <div class="pf-hint" style="margin-top:8px">≈ {{ round($proyecto->estimacion_dias / 5, 1) }} semanas</div>
                            </div>
                            <div class="pf-box">
                                <div class="pf-kpi__label">Entrega estimada</div>
                                <div class="pf-big-number" style="font-size:26px;margin-top:8px">{{ $proyecto->estimacion_fecha_fin?->format('d/m/Y') }}</div>
                                <div class="pf-hint" style="margin-top:8px">
                                    Por {{ $proyecto->estimadoPor->name ?? '—' }} · {{ $proyecto->estimado_at?->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>
                        @if($difPactada !== null && $difPactada > 0)
                        <div class="pf-alert pf-alert--warning" style="margin-top:12px">
                            <x-pf-icon name="alert" /><span>La estimación supera en {{ $difPactada }} día(s) la fecha pactada con el cliente ({{ $proyecto->end_date->format('d/m/Y') }}). Avisa al responsable.</span>
                        </div>
                        @endif
                        @if($proyecto->estimacion_detalle)
                            <div class="pf-item__label">Desglose</div>
                            <div class="pf-item__text" style="margin-top:4px">{{ $proyecto->estimacion_detalle }}</div>
                        @endif
                    </div>
                    @endif

                    @unless($bloqueado(2))
                    <div class="pf-card__body" x-data="{
                            dias: {{ (int) old('estimacion_dias', $proyecto->estimacion_dias ?? 10) }},
                            desde: @js((string) old('desde', ($proyecto->start_date ?? today())->toDateString())),
                            get fin() {
                                if (!this.desde || !this.dias || this.dias < 1) return '—';
                                const d = new Date(this.desde + 'T12:00:00'); let n = parseInt(this.dias, 10);
                                while (n > 0) { d.setDate(d.getDate() + 1); const w = d.getDay(); if (w !== 0 && w !== 6) n--; }
                                return d.toLocaleDateString('es-PE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
                            }
                        }">
                        <form method="POST" action="{{ route('proyectos.estimacion.update', $proyecto) }}" class="pf-form">
                            @csrf @method('PATCH')
                            <label class="pf-field">
                                <span class="pf-label">Días hábiles de desarrollo</span>
                                <input type="number" name="estimacion_dias" class="pf-input mono" min="1" max="1000" required x-model="dias">
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Contar desde</span>
                                <input type="date" name="desde" class="pf-input" x-model="desde">
                            </label>
                            <div class="full pf-alert pf-alert--info">
                                <x-pf-icon name="calendar" /><span>Entrega estimada: <strong x-text="fin"></strong></span>
                            </div>
                            <label class="pf-field full">
                                <span class="pf-label">Desglose (por módulo o etapa)</span>
                                <textarea name="estimacion_detalle" class="pf-input" rows="5" maxlength="5000"
                                          placeholder="Ej.:&#10;- Base de datos y login: 3 días&#10;- Módulo de ventas: 5 días&#10;- Pruebas y ajustes: 2 días">{{ old('estimacion_detalle', $proyecto->estimacion_detalle) }}</textarea>
                            </label>
                            <div class="pf-form__actions">
                                <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="check" /> {{ $proyecto->tieneEstimacion() ? 'Actualizar estimación' : 'Guardar estimación' }}</button>
                            </div>
                        </form>
                    </div>
                    @endunless
                </section>

                <section class="pf-card">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="doc" /> Documentación del proyecto</h2>
                            <div class="pf-card__sub">Análisis de requerimientos, cronograma, diagrama de BD, mockups, manuales…</div>
                        </div>
                        @if($docs->isNotEmpty())<span class="pf-pill pf-pill--green">{{ $docs->count() }} archivo(s)</span>@else<span class="pf-pill pf-pill--amber">Pendiente</span>@endif
                    </header>
                    <div class="pf-card__body pf-gap">
                        @if($docs->isNotEmpty())
                        <div class="pf-files">
                            @foreach($docs as $archivo)
                                @include('proyectos.partials.archivo', ['puedeBorrar' => true, 'vista' => 'desarrollo'])
                            @endforeach
                        </div>
                        @endif
                        @unless($bloqueado(2))
                        <form method="POST" action="{{ route('proyectos.archivos.store', $proyecto) }}" enctype="multipart/form-data" class="pf-box pf-box--dashed pf-form">
                            @csrf
                            <input type="hidden" name="categoria" value="documentacion">
                            <input type="hidden" name="vista" value="desarrollo">
                            <label class="pf-field">
                                <span class="pf-label">Archivos</span>
                                <input type="file" name="archivos[]" class="pf-input" multiple required>
                                <span class="pf-hint">Hasta 10 archivos de 20 MB.</span>
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">¿Qué es? (opcional)</span>
                                <input type="text" name="descripcion" class="pf-input" maxlength="255" placeholder="Ej.: cronograma v1">
                            </label>
                            <div class="pf-form__actions">
                                <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="upload" /> Adjuntar documentación</button>
                            </div>
                        </form>
                        @endunless
                    </div>
                </section>
            </div>

            {{-- ══ PASO 3 · Desarrollo ══════════════════════════════ --}}
            <div class="pf-col" x-show="abierto === 3" @if($abierto !== 3) style="display:none" @endif>
                @if($bloqueado(3))
                <div class="pf-locked"><x-pf-icon name="lock" /><span>Completa el <strong>paso 2</strong> (estimación y documentación) antes de arrancar los sprints.</span></div>
                @endif

                <section class="pf-card">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="bolt" /> Sprints</h2>
                            <div class="pf-card__sub">{{ $proyecto->sprints->count() }} sprint(s) · {{ $proyecto->sprints->where('status', 'completado')->count() }} completado(s)</div>
                        </div>
                        @can('sprints.ver')
                        <a href="{{ route('sprints.index', $proyecto) }}" class="pf-btn pf-btn--sm {{ $bloqueado(3) ? '' : 'pf-btn--primary' }}"><x-pf-icon name="external" /> Abrir sprints</a>
                        @endcan
                    </header>
                    <div class="pf-card__body pf-gap">
                        @if($sprintActivo)
                        @php
                            $sTotal = $sprintActivo->requirements->count();
                            $sOk = $sprintActivo->requirements->where('status', 'completado')->count();
                            $sPct = $sTotal ? (int) round($sOk / $sTotal * 100) : 0;
                            $sDias = $sprintActivo->end_date ? (int) today()->diffInDays($sprintActivo->end_date, false) : null;
                        @endphp
                        <div class="pf-sprint is-activo">
                            <div class="pf-between">
                                <div>
                                    <div class="pf-row"><span class="pf-item__title">{{ $sprintActivo->name }}</span><span class="pf-pill pf-pill--sky">Activo</span></div>
                                    @if($sprintActivo->goal)<div class="pf-item__meta">{{ $sprintActivo->goal }}</div>@endif
                                </div>
                                @can('sprints.ver')
                                <a href="{{ route('sprints.show', [$proyecto, $sprintActivo]) }}" class="pf-btn pf-btn--sm">Ver tablero</a>
                                @endcan
                            </div>
                            <div class="pf-between" style="margin-top:12px;font-size:12.5px">
                                <span class="muted">{{ $sOk }} / {{ $sTotal }} requerimientos</span>
                                <span class="mono {{ $sDias !== null && $sDias < 0 ? '' : 'muted' }}" @if($sDias !== null && $sDias < 0) style="color:#f87171" @endif>
                                    @if($sDias === null) Sin fecha de fin
                                    @elseif($sDias < 0) Vencido hace {{ abs($sDias) }} d
                                    @elseif($sDias === 0) Cierra hoy
                                    @else Quedan {{ $sDias }} d @endif
                                </span>
                            </div>
                            <div class="pf-bar {{ $sPct >= 100 ? 'is-green' : '' }}" style="margin-top:8px"><span style="width: {{ $sPct }}%"></span></div>
                        </div>
                        @endif

                        @php $otrosSprints = $proyecto->sprints->where('status', '!=', 'activo'); @endphp
                        @if($otrosSprints->isNotEmpty())
                        <div class="pf-list">
                            @foreach($otrosSprints as $sprint)
                            @php $spc = ['completado' => 'green', 'cancelado' => 'red'][$sprint->status] ?? ''; @endphp
                            <div class="pf-req">
                                <span class="pf-req__t">{{ $sprint->name }}</span>
                                <span class="dim mono" style="font-size:12px">{{ $sprint->start_date?->format('d/m') }} – {{ $sprint->end_date?->format('d/m') }}</span>
                                <span class="pf-pill {{ $spc ? 'pf-pill--'.$spc : '' }}">{{ $sprint->statusLabel() }}</span>
                            </div>
                            @endforeach
                        </div>
                        @elseif(!$sprintActivo)
                            <div class="pf-empty"><x-pf-icon name="bolt" />Aún no hay sprints. Créalos desde «Abrir sprints».</div>
                        @endif
                    </div>
                </section>

                <section class="pf-card">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="users" /> Dailies de la semana</h2>
                            <div class="pf-card__sub">Qué hizo cada uno, qué hará y si tiene bloqueos.</div>
                        </div>
                        @if($sprintActivo && auth()->user()->can('sprints.daily'))
                        <a href="{{ route('sprints.show', [$proyecto, $sprintActivo]) }}" class="pf-btn pf-btn--sm"><x-pf-icon name="plus" /> Registrar daily</a>
                        @endif
                    </header>
                    <div class="pf-card__body">
                        @if($proyecto->dailyReports->isEmpty())
                            <div class="pf-empty"><x-pf-icon name="users" />Sin dailies en los últimos 7 días.</div>
                        @else
                        <div class="pf-list">
                            @foreach($proyecto->dailyReports->take(8) as $daily)
                            <div class="pf-item" @if($daily->tieneBlockers()) style="border-color:rgba(248,113,113,.3)" @endif>
                                <div class="pf-item__head">
                                    <span class="pf-item__title">{{ $daily->user->name ?? '—' }}</span>
                                    <span class="dim mono" style="font-size:12px">{{ $daily->date?->format('d/m/Y') }}</span>
                                </div>
                                @if($daily->today)<div class="pf-item__text" style="margin-top:6px"><span class="dim">Hoy:</span> {{ $daily->today }}</div>@endif
                                @if($daily->tieneBlockers())<div class="pf-item__text" style="margin-top:6px;color:#fecaca"><span style="color:#f87171;font-weight:700">Bloqueo:</span> {{ $daily->blockers }}</div>@endif
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </section>

                @if($proyecto->phases->isNotEmpty())
                <section class="pf-card">
                    <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="chart" /> Fases</h2></header>
                    <div class="pf-card__body pf-gap-sm">
                        @foreach($proyecto->phases as $fase)
                        <div>
                            <div class="pf-between" style="font-size:13px"><span>{{ $fase->name }}</span><span class="mono muted">{{ $fase->progress }}%</span></div>
                            <div class="pf-bar pf-bar--thin {{ $fase->progress >= 100 ? 'is-green' : '' }}" style="margin-top:6px"><span style="width: {{ $fase->progress }}%"></span></div>
                        </div>
                        @endforeach
                    </div>
                </section>
                @endif
            </div>

            {{-- ══ PASO 4 · Entrega ═════════════════════════════════ --}}
            <div class="pf-col" x-show="abierto === 4" @if($abierto !== 4) style="display:none" @endif>
                @if($bloqueado(4))
                <div class="pf-locked"><x-pf-icon name="lock" /><span>Termina el <strong>desarrollo</strong> (todos los requerimientos completados) para preparar la entrega.</span></div>
                @endif

                @if(!empty($proyecto->checklist))
                <section class="pf-card">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="check-circle" /> Entregables</h2>
                            <div class="pf-card__sub">{{ $proyecto->checklistCompletados() }} de {{ $proyecto->checklistTotal() }} listos</div>
                        </div>
                    </header>
                    <div class="pf-card__body">
                        <div class="pf-files" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
                            @foreach($proyecto->checklist as $index => $item)
                            <div class="pf-check {{ $item['completado'] ? 'is-done' : '' }}">
                                @can('proyectos.editar')
                                <form method="POST" action="{{ route('proyectos.checklist.toggle', [$proyecto, $index]) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="pf-check__box" title="Marcar">@if($item['completado'])<x-pf-icon name="check" />@endif</button>
                                </form>
                                @else
                                <span class="pf-check__box">@if($item['completado'])<x-pf-icon name="check" />@endif</span>
                                @endcan
                                <span>{{ $item['nombre'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </section>
                @endif

                @php $entregas = $proyecto->reuniones->where('tipo', 'entrega'); @endphp
                <section class="pf-card" x-data="{ form: false }">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="flag" /> Reunión de entrega</h2>
                            <div class="pf-card__sub">Registra la presentación final con el acta de conformidad del cliente.</div>
                        </div>
                        @unless($bloqueado(4))
                        <button type="button" class="pf-btn pf-btn--primary pf-btn--sm" @click="form = !form" x-show="!form"><x-pf-icon name="plus" /> Registrar entrega</button>
                        @endunless
                    </header>
                    <div class="pf-card__body" x-show="form" x-transition style="display:none">
                        @include('proyectos.partials.reunion-form', ['reunion' => null, 'tipoDefault' => 'entrega', 'tituloDefault' => 'Entrega final del proyecto'])
                    </div>
                    <div class="pf-card__body">
                        @forelse($entregas as $reunion)
                            <div class="pf-req">
                                <span class="pf-req__t">{{ $reunion->titulo }}</span>
                                <span class="dim mono" style="font-size:12px">{{ $reunion->fecha->format('d/m/Y') }}</span>
                                <span class="pf-pill {{ $reunion->tieneActa() ? 'pf-pill--green' : 'pf-pill--amber' }}">{{ $reunion->tieneActa() ? 'Con acta' : 'Sin acta' }}</span>
                            </div>
                        @empty
                            <div class="pf-empty"><x-pf-icon name="flag" />Aún no hay reunión de entrega.</div>
                        @endforelse
                        <div class="pf-alert {{ $proyecto->status === 'entregado' ? 'pf-alert--ok' : 'pf-alert--info' }}" style="margin-top:12px">
                            <x-pf-icon :name="$proyecto->status === 'entregado' ? 'check-circle' : 'info'" />
                            <span>{{ $proyecto->status === 'entregado'
                                ? 'Proyecto entregado. ¡Buen trabajo!'
                                : 'Cuando el cliente dé su conformidad, el responsable marca el proyecto como «Entregado».' }}</span>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        {{-- ── Columna lateral ─────────────────────────────────── --}}
        <aside class="pf-col">
            <section class="pf-card">
                <header class="pf-card__head">
                    <h2 class="pf-card__title"><x-pf-icon name="alert" style="color:#fbbf24" /> Alertas</h2>
                    @if(count($alertas))<span class="pf-pill pf-pill--amber pf-pill--plain">{{ count($alertas) }}</span>@endif
                </header>
                <div class="pf-card__body">
                    @include('proyectos.partials.alertas', ['alertas' => $alertas, 'vacio' => 'Todo en orden. Sin alertas por ahora.'])
                </div>
            </section>

            <section class="pf-card">
                <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="chart" /> Resumen</h2></header>
                <div class="pf-card__body">
                    <div class="pf-row" style="gap:16px;margin-bottom:14px">
                        <div class="pf-ring pf-ring--sm" style="--p: {{ $proyecto->progress }}; --c: {{ $proyecto->progress >= 100 ? '#10b981' : '#0ea5e9' }}">
                            <div class="pf-ring__inner"><div class="pf-ring__value">{{ $proyecto->progress }}%</div></div>
                        </div>
                        <div>
                            <div style="font-weight:800;color:#fff">{{ $actual <= 4 ? 'Paso ' . $actual . ': ' . $pasos[$actual]['titulo'] : 'Flujo completo' }}</div>
                            <div class="pf-hint">{{ $actual <= 4 ? $pasos[$actual]['descripcion'] : 'Todos los pasos están listos' }}</div>
                        </div>
                    </div>
                    <div class="pf-kv">
                        <div class="pf-kv__row"><span class="pf-kv__k">Requerimientos</span><span class="pf-kv__v">{{ $reqsOk }} / {{ $reqs->count() }}</span></div>
                        <div class="pf-kv__row"><span class="pf-kv__k">Reuniones</span><span class="pf-kv__v">{{ $proyecto->reuniones->count() }}</span></div>
                        <div class="pf-kv__row"><span class="pf-kv__k">Estimación</span><span class="pf-kv__v">{{ $proyecto->estimacion_dias ? $proyecto->estimacion_dias . ' días háb.' : '—' }}</span></div>
                        <div class="pf-kv__row"><span class="pf-kv__k">Fecha objetivo</span><span class="pf-kv__v mono">{{ $proyecto->fechaObjetivo()?->format('d/m/Y') ?? '—' }}</span></div>
                        <div class="pf-kv__row"><span class="pf-kv__k">Sprint activo</span><span class="pf-kv__v">{{ $sprintActivo->name ?? '—' }}</span></div>
                        <div class="pf-kv__row"><span class="pf-kv__k">Documentos</span><span class="pf-kv__v">{{ $docs->count() }}</span></div>
                    </div>
                </div>
            </section>

            @php $compartidos = $proyecto->archivos->where('categoria', 'otro'); @endphp
            @if($compartidos->isNotEmpty())
            <section class="pf-card">
                <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="folder" /> Archivos compartidos</h2></header>
                <div class="pf-card__body">
                    <div class="pf-files" style="grid-template-columns:minmax(0,1fr)">
                        @foreach($compartidos as $archivo)
                            @include('proyectos.partials.archivo', ['puedeBorrar' => false, 'vista' => 'desarrollo'])
                        @endforeach
                    </div>
                </div>
            </section>
            @endif
        </aside>
    </div>
</div>
