{{-- Vista del rol administrativo: pagos, cuotas, vouchers, contrato y archivos --}}
@php
    $s          = $proyecto->monedaSimbolo();
    $pct        = $proyecto->porcentajePagado();
    $pagadas    = $proyecto->cuotas->where('estado', 'pagada')->count();
    $metodos    = \App\Models\CashMovement::METODOS_PAGO;
    $contratos  = $proyecto->archivos->where('categoria', 'contrato');
    $otros      = $proyecto->archivos->where('categoria', '!=', 'contrato');
    $ringColor  = $pct >= 100 ? '#10b981' : ($pct >= 50 ? '#0ea5e9' : '#f59e0b');
    $money      = fn ($v) => $s . ' ' . number_format((float) $v, 2);
@endphp

<div class="pf-grid">
    <div class="pf-col">

        {{-- ── Pagos ──────────────────────────────────────────── --}}
        <section class="pf-card" id="pagos" x-data="{ nueva: false }">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="cash" /> Pagos del proyecto</h2>
                    <div class="pf-card__sub">
                        {{ $proyecto->cuotas->count() ? "{$pagadas} de {$proyecto->cuotas->count()} cuotas pagadas" : 'Aún no hay cuotas programadas' }}
                    </div>
                </div>
                <button type="button" class="pf-btn pf-btn--primary pf-btn--sm" @click="nueva = !nueva">
                    <x-pf-icon name="plus" /> <span x-text="nueva ? 'Cerrar' : 'Nueva cuota'">Nueva cuota</span>
                </button>
            </header>

            <div class="pf-card__body">
                <div class="pf-kpis">
                    <div class="pf-ring" style="--p: {{ $pct }}; --c: {{ $ringColor }}">
                        <div class="pf-ring__inner">
                            <div class="pf-ring__value">{{ $pct }}%</div>
                            <div class="pf-ring__label">pagado</div>
                        </div>
                    </div>
                    <div class="pf-kpi">
                        <div class="pf-kpi__label">Total contrato</div>
                        <div class="pf-kpi__value">{{ $proyecto->montoBase() > 0 ? $money($proyecto->montoBase()) : '—' }}</div>
                    </div>
                    <div class="pf-kpi">
                        <div class="pf-kpi__label">Cobrado</div>
                        <div class="pf-kpi__value is-green">{{ $money($proyecto->montoPagado()) }}</div>
                    </div>
                    <div class="pf-kpi">
                        <div class="pf-kpi__label">Por cobrar</div>
                        <div class="pf-kpi__value is-amber">{{ $money($proyecto->montoPendiente()) }}</div>
                    </div>
                </div>

                @php $alertasFin = $proyecto->alertasFinanzas(); @endphp
                @if($alertasFin)
                <div style="margin-top:16px">
                    @include('proyectos.partials.alertas', ['alertas' => $alertasFin])
                </div>
                @endif
            </div>

            {{-- Nueva cuota --}}
            <div class="pf-card__body" x-show="nueva" x-transition style="display:none">
                <form method="POST" action="{{ route('proyectos.cuotas.store', $proyecto) }}" class="pf-form pf-form--3">
                    @csrf
                    <label class="pf-field">
                        <span class="pf-label">Concepto</span>
                        <input type="text" name="nombre" class="pf-input" required maxlength="150"
                               value="{{ old('nombre', 'Cuota ' . ($proyecto->cuotas->count() + 1)) }}">
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Monto ({{ $s }})</span>
                        <input type="number" name="monto" class="pf-input mono" step="0.01" min="0.01" required
                               value="{{ old('monto', $proyecto->monto_contrato ? number_format(max(0, (float) $proyecto->monto_contrato - $proyecto->montoProgramado()), 2, '.', '') : '') }}">
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Vence el</span>
                        <input type="date" name="fecha_vencimiento" class="pf-input" value="{{ old('fecha_vencimiento') }}">
                    </label>
                    <label class="pf-field full">
                        <span class="pf-label">Notas (opcional)</span>
                        <input type="text" name="notas" class="pf-input" maxlength="1000" placeholder="Ej.: se paga al aprobar los diseños">
                    </label>
                    <div class="pf-form__actions">
                        <button type="button" class="pf-btn pf-btn--ghost" @click="nueva = false">Cancelar</button>
                        <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="plus" /> Agregar cuota</button>
                    </div>
                </form>
            </div>

            {{-- Lista de cuotas --}}
            @if($proyecto->cuotas->isEmpty())
            <div class="pf-card__body">
                <div class="pf-box pf-box--dashed">
                    <div class="pf-between" style="margin-bottom:12px">
                        <div>
                            <div style="font-weight:800;color:#fff">Generar plan de pagos</div>
                            <div class="pf-hint">Reparte el total del contrato en cuotas automáticamente. Luego puedes editar cada una.</div>
                        </div>
                    </div>
                    @if($proyecto->monto_contrato)
                    <form method="POST" action="{{ route('proyectos.cuotas.plan', $proyecto) }}" class="pf-form pf-form--3"
                          x-data="{ esquema: '50-50' }">
                        @csrf
                        <label class="pf-field">
                            <span class="pf-label">Esquema</span>
                            <select name="esquema" class="pf-input" x-model="esquema">
                                <option value="50-50">50% adelanto + 50% al final</option>
                                <option value="40-30-30">40% · 30% · 30%</option>
                                <option value="iguales">Cuotas iguales</option>
                            </select>
                        </label>
                        <label class="pf-field" x-show="esquema === 'iguales'" style="display:none">
                            <span class="pf-label">Número de cuotas</span>
                            <input type="number" name="cantidad" class="pf-input" min="2" max="24" value="3">
                        </label>
                        <label class="pf-field">
                            <span class="pf-label">Primera cuota vence</span>
                            <input type="date" name="primera_fecha" class="pf-input" value="{{ today()->toDateString() }}">
                        </label>
                        <label class="pf-field">
                            <span class="pf-label">Siguientes cada (días)</span>
                            <input type="number" name="cada_dias" class="pf-input" min="1" max="365" value="30">
                        </label>
                        <div class="pf-form__actions">
                            <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="sparkles" /> Generar cuotas de {{ $money($proyecto->monto_contrato) }}</button>
                        </div>
                    </form>
                    @else
                    <div class="pf-alert pf-alert--info">
                        <x-pf-icon name="info" />
                        <span>Primero escribe el <strong>monto del contrato</strong> en el panel de la derecha, o agrega las cuotas una por una con «Nueva cuota».</span>
                    </div>
                    @endif
                </div>
            </div>
            @else
            <div class="pf-cuotas" style="border-top:1px solid var(--pf-line)">
                @foreach($proyecto->cuotas as $cuota)
                @php
                    $ev = $cuota->estadoVisual();
                    $pill = ['pagada' => 'green', 'vencida' => 'red', 'por_vencer' => 'amber'][$ev] ?? 'sky';
                    // Tras un error de validación, se reabre el panel que se estaba usando.
                    $abrir = (string) old('_cuota') === (string) $cuota->id && in_array(old('_panel'), ['pagar', 'editar'], true)
                        ? old('_panel') : null;
                @endphp
                <div class="pf-cuota is-{{ $ev }}" x-data="{ panel: @js($abrir) }">
                    <div class="pf-cuota__row">
                        <span class="pf-cuota__n">
                            @if($cuota->pagada()) <x-pf-icon name="check" style="width:14px;height:14px" /> @else {{ $loop->iteration }} @endif
                        </span>
                        <div style="min-width:0">
                            <div class="pf-cuota__nombre">{{ $cuota->nombre }}</div>
                            <div class="pf-cuota__det">
                                @if($cuota->pagada())
                                    Pagada el {{ $cuota->fecha_pago?->format('d/m/Y') }} · {{ $cuota->metodoLabel() }}
                                    @if($cuota->referencia) · Op. {{ $cuota->referencia }} @endif
                                @elseif($cuota->fecha_vencimiento)
                                    Vence el {{ $cuota->fecha_vencimiento->format('d/m/Y') }}
                                @else
                                    Sin fecha de vencimiento
                                @endif
                                @if($cuota->notas) · {{ \Illuminate\Support\Str::limit($cuota->notas, 60) }} @endif
                            </div>
                        </div>
                        <div class="pf-cuota__monto">{{ $money($cuota->monto) }}</div>
                        <div class="pf-cuota__estado"><span class="pf-pill pf-pill--{{ $pill }}">{{ $cuota->estadoLabel() }}</span></div>
                        <div class="pf-cuota__acciones">
                            @if($cuota->pagada())
                                @if($cuota->voucher_path)
                                <a href="{{ route('proyectos.cuotas.voucher.ver', [$proyecto, $cuota]) }}" target="_blank" rel="noopener" class="pf-btn pf-btn--sm">
                                    <x-pf-icon name="eye" /> Voucher
                                </a>
                                @else
                                <button type="button" class="pf-btn pf-btn--sm" @click="panel = panel === 'voucher' ? null : 'voucher'">
                                    <x-pf-icon name="upload" /> Subir voucher
                                </button>
                                @endif
                                <form method="POST" action="{{ route('proyectos.cuotas.revertir', [$proyecto, $cuota]) }}"
                                      x-data @submit.prevent="if (confirm('¿Revertir el pago? La cuota volverá a pendiente, se quitará de Caja y se borrará el voucher.')) $el.submit()">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="pf-btn pf-btn--ghost pf-btn--sm" title="Revertir pago"><x-pf-icon name="undo" /></button>
                                </form>
                            @else
                                <button type="button" class="pf-btn pf-btn--success pf-btn--sm" @click="panel = panel === 'pagar' ? null : 'pagar'">
                                    <x-pf-icon name="check" /> Registrar pago
                                </button>
                            @endif
                            <button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" title="Editar cuota" @click="panel = panel === 'editar' ? null : 'editar'">
                                <x-pf-icon name="pencil" />
                            </button>
                            @unless($cuota->pagada())
                            <form method="POST" action="{{ route('proyectos.cuotas.destroy', [$proyecto, $cuota]) }}"
                                  x-data @submit.prevent="if (confirm('¿Eliminar la cuota «{{ addslashes($cuota->nombre) }}»?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button type="submit" class="pf-btn pf-btn--ghost pf-btn--sm" title="Eliminar cuota"><x-pf-icon name="trash" style="color:#f87171" /></button>
                            </form>
                            @endunless
                        </div>
                    </div>

                    {{-- Registrar pago --}}
                    @unless($cuota->pagada())
                    <div class="pf-cuota__panel" x-show="panel === 'pagar'" x-transition style="display:none">
                        <form method="POST" action="{{ route('proyectos.cuotas.pagar', [$proyecto, $cuota]) }}" enctype="multipart/form-data" class="pf-box pf-form pf-form--3">
                            @csrf
                            <input type="hidden" name="_cuota" value="{{ $cuota->id }}">
                            <input type="hidden" name="_panel" value="pagar">
                            <label class="pf-field">
                                <span class="pf-label">Fecha de pago</span>
                                <input type="date" name="fecha_pago" class="pf-input" required max="{{ today()->toDateString() }}" value="{{ old('fecha_pago', today()->toDateString()) }}">
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Método</span>
                                <select name="metodo_pago" class="pf-input" required>
                                    @foreach($metodos as $clave => $etiqueta)
                                        <option value="{{ $clave }}" @selected(old('metodo_pago', 'transferencia') === $clave)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">N.º de operación</span>
                                <input type="text" name="referencia" class="pf-input mono" maxlength="120" value="{{ old('referencia') }}" placeholder="Opcional">
                            </label>
                            <label class="pf-field full">
                                <span class="pf-label">Voucher / comprobante</span>
                                <input type="file" name="voucher" class="pf-input" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                <span class="pf-hint">Imagen o PDF, máx. 10 MB. Se puede subir después.</span>
                            </label>
                            <label class="pf-field full">
                                <span class="pf-label">Notas</span>
                                <input type="text" name="notas" class="pf-input" maxlength="1000" value="{{ old('notas') }}" placeholder="Opcional">
                            </label>
                            <div class="pf-form__actions">
                                <span class="pf-hint" style="margin-right:auto;align-self:center">Se registrará como ingreso en Caja.</span>
                                <button type="button" class="pf-btn pf-btn--ghost" @click="panel = null">Cancelar</button>
                                <button type="submit" class="pf-btn pf-btn--success"><x-pf-icon name="check" /> Confirmar pago de {{ $money($cuota->monto) }}</button>
                            </div>
                        </form>
                    </div>
                    @endunless

                    {{-- Subir voucher a una cuota ya pagada --}}
                    @if($cuota->pagada() && !$cuota->voucher_path)
                    <div class="pf-cuota__panel" x-show="panel === 'voucher'" x-transition style="display:none">
                        <form method="POST" action="{{ route('proyectos.cuotas.voucher', [$proyecto, $cuota]) }}" enctype="multipart/form-data" class="pf-box pf-row">
                            @csrf
                            <input type="file" name="voucher" class="pf-input" accept=".jpg,.jpeg,.png,.webp,.pdf" required style="flex:1;min-width:200px">
                            <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="upload" /> Guardar voucher</button>
                        </form>
                    </div>
                    @endif

                    {{-- Editar cuota --}}
                    <div class="pf-cuota__panel" x-show="panel === 'editar'" x-transition style="display:none">
                        <form method="POST" action="{{ route('proyectos.cuotas.update', [$proyecto, $cuota]) }}" class="pf-box pf-form pf-form--3">
                            @csrf @method('PATCH')
                            <input type="hidden" name="_cuota" value="{{ $cuota->id }}">
                            <input type="hidden" name="_panel" value="editar">
                            <label class="pf-field">
                                <span class="pf-label">Concepto</span>
                                <input type="text" name="nombre" class="pf-input" required maxlength="150" value="{{ $cuota->nombre }}">
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Monto ({{ $s }})</span>
                                <input type="number" name="monto" class="pf-input mono" step="0.01" min="0.01" required value="{{ $cuota->monto }}"
                                       @if($cuota->pagada()) readonly title="Revierte el pago para cambiar el monto" @endif>
                            </label>
                            <label class="pf-field">
                                <span class="pf-label">Vence el</span>
                                <input type="date" name="fecha_vencimiento" class="pf-input" value="{{ $cuota->fecha_vencimiento?->toDateString() }}">
                            </label>
                            <label class="pf-field full">
                                <span class="pf-label">Notas</span>
                                <input type="text" name="notas" class="pf-input" maxlength="1000" value="{{ $cuota->notas }}">
                            </label>
                            <div class="pf-form__actions">
                                <button type="button" class="pf-btn pf-btn--ghost" @click="panel = null">Cancelar</button>
                                <button type="submit" class="pf-btn pf-btn--primary">Guardar cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </section>

        {{-- ── Contrato y archivos ────────────────────────────── --}}
        <section class="pf-card" id="archivos">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="contract" /> Contrato</h2>
                    <div class="pf-card__sub">Contrato firmado, adendas y anexos. Solo lo ve administración.</div>
                </div>
                @if($contratos->isNotEmpty())
                    <span class="pf-pill pf-pill--green">Subido</span>
                @else
                    <span class="pf-pill pf-pill--amber">Pendiente</span>
                @endif
            </header>
            <div class="pf-card__body pf-gap">
                @if($contratos->isNotEmpty())
                <div class="pf-files">
                    @foreach($contratos as $archivo)
                        @include('proyectos.partials.archivo', ['puedeBorrar' => true, 'vista' => 'finanzas'])
                    @endforeach
                </div>
                @endif
                <form method="POST" action="{{ route('proyectos.archivos.store', $proyecto) }}" enctype="multipart/form-data" class="pf-box pf-box--dashed pf-form">
                    @csrf
                    <input type="hidden" name="categoria" value="contrato">
                    <input type="hidden" name="vista" value="finanzas">
                    <label class="pf-field">
                        <span class="pf-label">{{ $contratos->isEmpty() ? 'Subir contrato' : 'Agregar adenda o anexo' }}</span>
                        <input type="file" name="archivos[]" class="pf-input" multiple required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Descripción (opcional)</span>
                        <input type="text" name="descripcion" class="pf-input" maxlength="255" placeholder="Ej.: contrato firmado por ambas partes">
                    </label>
                    <div class="pf-form__actions">
                        <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="upload" /> Subir</button>
                    </div>
                </form>
            </div>

            <header class="pf-card__head" style="border-top:1px solid var(--pf-line)">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="folder" /> Archivos del proyecto</h2>
                    <div class="pf-card__sub">Documentos adicionales, actas y documentación del equipo de desarrollo.</div>
                </div>
                <span class="pf-pill pf-pill--plain">{{ $otros->count() }}</span>
            </header>
            <div class="pf-card__body pf-gap">
                @if($otros->isNotEmpty())
                <div class="pf-files">
                    @foreach($otros as $archivo)
                        @include('proyectos.partials.archivo', ['puedeBorrar' => $archivo->categoria === 'otro', 'vista' => 'finanzas'])
                    @endforeach
                </div>
                @else
                <div class="pf-empty"><x-pf-icon name="folder" />Todavía no hay archivos.</div>
                @endif

                <form method="POST" action="{{ route('proyectos.archivos.store', $proyecto) }}" enctype="multipart/form-data" class="pf-box pf-box--dashed pf-form">
                    @csrf
                    <input type="hidden" name="categoria" value="otro">
                    <input type="hidden" name="vista" value="finanzas">
                    <label class="pf-field">
                        <span class="pf-label">Agregar archivos</span>
                        <input type="file" name="archivos[]" class="pf-input" multiple required>
                        <span class="pf-hint">Hasta 10 archivos de 20 MB.</span>
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Descripción (opcional)</span>
                        <input type="text" name="descripcion" class="pf-input" maxlength="255" placeholder="Ej.: factura, orden de compra, logo…">
                    </label>
                    <div class="pf-form__actions">
                        <button type="submit" class="pf-btn"><x-pf-icon name="upload" /> Subir archivos</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    {{-- ── Columna lateral ────────────────────────────────────── --}}
    <aside class="pf-col">
        <section class="pf-card" x-data="{ monto: '{{ $proyecto->monto_contrato }}' }">
            <header class="pf-card__head">
                <h2 class="pf-card__title"><x-pf-icon name="contract" /> Datos del contrato</h2>
            </header>
            <div class="pf-card__body">
                <form method="POST" action="{{ route('proyectos.contrato.update', $proyecto) }}" class="pf-form">
                    @csrf @method('PATCH')
                    <label class="pf-field">
                        <span class="pf-label">Monto total</span>
                        <input type="number" name="monto_contrato" class="pf-input mono" step="0.01" min="0" x-model="monto" value="{{ $proyecto->monto_contrato }}" placeholder="0.00">
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Moneda</span>
                        <select name="moneda" class="pf-input">
                            <option value="PEN" @selected($proyecto->moneda !== 'USD')>Soles (S/)</option>
                            <option value="USD" @selected($proyecto->moneda === 'USD')>Dólares ($)</option>
                        </select>
                    </label>
                    @if($proyecto->quote)
                    <div class="full pf-box" style="padding:10px 12px">
                        <div class="pf-between">
                            <span class="pf-hint">Cotización {{ $proyecto->quote->numero }}:
                                <strong class="mono" style="color:#e2e8f0">{{ $proyecto->quote->monedaSimbolo() }} {{ number_format((float) $proyecto->quote->total, 2) }}</strong>
                            </span>
                            <button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" @click="monto = '{{ $proyecto->quote->total }}'">Usar</button>
                        </div>
                    </div>
                    @endif
                    <div class="pf-form__actions">
                        <button type="submit" class="pf-btn pf-btn--primary pf-btn--block">Guardar</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="pf-card">
            <header class="pf-card__head">
                <h2 class="pf-card__title"><x-pf-icon name="info" /> Información</h2>
            </header>
            <div class="pf-card__body">
                <div class="pf-kv">
                    <div class="pf-kv__row"><span class="pf-kv__k">Cliente</span><span class="pf-kv__v">{{ $proyecto->client->razon_social ?? '—' }}</span></div>
                    @if($proyecto->client?->numero_documento)
                    <div class="pf-kv__row"><span class="pf-kv__k">Documento</span><span class="pf-kv__v mono">{{ $proyecto->client->numero_documento }}</span></div>
                    @endif
                    <div class="pf-kv__row"><span class="pf-kv__k">Estado</span><span class="pf-kv__v">{{ $proyecto->statusLabel() }}</span></div>
                    <div class="pf-kv__row"><span class="pf-kv__k">Responsable</span><span class="pf-kv__v">{{ $proyecto->responsible->name ?? 'Sin asignar' }}</span></div>
                    <div class="pf-kv__row"><span class="pf-kv__k">Avance dev.</span><span class="pf-kv__v mono">{{ $proyecto->progress }}%</span></div>
                    <div class="pf-kv__row"><span class="pf-kv__k">Requerimientos</span><span class="pf-kv__v">{{ $proyecto->requirements->where('status', 'completado')->count() }} / {{ $proyecto->requirements->count() }} listos</span></div>
                    <div class="pf-kv__row"><span class="pf-kv__k">Creado</span><span class="pf-kv__v mono">{{ $proyecto->created_at?->format('d/m/Y') }}</span></div>
                </div>
            </div>
        </section>

        @can('proyectos.eliminar')
        <section class="pf-card">
            <div class="pf-card__body">
                <form method="POST" action="{{ route('proyectos.destroy', $proyecto) }}"
                      x-data @submit.prevent="if (confirm('¿Eliminar el proyecto «{{ addslashes($proyecto->name) }}»?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="pf-btn pf-btn--danger pf-btn--block"><x-pf-icon name="trash" /> Eliminar proyecto</button>
                </form>
            </div>
        </section>
        @endcan
    </aside>
</div>
