<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('reportes_diarios.index') }}" class="text-slate-600 hover:text-slate-400 font-mono">Reportes diarios</a>
            <span class="text-slate-700">/</span>
            <a href="{{ route('reportes_diarios.show', $reporte) }}" class="text-slate-600 hover:text-slate-400 font-mono">{{ $reporte->fecha->format('d/m/Y') }}</a>
            <span class="text-slate-700">/</span>
            <span class="text-white font-semibold">Subsanar</span>
        </div>
    </x-slot>

    @include('proyectos.partials.estilos')
    <style>
        .pf-chip-btn.pf-estado-cumplido.is-active { background: rgba(52,211,153,.14); color: #34d399; border-color: rgba(52,211,153,.4); }
        .pf-chip-btn.pf-estado-parcial.is-active { background: rgba(251,191,36,.14); color: #fbbf24; border-color: rgba(251,191,36,.4); }
        .pf-chip-btn.pf-estado-no_cumplido.is-active { background: rgba(248,113,113,.14); color: #f87171; border-color: rgba(248,113,113,.4); }
    </style>

    <div class="pf" style="max-width:900px">
        <div style="margin-bottom:16px">
            <h2 class="pf-title" style="font-size:22px">Subsanar reporte del {{ $reporte->fecha->locale('es')->isoFormat('dddd D [de] MMMM') }}</h2>
            <p class="pf-sub">Corrige lo que pidió gerencia. Al enviarlo, el reporte vuelve a revisión como «Subsanado».</p>
        </div>

        <div class="pf-alert pf-alert--warning" style="margin-bottom:16px">
            <x-pf-icon name="alert" />
            <div>
                <strong>Observación de {{ $reporte->revisadoPor->name ?? 'gerencia' }}</strong>
                @if($reporte->revisado_at)<span class="dim"> · {{ $reporte->revisado_at->format('d/m/Y H:i') }}</span>@endif
                <div style="white-space:pre-wrap;margin-top:4px">{{ $reporte->observacion_revision }}</div>
            </div>
        </div>

        @include('cotizaciones.partials.errores')

        <form method="POST" action="{{ route('reportes_diarios.subsanar.update', $reporte) }}" enctype="multipart/form-data" class="pf-col">
            @csrf @method('PUT')

            @if($reporte->bloques->isNotEmpty())
            <section class="pf-card">
                <header class="pf-card__head">
                    <h2 class="pf-card__title"><x-pf-icon name="calendar" /> Bloques del horario</h2>
                </header>
                <div class="pf-card__body pf-list">
                    @foreach($reporte->bloques as $b)
                    @php
                        $old = old("bloques.{$b->id}", []);
                        $estado = $old['estado'] ?? $b->estado;
                    @endphp
                    <div class="pf-item" x-data="{ estado: @js($estado) }">
                        <div class="pf-row">
                            <span class="mono" style="color:var(--pf-sky);font-weight:800">{{ $b->inicio() }} – {{ $b->fin() }}</span>
                            <span class="pf-item__title">{{ $b->actividad }}</span>
                        </div>
                        <div class="pf-item__meta">{{ $b->project->name ?? 'Sin proyecto' }}@if($b->descripcion) · {{ $b->descripcion }}@endif</div>

                        <div class="pf-chips" style="margin-top:12px">
                            @foreach(['cumplido' => 'Cumplido', 'parcial' => 'Parcial', 'no_cumplido' => 'No cumplido'] as $valor => $texto)
                            <label style="cursor:pointer">
                                <input type="radio" name="bloques[{{ $b->id }}][estado]" value="{{ $valor }}" x-model="estado" class="sr-only" @checked($estado === $valor)>
                                <span class="pf-chip-btn pf-estado-{{ $valor }}" :class="estado === '{{ $valor }}' ? 'is-active' : ''">{{ $texto }}</span>
                            </label>
                            @endforeach
                        </div>

                        <div class="pf-form" style="margin-top:12px">
                            <label class="pf-field full" x-show="estado !== 'no_cumplido'" style="{{ $estado === 'no_cumplido' ? 'display:none' : '' }}">
                                <span class="pf-label">¿Qué hiciste en este bloque?</span>
                                <textarea name="bloques[{{ $b->id }}][detalle]" class="pf-input" rows="3" maxlength="3000">{{ $old['detalle'] ?? $b->detalle }}</textarea>
                                @error("bloques.{$b->id}.detalle")<span class="pf-error">{{ $message }}</span>@enderror
                            </label>
                            <label class="pf-field full" x-show="estado !== 'cumplido'" style="{{ $estado === 'cumplido' ? 'display:none' : '' }}">
                                <span class="pf-label">¿Por qué no se completó?</span>
                                <textarea name="bloques[{{ $b->id }}][justificacion]" class="pf-input" rows="2" maxlength="2000">{{ $old['justificacion'] ?? $b->justificacion }}</textarea>
                                @error("bloques.{$b->id}.justificacion")<span class="pf-error">{{ $message }}</span>@enderror
                            </label>
                            @if($b->archivos->isNotEmpty())
                            <div class="pf-field full">
                                <span class="pf-label">Evidencias ya enviadas</span>
                                <div class="pf-row">
                                    @foreach($b->archivos as $ev)
                                    <a href="{{ route('reportes_diarios.evidencia', $ev) }}" target="_blank" rel="noopener" class="pf-pill pf-pill--plain" title="{{ $ev->nombre }}">{{ \Illuminate\Support\Str::limit($ev->nombre, 28) }}</a>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                            <label class="pf-field full">
                                <span class="pf-label">Agregar evidencias</span>
                                <input type="file" name="bloques[{{ $b->id }}][archivos][]" class="pf-input" multiple
                                       accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt">
                                @error("bloques.{$b->id}.archivos.*")<span class="pf-error">{{ $message }}</span>@enderror
                            </label>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>
            @endif

            <section class="pf-card">
                <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="doc" /> Resumen del día</h2></header>
                <div class="pf-card__body pf-form">
                    <label class="pf-field full">
                        <span class="pf-label">Logros destacados</span>
                        <textarea name="logros_destacados" class="pf-input" rows="3" maxlength="2000">{{ old('logros_destacados', $reporte->logros_destacados) }}</textarea>
                    </label>
                    <label class="pf-field full">
                        <span class="pf-label">Plan para el día siguiente</span>
                        <textarea name="plan_siguiente_dia" class="pf-input" rows="2" maxlength="2000">{{ old('plan_siguiente_dia', $reporte->plan_siguiente_dia) }}</textarea>
                    </label>
                    <label class="pf-field full">
                        <span class="pf-label">Respuesta a gerencia: ¿qué corregiste?</span>
                        <textarea name="respuesta" class="pf-input" rows="3" required maxlength="3000"
                                  placeholder="Ej.: agregué las capturas del módulo y detallé el avance del bloque de la mañana.">{{ old('respuesta') }}</textarea>
                        @error('respuesta')<span class="pf-error">{{ $message }}</span>@enderror
                    </label>
                    <div class="pf-form__actions">
                        <a href="{{ route('reportes_diarios.show', $reporte) }}" class="pf-btn pf-btn--ghost">Cancelar</a>
                        <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="send" /> Enviar subsanación</button>
                    </div>
                </div>
            </section>
        </form>
    </div>
</x-app-layout>
