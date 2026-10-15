{{-- Formulario del modelo de cotización ESTELAR. Params: $cotizacion, $clientes, $plantillas, $tiposVisita --}}
@php
    $vinetasIniciales = old('caracteristicas', $cotizacion->caracteristicas ?: ['']);
    $terminosGuardados = old('terminos', $cotizacion->terminos);
    $clientesJs = $clientes->mapWithKeys(fn ($c) => [$c->id => [
        'ruc' => $c->numero_documento, 'razon' => $c->razon_social, 'comercial' => $c->nombre_comercial,
    ]]);
    $plantillasJs = collect($plantillas)->map(fn ($p) => ['nombre' => $p['nombre'], 'titulo' => $p['titulo'], 'items' => $p['items']]);
    $terminosPorDefecto = \App\Support\CotizacionModelo::terminosTexto(5);
    $planInicial = array_values(old('plan_pago', $cotizacion->plan_pago ?: $planesPago['50-50']['partes']));
@endphp

<div class="pf-grid" x-data="{
        vinetas: @js(array_values($vinetasIniciales)),
        plantillas: @js($plantillasJs),
        clientes: @js($clientesJs),
        clienteId: @js((string) old('client_id', $cotizacion->client_id)),
        titulo: @js(old('titulo', $cotizacion->titulo)),
        total: @js((string) old('total', $cotizacion->total)),
        moneda: @js(old('moneda', $cotizacion->moneda ?? 'PEN')),
        etiqueta: @js(old('precio_etiqueta', $cotizacion->precio_etiqueta)),
        emision: @js(old('fecha_emision', optional($cotizacion->fecha_emision)->toDateString() ?? today()->toDateString())),
        dias: @js((int) old('vigencia_dias', $cotizacion->vigencia_dias ?: 5)),
        personalizar: @js(filled($terminosGuardados)),
        plan: @js($planInicial),
        planes: @js($planesPago),
        usarPlan(clave) { this.plan = this.planes[clave].partes.map(p => ({ ...p })) },
        agregarParte() { this.plan.push({ concepto: '', porcentaje: Math.max(0, 100 - this.sumaPlan) || '' }) },
        get sumaPlan() { return Math.round(this.plan.reduce((a, p) => a + (parseFloat(p.porcentaje) || 0), 0) * 100) / 100 },
        montoParte(i) {
            // Igual que el servidor: cada parte se redondea y la última se lleva el saldo.
            const t = parseFloat(this.total) || 0;
            const parte = p => Math.round(t * (parseFloat(p.porcentaje) || 0)) / 100;
            const monto = i === this.plan.length - 1 && Math.abs(this.sumaPlan - 100) < 0.01
                ? t - this.plan.slice(0, -1).reduce((a, p) => a + parte(p), 0)
                : parte(this.plan[i]);
            return (this.moneda === 'USD' ? '$ ' : 'S/ ') + monto.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        pegado: '',
        terminosBase: @js($terminosPorDefecto),
        get cliente() { return this.clientes[this.clienteId] || null },
        get vence() {
            if (!this.emision || !this.dias) return '—';
            const d = new Date(this.emision + 'T12:00:00'); d.setDate(d.getDate() + parseInt(this.dias, 10));
            return d.toLocaleDateString('es-PE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
        },
        get precio() {
            const n = parseFloat(this.total); if (isNaN(n)) return '—';
            return (this.moneda === 'USD' ? '$ ' : 'S/ ') + n.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        },
        get llenas() { return this.vinetas.filter(v => v.trim() !== '').length },
        agregar() { this.vinetas.push(''); this.$nextTick(() => { const t = this.$refs.lista.querySelectorAll('textarea'); t[t.length - 1]?.focus(); }); },
        quitar(i) { this.vinetas.splice(i, 1); if (!this.vinetas.length) this.vinetas.push(''); },
        mover(i, d) { const j = i + d; if (j < 0 || j >= this.vinetas.length) return; [this.vinetas[i], this.vinetas[j]] = [this.vinetas[j], this.vinetas[i]]; },
        cargarPlantilla(clave) {
            const p = this.plantillas[clave]; if (!p) return;
            if (this.llenas && !confirm('¿Reemplazar los puntos actuales por la plantilla «' + p.nombre + '»?')) return;
            this.vinetas = [...p.items]; if (!this.titulo) this.titulo = p.titulo;
        },
        pegarLineas() {
            const nuevas = this.pegado.split(/\r?\n/).map(l => l.replace(/^\s*[•\-\*·●▪]+\s*/, '').trim()).filter(Boolean);
            if (!nuevas.length) return;
            this.vinetas = [...this.vinetas.filter(v => v.trim() !== ''), ...nuevas]; this.pegado = '';
        },
     }">

    <div class="pf-col">

        {{-- 1 · Cliente y portada --}}
        <section class="pf-card">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="users" /> Cliente y portada</h2>
                    <div class="pf-card__sub">Aparece en la portada: «COTIZACIÓN DE …» y «RUC – RAZÓN SOCIAL – NOMBRE COMERCIAL».</div>
                </div>
            </header>
            <div class="pf-card__body pf-form">
                <label class="pf-field full">
                    <span class="pf-label">Cliente</span>
                    <select name="client_id" class="pf-input" required x-model="clienteId">
                        <option value="">— Selecciona el cliente —</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->numero_documento }} – {{ $c->razon_social }}{{ $c->nombre_comercial ? ' – ' . $c->nombre_comercial : '' }}</option>
                        @endforeach
                    </select>
                    <span class="pf-hint">¿No está? <a href="{{ route('clientes.create') }}" target="_blank" rel="noopener" style="color:var(--pf-sky)">Crear cliente</a> y recarga esta página.</span>
                </label>
                <label class="pf-field full">
                    <span class="pf-label">Título de la portada</span>
                    <div class="pf-row" style="flex-wrap:nowrap">
                        <span class="muted" style="white-space:nowrap;font-weight:700">COTIZACIÓN DE</span>
                        <input type="text" name="titulo" class="pf-input" required maxlength="150" x-model="titulo" style="text-transform:uppercase" placeholder="IMPLEMENTACION ADMINISTRATIVA">
                    </div>
                </label>
            </div>
        </section>

        {{-- 2 · Servicio --}}
        <section class="pf-card">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="briefcase" /> Servicio</h2>
                    <div class="pf-card__sub">Recuadro «SERVICIO DE …» de la página 2.</div>
                </div>
            </header>
            <div class="pf-card__body pf-form">
                <label class="pf-field">
                    <span class="pf-label">Servicio</span>
                    <input type="text" name="servicio" class="pf-input" required maxlength="150" style="text-transform:uppercase"
                           value="{{ old('servicio', $cotizacion->servicio) }}" placeholder="IMPLEMENTACION ESTELAR">
                </label>
                <label class="pf-field">
                    <span class="pf-label">S/N</span>
                    <input type="text" name="numero_serie" class="pf-input mono" maxlength="30" value="{{ old('numero_serie', $cotizacion->numero_serie) }}" placeholder="APF257801">
                </label>
                <label class="pf-field">
                    <span class="pf-label">Tipo de visita</span>
                    <input type="text" name="tipo_visita" class="pf-input" required maxlength="60" list="tipos-visita" style="text-transform:uppercase"
                           value="{{ old('tipo_visita', $cotizacion->tipo_visita) }}">
                    <datalist id="tipos-visita">
                        @foreach($tiposVisita as $tipo)<option value="{{ $tipo }}">@endforeach
                    </datalist>
                </label>
                <label class="pf-field">
                    <span class="pf-label">Modalidad</span>
                    <input type="text" name="modalidad" class="pf-input" maxlength="40" value="{{ old('modalidad', $cotizacion->modalidad) }}" placeholder="Fixed Price">
                </label>
            </div>
        </section>

        {{-- 3 · Descripción del sistema web --}}
        <section class="pf-card">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="list" /> Descripción del sistema web</h2>
                    <div class="pf-card__sub"><span x-text="llenas"></span> punto(s) · cada uno sale como una viñeta en el PDF.</div>
                </div>
                <div class="pf-row" style="gap:6px">
                    @foreach($plantillas as $clave => $p)
                    <button type="button" class="pf-btn pf-btn--sm" @click="cargarPlantilla(@js($clave))"><x-pf-icon name="sparkles" /> {{ $p['nombre'] }}</button>
                    @endforeach
                </div>
            </header>
            <div class="pf-card__body">
                <div class="pf-list" x-ref="lista">
                    <template x-for="(v, i) in vinetas" :key="i">
                        <div class="pf-row" style="flex-wrap:nowrap;align-items:flex-start">
                            <span class="pf-cuota__n" style="margin-top:6px" x-text="i + 1"></span>
                            <textarea name="caracteristicas[]" class="pf-input" rows="2" maxlength="1000" x-model="vinetas[i]"
                                      style="min-height:58px;resize:none;overflow:hidden" placeholder="Ej.: Registro y administración de productos…"
                                      x-init="$nextTick(() => { $el.style.height = $el.scrollHeight + 'px' }); $watch('vinetas', () => $nextTick(() => { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px' }))"
                                      @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"></textarea>
                            <div style="display:flex;flex-direction:column;gap:2px">
                                <button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" title="Subir" @click="mover(i, -1)" :disabled="i === 0">▲</button>
                                <button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" title="Bajar" @click="mover(i, 1)" :disabled="i === vinetas.length - 1">▼</button>
                            </div>
                            <button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" title="Quitar" @click="quitar(i)" style="margin-top:6px"><x-pf-icon name="x" style="color:#f87171" /></button>
                        </div>
                    </template>
                </div>
                <div class="pf-between" style="margin-top:12px">
                    <button type="button" class="pf-btn pf-btn--sm" @click="agregar()"><x-pf-icon name="plus" /> Agregar punto</button>
                </div>
                <details style="margin-top:14px">
                    <summary class="pf-hint" style="cursor:pointer;font-weight:700">Pegar varios puntos de una vez</summary>
                    <div class="pf-box" style="margin-top:8px">
                        <textarea class="pf-input" rows="5" x-model="pegado" placeholder="Pega una lista (un punto por línea). Las viñetas •, - o * se quitan solas."></textarea>
                        <div class="pf-form__actions" style="margin-top:8px"><button type="button" class="pf-btn pf-btn--sm pf-btn--primary" @click="pegarLineas()">Agregar a la lista</button></div>
                    </div>
                </details>
            </div>
        </section>

        {{-- 4 · Términos --}}
        <section class="pf-card">
            <header class="pf-card__head">
                <div>
                    <h2 class="pf-card__title"><x-pf-icon name="contract" /> Términos y condiciones</h2>
                    <div class="pf-card__sub" x-show="!personalizar">Se usan las 17 cláusulas del modelo (la vigencia se actualiza sola con los días).</div>
                    <div class="pf-card__sub" x-show="personalizar" style="display:none">Texto personalizado para esta cotización. Deja una línea en blanco entre cláusulas.</div>
                </div>
                <label class="pf-row" style="gap:8px;cursor:pointer">
                    <input type="checkbox" x-model="personalizar" style="accent-color:#0ea5e9;width:16px;height:16px">
                    <span class="pf-label" style="margin:0">Personalizar</span>
                </label>
            </header>
            <div class="pf-card__body" x-show="personalizar" style="display:none">
                <textarea name="terminos" class="pf-input mono" rows="14" style="font-size:12px" :disabled="!personalizar"
                          x-init="if (!$el.value.trim()) $el.value = terminosBase.replace('vigencia de 5 días', 'vigencia de ' + dias + ' días')">{{ $terminosGuardados }}</textarea>
            </div>
        </section>
    </div>

    {{-- ── Columna lateral: precio, vigencia y vista previa ─────── --}}
    <aside class="pf-col">
        <section class="pf-card">
            <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="cash" /> Precio del sistema</h2></header>
            <div class="pf-card__body pf-form">
                <label class="pf-field full">
                    <span class="pf-label">Etiqueta</span>
                    <input type="text" name="precio_etiqueta" class="pf-input" required maxlength="40" x-model="etiqueta" style="text-transform:uppercase">
                </label>
                <label class="pf-field">
                    <span class="pf-label">Monto</span>
                    <input type="number" name="total" class="pf-input mono" required step="0.01" min="0" x-model="total" placeholder="600.00">
                </label>
                <label class="pf-field">
                    <span class="pf-label">Moneda</span>
                    <select name="moneda" class="pf-input" x-model="moneda">
                        <option value="PEN">Soles (S/)</option>
                        <option value="USD">Dólares ($)</option>
                    </select>
                </label>
                <label class="pf-row full" style="gap:8px;cursor:pointer">
                    <input type="hidden" name="incluye_igv" value="0">
                    <input type="checkbox" name="incluye_igv" value="1" @checked(old('incluye_igv', $cotizacion->incluye_igv)) style="accent-color:#0ea5e9;width:16px;height:16px">
                    <span class="pf-hint">El precio ya incluye IGV (por defecto, no lo incluye).</span>
                </label>
            </div>

            {{-- Forma de pago --}}
            <div class="pf-card__body">
                <div class="pf-between" style="margin-bottom:10px">
                    <span class="pf-label" style="font-size:12.5px">Forma de pago</span>
                    <span class="pf-pill pf-pill--plain mono" :class="Math.abs(sumaPlan - 100) < 0.01 ? 'pf-pill--green' : 'pf-pill--red'" x-text="sumaPlan + '%'"></span>
                </div>
                <div class="pf-chips" style="margin-bottom:12px">
                    @foreach($planesPago as $clave => $planPreset)
                    <button type="button" class="pf-chip-btn" @click="usarPlan(@js($clave))">{{ $planPreset['nombre'] }}</button>
                    @endforeach
                </div>
                <div class="pf-gap-sm">
                    <template x-for="(p, i) in plan" :key="i">
                        <div class="pf-box" style="padding:10px">
                            <div class="pf-row" style="flex-wrap:nowrap;gap:6px">
                                <input type="text" class="pf-input" :name="`plan_pago[${i}][concepto]`" x-model="p.concepto" required maxlength="100" placeholder="Ej.: Adelanto al iniciar" style="padding:8px 10px">
                                <button type="button" class="pf-btn pf-btn--ghost pf-btn--sm" title="Quitar" @click="plan.splice(i, 1)" x-show="plan.length > 1"><x-pf-icon name="x" style="color:#f87171" /></button>
                            </div>
                            <div class="pf-row" style="flex-wrap:nowrap;gap:6px;margin-top:6px">
                                <input type="number" class="pf-input mono" :name="`plan_pago[${i}][porcentaje]`" x-model="p.porcentaje" required min="0.01" max="100" step="0.01" style="width:90px;padding:8px 10px">
                                <span class="muted">%</span>
                                <span class="mono" style="margin-left:auto;font-weight:800;color:#e2e8f0" x-text="montoParte(i)"></span>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="button" class="pf-btn pf-btn--sm" style="margin-top:10px" @click="agregarParte()" x-show="plan.length < 12"><x-pf-icon name="plus" /> Agregar parte</button>
                <p class="pf-hint" style="margin-top:8px">Al convertir en contrato, cada parte se vuelve una cuota del proyecto.</p>
            </div>
        </section>

        <section class="pf-card">
            <header class="pf-card__head"><h2 class="pf-card__title"><x-pf-icon name="calendar" /> Vigencia y seguimiento</h2></header>
            <div class="pf-card__body pf-form">
                <label class="pf-field">
                    <span class="pf-label">Fecha de emisión</span>
                    <input type="date" name="fecha_emision" class="pf-input" required x-model="emision">
                </label>
                <label class="pf-field">
                    <span class="pf-label">Vigencia (días)</span>
                    <input type="number" name="vigencia_dias" class="pf-input mono" required min="1" max="365" x-model="dias">
                </label>
                <div class="full pf-alert pf-alert--info">
                    <x-pf-icon name="clock" /><span>Vence el <strong x-text="vence"></strong></span>
                </div>
                <label class="pf-field full">
                    <span class="pf-label">Próximo seguimiento</span>
                    <input type="date" name="proximo_seguimiento" class="pf-input" value="{{ old('proximo_seguimiento', optional($cotizacion->proximo_seguimiento)->toDateString()) }}">
                    <span class="pf-hint">Te avisaremos ese día para llamar o escribir al cliente.</span>
                </label>
                <label class="pf-field full">
                    <span class="pf-label">Notas internas (no salen en el PDF)</span>
                    <textarea name="notas" class="pf-input" rows="3" maxlength="5000" placeholder="Ej.: lo refirió Juan; pidió descuento si paga al contado">{{ old('notas', $cotizacion->notas) }}</textarea>
                </label>
            </div>
        </section>

        <section class="pf-card" style="position:sticky;top:84px">
            <div class="pf-card__body">
                <div class="pf-section-title">Resumen</div>
                <div style="font-weight:800;color:#fff" x-text="cliente ? (cliente.comercial || cliente.razon) : 'Sin cliente'"></div>
                <div class="pf-hint mono" x-text="cliente ? cliente.ruc : ''"></div>
                <div class="pf-row" style="margin-top:12px;align-items:baseline">
                    <span class="pf-hint" x-text="etiqueta"></span>
                    <span class="pf-big-number" style="font-size:26px" x-text="precio"></span>
                </div>
                <div class="pf-hint" style="margin-top:6px"><span x-text="llenas"></span> puntos · vigencia <span x-text="dias"></span> días</div>
                <div class="pf-form__actions" style="margin-top:16px">
                    <a href="{{ $cotizacion->exists ? route('cotizaciones.show', $cotizacion) : route('cotizaciones.index') }}" class="pf-btn pf-btn--ghost">Cancelar</a>
                    <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="check" /> {{ $cotizacion->exists ? 'Guardar cambios' : 'Crear cotización' }}</button>
                </div>
            </div>
        </section>
    </aside>
</div>
