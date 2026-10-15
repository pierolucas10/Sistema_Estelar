{{-- Revisión de gerencia: estado, acciones (revisado / observado / rechazado), subsanación e historial --}}
@include('proyectos.partials.estilos')
@php
    $color = $reporte->estadoRevisionColor();
    $esAutor = $reporte->user_id === auth()->id();
@endphp

<section class="pf" x-data="{ accion: @js(old('accion')) }">
    <div class="pf-card" style="border-color: {{ ['amber' => 'rgba(251,191,36,.4)', 'red' => 'rgba(248,113,113,.4)', 'green' => 'rgba(52,211,153,.35)', 'violet' => 'rgba(167,139,250,.4)'][$color] ?? 'var(--pf-line)' }}">
        <header class="pf-card__head">
            <div>
                <h2 class="pf-card__title"><x-pf-icon name="check-circle" /> Revisión de gerencia</h2>
                <div class="pf-card__sub">
                    @if($reporte->revisado_at)
                        Última revisión: {{ $reporte->revisadoPor->name ?? '—' }} · {{ $reporte->revisado_at->format('d/m/Y H:i') }}
                    @else
                        Aún no ha sido revisado.
                    @endif
                    @if($reporte->subsanado_at) · subsanado el {{ $reporte->subsanado_at->format('d/m/Y H:i') }} @endif
                </div>
            </div>
            <span class="pf-pill pf-pill--{{ $color }}">{{ $reporte->estadoRevisionLabel() }}</span>
        </header>

        <div class="pf-card__body pf-gap">
            {{-- Observación / motivo vigente --}}
            @if($reporte->observacion_revision && in_array($reporte->estado_revision, ['observado', 'rechazado', 'subsanado']))
            <div class="pf-alert {{ $reporte->estado_revision === 'rechazado' ? 'pf-alert--danger' : 'pf-alert--warning' }}">
                <x-pf-icon name="alert" />
                <div>
                    <strong>{{ $reporte->estado_revision === 'rechazado' ? 'Motivo del rechazo' : 'Observación de gerencia' }}:</strong>
                    <div style="white-space:pre-wrap;margin-top:4px">{{ $reporte->observacion_revision }}</div>
                </div>
            </div>
            @endif

            {{-- El autor debe subsanar --}}
            @if($esAutor && $reporte->debeSubsanar())
            <div class="pf-between pf-box" style="border-color:rgba(251,191,36,.35)">
                <span>Gerencia observó tu reporte. Corrige lo indicado y envíalo de nuevo.</span>
                <a href="{{ route('reportes_diarios.subsanar', $reporte) }}" class="pf-btn pf-btn--primary"><x-pf-icon name="pencil" /> Subsanar reporte</a>
            </div>
            @endif

            {{-- Acciones de gerencia --}}
            @can('revisar', $reporte)
            <div>
                <div class="pf-section-title">{{ $reporte->porRevisar() ? 'Revisar este reporte' : 'Cambiar la revisión' }}</div>
                <div class="pf-row">
                    <form method="POST" action="{{ route('reportes_diarios.revisar', $reporte) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="accion" value="revisado">
                        <button type="submit" class="pf-btn pf-btn--success"><x-pf-icon name="check" /> Revisado</button>
                    </form>
                    <button type="button" class="pf-btn" style="color:#fbbf24;border-color:rgba(251,191,36,.35)" @click="accion = accion === 'observado' ? null : 'observado'"><x-pf-icon name="alert" /> Observado</button>
                    <button type="button" class="pf-btn pf-btn--danger" @click="accion = accion === 'rechazado' ? null : 'rechazado'"><x-pf-icon name="x" /> Rechazado</button>
                </div>
                <form method="POST" action="{{ route('reportes_diarios.revisar', $reporte) }}" class="pf-box pf-form" style="margin-top:12px;{{ old('accion') ? '' : 'display:none' }}" x-show="accion" x-transition>
                    @csrf @method('PATCH')
                    <input type="hidden" name="accion" :value="accion">
                    <label class="pf-field full">
                        <span class="pf-label" x-text="accion === 'rechazado' ? 'Motivo del rechazo' : '¿Qué debe corregir?'">¿Qué debe corregir?</span>
                        <textarea name="comentario" class="pf-input" rows="3" required maxlength="3000"
                                  placeholder="Ej.: falta evidencia del bloque de 08:30; detalla qué avanzaste en Lirio">{{ old('comentario') }}</textarea>
                        @error('comentario')<span class="pf-error">{{ $message }}</span>@enderror
                    </label>
                    <div class="pf-form__actions">
                        <button type="button" class="pf-btn pf-btn--ghost" @click="accion = null">Cancelar</button>
                        <button type="submit" class="pf-btn" :class="accion === 'rechazado' ? 'pf-btn--danger' : 'pf-btn--primary'"
                                x-text="accion === 'rechazado' ? 'Rechazar reporte' : 'Enviar observación'">Enviar</button>
                    </div>
                </form>
            </div>
            @endcan

            {{-- Historial --}}
            @if($reporte->revisiones->isNotEmpty())
            <div>
                <div class="pf-section-title">Historial</div>
                <div class="pf-timeline">
                    @foreach($reporte->revisiones as $rev)
                    <div class="pf-tl">
                        <div class="pf-tl__h">
                            <strong style="color:#e2e8f0">{{ $rev->accionLabel() }}</strong> · {{ $rev->user->name ?? '—' }} · {{ $rev->created_at->format('d/m/Y H:i') }}
                        </div>
                        @if($rev->comentario)<div class="pf-tl__t">{{ $rev->comentario }}</div>@endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
