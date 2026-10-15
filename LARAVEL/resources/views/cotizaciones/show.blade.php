<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('cotizaciones.index') }}" class="text-slate-600 hover:text-slate-400 font-mono">Cotizaciones</a>
            <span class="text-slate-700">/</span>
            <span class="text-white font-semibold">N° {{ $cotizacion->numero }}</span>
        </div>
    </x-slot>

    @include('proyectos.partials.estilos')

    @php
        $q = $cotizacion;
        $cli = $q->client;
        $dias = $q->diasParaVencer();
        $alertas = $q->alertas();
        $vinetas = $q->vinetas() ?: $q->items()->pluck('descripcion')->all();
        $puedeEditar = auth()->user()->can('cotizaciones.editar');
    @endphp

    <div class="pf">
        {{-- ── Cabecera ─────────────────────────────────────────── --}}
        <section class="pf-hero">
            <div class="pf-hero__top">
                <div class="pf-hero__id">
                    <div class="pf-avatar" style="font-size:13px">N°<br>{{ ltrim($q->numero, '0') ?: $q->numero }}</div>
                    <div style="min-width:0">
                        <div class="pf-row">
                            <h1 class="pf-title">Cotización N° {{ $q->numero }}</h1>
                            <span class="pf-pill {{ $q->estadoColor() ? 'pf-pill--'.$q->estadoColor() : '' }}">{{ $q->statusLabel() }}</span>
                        </div>
                        <div class="pf-sub">{{ $cli->numero_documento }} – {{ $cli->razon_social }}{{ $cli->nombre_comercial ? ' – ' . $cli->nombre_comercial : '' }}</div>
                        <p class="pf-desc">COTIZACIÓN DE {{ $q->titulo }} · Servicio de {{ $q->servicio }} · {{ $q->tipo_visita }}</p>
                    </div>
                </div>
                <div class="pf-actions">
                    @can('cotizaciones.pdf')
                    <a href="{{ route('cotizaciones.pdf', $q) }}" target="_blank" rel="noopener" class="pf-btn pf-btn--primary"><x-pf-icon name="eye" /> Ver PDF</a>
                    <a href="{{ route('cotizaciones.pdf', [$q, 'descargar' => 1]) }}" class="pf-btn"><x-pf-icon name="download" /> Descargar</a>
                    @endcan
                    @if($q->esEditable() && $puedeEditar)
                    <a href="{{ route('cotizaciones.edit', $q) }}" class="pf-btn"><x-pf-icon name="pencil" /> Editar</a>
                    @endif
                    @can('cotizaciones.crear')
                    <a href="{{ route('cotizaciones.create', ['duplicar' => $q->id]) }}" class="pf-btn" title="Crear una nueva con el mismo contenido"><x-pf-icon name="copy" /> Duplicar</a>
                    @endcan
                </div>
            </div>
            <div class="pf-meta">
                <div class="pf-meta__item"><span class="pf-meta__label">{{ $q->precio_etiqueta ?: 'Precio' }}</span><span class="pf-meta__value mono" style="font-size:18px">{{ $q->montoFormateado() }}</span></div>
                <div class="pf-meta__item"><span class="pf-meta__label">Emisión</span><span class="pf-meta__value mono">{{ $q->fecha_emision?->format('d/m/Y') }}</span></div>
                <div class="pf-meta__item">
                    <span class="pf-meta__label">Vence ({{ $q->vigencia_dias }} días)</span>
                    <span class="pf-meta__value mono {{ $dias !== null && $dias <= 0 ? 'is-late' : '' }}">
                        {{ $q->fecha_vencimiento?->format('d/m/Y') ?? '—' }}
                        @if($dias !== null) · {{ $dias < 0 ? 'venció hace ' . abs($dias) . ' d' : ($dias === 0 ? 'hoy' : "en {$dias} d") }} @endif
                    </span>
                </div>
                <div class="pf-meta__item"><span class="pf-meta__label">Próximo seguimiento</span><span class="pf-meta__value mono {{ $q->seguimientoAtrasado() ? 'is-late' : '' }}">{{ $q->estaAbierta() ? ($q->proximo_seguimiento?->format('d/m/Y') ?? '—') : '—' }}</span></div>
                <div class="pf-meta__item"><span class="pf-meta__label">Elaborada por</span><span class="pf-meta__value">{{ $q->createdBy->name ?? '—' }}</span></div>
            </div>
        </section>

        @include('cotizaciones.partials.errores')

        @if($q->estaConvertida())
        <div class="pf-alert pf-alert--ok" style="margin-bottom:18px">
            <x-pf-icon name="check-circle" />
            <div class="pf-between" style="flex:1">
                <span>Convertida en contrato el {{ ($q->convertida_at ?? $q->accepted_at)?->format('d/m/Y') }}. Los pagos, el contrato y el desarrollo se gestionan en el proyecto.</span>
                @if($q->project)<a href="{{ route('proyectos.show', $q->project) }}" class="pf-btn pf-btn--success pf-btn--sm"><x-pf-icon name="external" /> Ir al proyecto</a>@endif
            </div>
        </div>
        @elseif($q->status === 'rechazado')
        <div class="pf-alert pf-alert--danger" style="margin-bottom:18px">
            <x-pf-icon name="x" />
            <div class="pf-between" style="flex:1">
                <span>Rechazada{{ $q->motivo_rechazo ? ': «' . $q->motivo_rechazo . '»' : '' }}.</span>
                @if($puedeEditar)
                <form method="POST" action="{{ route('cotizaciones.reabrir', $q) }}">@csrf @method('PATCH')
                    <button type="submit" class="pf-btn pf-btn--sm"><x-pf-icon name="refresh" /> Reabrir</button>
                </form>
                @endif
            </div>
        </div>
        @endif

        <div class="pf-grid">
            <div class="pf-col">

                @if($alertas)
                    @include('proyectos.partials.alertas', ['alertas' => $alertas])
                @endif

                {{-- ── Cerrar la venta ─────────────────────────── --}}
                @if($q->estaAbierta() && ($puedeEditar || auth()->user()->can('cotizaciones.aprobar')))
                <section class="pf-card" x-data="{ panel: @js(old('nombre') ? 'convertir' : null) }">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="flag" /> ¿Qué dijo el cliente?</h2>
                            <div class="pf-card__sub">Si acepta, conviértela en contrato: se crea el proyecto con el monto y los requerimientos.</div>
                        </div>
                    </header>
                    <div class="pf-card__body">
                        <div class="pf-row">
                            @can('cotizaciones.aprobar')
                            <button type="button" class="pf-btn pf-btn--success" @click="panel = panel === 'convertir' ? null : 'convertir'"><x-pf-icon name="briefcase" /> Aceptó: convertir en contrato</button>
                            @endcan
                            @if($puedeEditar)
                                @if($q->status === 'borrador')
                                <form method="POST" action="{{ route('cotizaciones.enviar', $q) }}">@csrf @method('PATCH')
                                    <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="send" /> Marcar como enviada</button>
                                </form>
                                @endif
                                <button type="button" class="pf-btn" @click="panel = panel === 'renovar' ? null : 'renovar'"><x-pf-icon name="refresh" /> Renovar vigencia</button>
                                <button type="button" class="pf-btn pf-btn--danger" @click="panel = panel === 'rechazar' ? null : 'rechazar'"><x-pf-icon name="x" /> No aceptó</button>
                            @endif
                        </div>

                        @can('cotizaciones.aprobar')
                        <form method="POST" action="{{ route('cotizaciones.convertir', $q) }}" class="pf-box pf-form" style="margin-top:14px;{{ old('nombre') ? '' : 'display:none' }}" x-show="panel === 'convertir'" x-transition>
                            @csrf
                            <label class="pf-field full">
                                <span class="pf-label">Nombre del proyecto</span>
                                <input type="text" name="nombre" class="pf-input" required maxlength="200"
                                       value="{{ old('nombre', \Illuminate\Support\Str::title(mb_strtolower($q->titulo)) . ' – ' . ($cli->nombre_comercial ?: $cli->razon_social)) }}">
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Responsable</span>
                                <select name="responsible_user_id" class="pf-input">
                                    <option value="">Sin asignar</option>
                                    @foreach($usuarios as $u)<option value="{{ $u->id }}" @selected(old('responsible_user_id') == $u->id)>{{ $u->name }}</option>@endforeach
                                </select>
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Monto del contrato</span>
                                <input type="text" class="pf-input mono" value="{{ $q->montoFormateado() }}" disabled>
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Inicio</span>
                                <input type="date" name="start_date" class="pf-input" value="{{ old('start_date', today()->toDateString()) }}">
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Entrega pactada</span>
                                <input type="date" name="end_date" class="pf-input" value="{{ old('end_date') }}">
                            </label>
                            <label class="pf-row full" style="gap:8px;cursor:pointer">
                                <input type="hidden" name="crear_requerimientos" value="0">
                                <input type="checkbox" name="crear_requerimientos" value="1" checked style="accent-color:#10b981;width:16px;height:16px">
                                <span class="pf-hint">Crear {{ count($vinetas) }} requerimiento(s) con los puntos de la descripción para el equipo de desarrollo.</span>
                            </label>
                            <div class="full pf-alert pf-alert--info">
                                <x-pf-icon name="cash" />
                                <span>Se crearán {{ count($q->planPago()) }} cuota(s) en el proyecto:
                                    {{ collect($q->planPago())->map(fn ($p) => $p['concepto'] . ' ' . $q->monedaSimbolo() . ' ' . number_format($p['monto'], 2))->implode(' · ') }}.
                                    La primera vence en la fecha de inicio y la última en la entrega.</span>
                            </div>
                            <div class="pf-form__actions">
                                <button type="button" class="pf-btn pf-btn--ghost" @click="panel = null">Cancelar</button>
                                <button type="submit" class="pf-btn pf-btn--success"><x-pf-icon name="check" /> Crear contrato y proyecto</button>
                            </div>
                        </form>
                        @endcan

                        @if($puedeEditar)
                        <form method="POST" action="{{ route('cotizaciones.renovar', $q) }}" class="pf-box pf-row" style="margin-top:14px;display:none" x-show="panel === 'renovar'" x-transition>
                            @csrf @method('PATCH')
                            <span class="pf-hint">Nueva emisión hoy, válida por</span>
                            <input type="number" name="vigencia_dias" class="pf-input mono" min="1" max="365" value="{{ $q->vigencia_dias ?: 5 }}" style="width:90px">
                            <span class="pf-hint">días.</span>
                            <button type="submit" class="pf-btn pf-btn--primary" style="margin-left:auto"><x-pf-icon name="refresh" /> Renovar</button>
                        </form>

                        <form method="POST" action="{{ route('cotizaciones.rechazar', $q) }}" class="pf-box pf-row" style="margin-top:14px;display:none" x-show="panel === 'rechazar'" x-transition>
                            @csrf @method('PATCH')
                            <input type="text" name="motivo_rechazo" class="pf-input" required maxlength="255" style="flex:1;min-width:220px"
                                   placeholder="Motivo: precio alto, eligió a otro proveedor, lo deja para después…">
                            <button type="submit" class="pf-btn pf-btn--danger">Marcar como rechazada</button>
                        </form>
                        @endif
                    </div>
                </section>
                @endif

                {{-- ── Contenido (vista previa) ────────────────── --}}
                <section class="pf-card">
                    <header class="pf-card__head">
                        <div>
                            <h2 class="pf-card__title"><x-pf-icon name="doc" /> Servicio de {{ $q->servicio }}</h2>
                            <div class="pf-card__sub">Propuesta de servicio · {{ $q->modalidad }}</div>
                        </div>
                    </header>
                    <div class="pf-card__body">
                        <div class="pf-tiles" style="margin-bottom:14px">
                            <div class="pf-kpi"><div class="pf-kpi__label">Servicio</div><div style="font-weight:800;color:#fff;margin-top:6px">{{ $q->servicio }}</div></div>
                            <div class="pf-kpi"><div class="pf-kpi__label">S/N</div><div class="mono" style="font-weight:800;color:#fff;margin-top:6px">{{ $q->numero_serie ?: '—' }}</div></div>
                            <div class="pf-kpi"><div class="pf-kpi__label">Tipo de visita</div><div style="font-weight:800;color:#fff;margin-top:6px">{{ $q->tipo_visita }}</div></div>
                            <div class="pf-kpi"><div class="pf-kpi__label">{{ $q->precio_etiqueta }}</div><div class="pf-kpi__value is-green">{{ $q->montoFormateado() }}</div><div class="pf-hint">{{ $q->incluye_igv ? 'Incluye IGV' : 'No incluye IGV' }}</div></div>
                        </div>
                        <div class="pf-section-title">Forma de pago</div>
                        <div class="pf-files" style="margin-bottom:16px">
                            @foreach($q->planPago() as $parte)
                            <div class="pf-file">
                                <span class="pf-file__ext" style="width:auto;padding:0 10px">{{ rtrim(rtrim(number_format($parte['porcentaje'], 2), '0'), '.') }}%</span>
                                <div class="pf-file__info">
                                    <span class="pf-file__name" style="white-space:normal">{{ $parte['concepto'] }}</span>
                                    <div class="pf-file__meta mono">{{ $q->monedaSimbolo() }} {{ number_format($parte['monto'], 2) }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="pf-section-title">Descripción del sistema web · {{ count($vinetas) }} puntos</div>
                        <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:7px;color:#cbd5e1;font-size:13px;line-height:1.55">
                            @foreach($vinetas as $v)<li>{{ $v }}</li>@endforeach
                        </ul>
                    </div>
                </section>

                @unless($q->estaConvertida())
                @can('cotizaciones.eliminar')
                <form method="POST" action="{{ route('cotizaciones.destroy', $q) }}" x-data
                      @submit.prevent="if (confirm('¿Eliminar la cotización N° {{ $q->numero }}?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="pf-btn pf-btn--danger pf-btn--sm"><x-pf-icon name="trash" /> Eliminar cotización</button>
                </form>
                @endcan
                @endunless
            </div>

            {{-- ── Columna lateral: seguimiento ───────────────────── --}}
            <aside class="pf-col">
                <section class="pf-card">
                    <header class="pf-card__head">
                        <h2 class="pf-card__title"><x-pf-icon name="chat" /> Seguimiento</h2>
                        <span class="pf-pill pf-pill--plain">{{ $q->seguimientos->count() }}</span>
                    </header>
                    @if($q->estaAbierta() && $puedeEditar)
                    <div class="pf-card__body">
                        <form method="POST" action="{{ route('cotizaciones.seguimiento', $q) }}" class="pf-form">
                            @csrf
                            <label class="pf-field">
                                <span class="pf-label">Contacto por</span>
                                <select name="tipo" class="pf-input">
                                    @foreach(\App\Models\QuoteFollowup::TIPOS as $clave => $etiqueta)<option value="{{ $clave }}">{{ $etiqueta }}</option>@endforeach
                                </select>
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Próximo</span>
                                <input type="date" name="proximo_seguimiento" class="pf-input" min="{{ today()->toDateString() }}" value="{{ today()->addDays(2)->toDateString() }}">
                            </label>
                            <label class="pf-field full">
                                <span class="pf-label">¿Qué pasó?</span>
                                <textarea name="nota" class="pf-input" rows="3" required maxlength="2000" placeholder="Ej.: Lo revisará con su socio, llamar el jueves."></textarea>
                            </label>
                            <div class="pf-form__actions"><button type="submit" class="pf-btn pf-btn--primary pf-btn--block"><x-pf-icon name="plus" /> Registrar seguimiento</button></div>
                        </form>
                    </div>
                    @endif
                    <div class="pf-card__body">
                        @forelse($q->seguimientos as $s)
                        <div class="pf-tl">
                            <div class="pf-tl__h"><strong style="color:#e2e8f0">{{ $s->tipoLabel() }}</strong> · {{ $s->created_at->format('d/m/Y H:i') }} · {{ \Illuminate\Support\Str::before($s->user->name ?? '—', ' ') }}</div>
                            <div class="pf-tl__t">{{ $s->nota }}</div>
                            @if($s->proximo_seguimiento)<div class="pf-tl__h" style="margin-top:4px">Próximo: {{ $s->proximo_seguimiento->format('d/m/Y') }}</div>@endif
                        </div>
                        @empty
                        <div class="pf-empty" style="padding:12px"><x-pf-icon name="chat" />Aún no hay seguimientos.</div>
                        @endforelse
                    </div>
                </section>

                <section class="pf-card">
                    <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="info" /> Datos</h2></header>
                    <div class="pf-card__body">
                        <div class="pf-kv">
                            <div class="pf-kv__row"><span class="pf-kv__k">Creada</span><span class="pf-kv__v mono">{{ $q->created_at?->format('d/m/Y H:i') }}</span></div>
                            <div class="pf-kv__row"><span class="pf-kv__k">Enviada</span><span class="pf-kv__v mono">{{ $q->sent_at?->format('d/m/Y') ?? '—' }}</span></div>
                            <div class="pf-kv__row"><span class="pf-kv__k">Moneda</span><span class="pf-kv__v">{{ $q->moneda === 'USD' ? 'Dólares' : 'Soles' }}</span></div>
                            <div class="pf-kv__row"><span class="pf-kv__k">Términos</span><span class="pf-kv__v">{{ filled($q->terminos) ? 'Personalizados' : 'Modelo estándar' }}</span></div>
                        </div>
                        @if($q->notas)
                            <div class="pf-item__label">Notas internas</div>
                            <div class="pf-item__text" style="margin-top:4px">{{ $q->notas }}</div>
                        @endif
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
