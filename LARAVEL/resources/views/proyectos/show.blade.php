<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('proyectos.index') }}" class="text-slate-600 hover:text-slate-400 font-mono">Proyectos</a>
            <span class="text-slate-700">/</span>
            <span class="text-white font-semibold truncate max-w-[240px]">{{ $proyecto->name }}</span>
        </div>
    </x-slot>

    @include('proyectos.partials.estilos')

    @php
        $estadoColor = [
            'en_curso' => 'sky', 'entregado' => 'green', 'pausado' => 'amber',
            'en_revision' => 'violet', 'cancelado' => 'red',
        ][$proyecto->status] ?? '';
        $iniciales = collect(preg_split('/\s+/', trim($proyecto->name)))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
        $atrasado = $proyecto->end_date && $proyecto->end_date->isPast()
            && !in_array($proyecto->status, ['entregado', 'cancelado']);
    @endphp

    <div class="pf">

        {{-- ── Cabecera ─────────────────────────────────────────── --}}
        <section class="pf-hero">
            <div class="pf-hero__top">
                <div class="pf-hero__id">
                    <div class="pf-avatar">{{ $iniciales ?: 'P' }}</div>
                    <div style="min-width:0">
                        <div class="pf-row">
                            <h1 class="pf-title">{{ $proyecto->name }}</h1>
                            <span class="pf-pill {{ $estadoColor ? 'pf-pill--'.$estadoColor : '' }}">{{ $proyecto->statusLabel() }}</span>
                        </div>
                        <div class="pf-sub">{{ $proyecto->client->razon_social ?? 'Sin cliente' }}</div>
                        @if($proyecto->description)
                            <p class="pf-desc">{{ $proyecto->description }}</p>
                        @endif
                    </div>
                </div>

                <div class="pf-actions">
                    @if($proyecto->quote && auth()->user()->can('cotizaciones.ver'))
                    <a href="{{ route('cotizaciones.show', $proyecto->quote) }}" class="pf-btn">
                        <x-pf-icon name="doc" /> {{ $proyecto->quote->numero }}
                    </a>
                    @endif
                    @can('proyectos.editar')
                    <a href="{{ route('proyectos.edit', $proyecto) }}" class="pf-btn">
                        <x-pf-icon name="pencil" /> Editar
                    </a>
                    @endcan
                </div>
            </div>

            <div class="pf-meta">
                <div class="pf-meta__item">
                    <span class="pf-meta__label">Responsable</span>
                    <span class="pf-meta__value">{{ $proyecto->responsible->name ?? 'Sin asignar' }}</span>
                </div>
                <div class="pf-meta__item">
                    <span class="pf-meta__label">Inicio</span>
                    <span class="pf-meta__value mono">{{ $proyecto->start_date?->format('d/m/Y') ?? '—' }}</span>
                </div>
                <div class="pf-meta__item">
                    <span class="pf-meta__label">Entrega pactada</span>
                    <span class="pf-meta__value mono {{ $atrasado ? 'is-late' : '' }}">
                        {{ $proyecto->end_date?->format('d/m/Y') ?? '—' }}{{ $atrasado ? ' · vencida' : '' }}
                    </span>
                </div>
                @if($proyecto->estimacion_fecha_fin)
                <div class="pf-meta__item">
                    <span class="pf-meta__label">Entrega estimada (dev)</span>
                    <span class="pf-meta__value mono">{{ $proyecto->estimacion_fecha_fin->format('d/m/Y') }} · {{ $proyecto->estimacion_dias }} días háb.</span>
                </div>
                @endif
                <div class="pf-meta__item" style="flex:1 1 200px;max-width:320px">
                    <span class="pf-meta__label">Avance del desarrollo · {{ $proyecto->progress }}%</span>
                    <div class="pf-bar pf-bar--thin {{ $proyecto->progress >= 100 ? 'is-green' : '' }}" style="margin-top:8px">
                        <span style="width: {{ $proyecto->progress }}%"></span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Pestañas (solo si el usuario ve ambas vistas) ────── --}}
        @if($verFinanzas && $verDesarrollo)
        <nav class="pf-tabs" aria-label="Vistas del proyecto">
            <a href="{{ route('proyectos.show', [$proyecto, 'vista' => 'finanzas']) }}"
               class="pf-tab {{ $vista === 'finanzas' ? 'is-active' : '' }}" @if($vista === 'finanzas') aria-current="page" @endif>
                <x-pf-icon name="cash" /> Pagos y documentos
            </a>
            <a href="{{ route('proyectos.show', [$proyecto, 'vista' => 'desarrollo']) }}"
               class="pf-tab {{ $vista === 'desarrollo' ? 'is-active' : '' }}" @if($vista === 'desarrollo') aria-current="page" @endif>
                <x-pf-icon name="bolt" /> Desarrollo
            </a>
        </nav>
        @endif

        @if($errors->any())
        <div class="pf-alert pf-alert--danger" style="margin-bottom:18px" role="alert">
            <x-pf-icon name="alert" />
            <div>
                <strong>Revisa los datos:</strong>
                <ul style="margin:4px 0 0 16px;list-style:disc">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
        @endif

        @if($vista === 'finanzas')
            @include('proyectos.partials.finanzas')
        @elseif($vista === 'desarrollo')
            @include('proyectos.partials.desarrollo')
        @else
            @include('proyectos.partials.resumen')
        @endif

    </div>
</x-app-layout>
