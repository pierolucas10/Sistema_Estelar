<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('cotizaciones.index') }}" class="text-slate-600 hover:text-slate-400 font-mono">Cotizaciones</a>
            <span class="text-slate-700">/</span>
            <a href="{{ route('cotizaciones.show', $cotizacion) }}" class="text-slate-600 hover:text-slate-400 font-mono">N° {{ $cotizacion->numero }}</a>
            <span class="text-slate-700">/</span>
            <span class="text-white font-semibold">Editar</span>
        </div>
    </x-slot>

    @include('proyectos.partials.estilos')

    <div class="pf">
        <div style="margin-bottom:18px">
            <h2 class="pf-title" style="font-size:22px">Editar cotización N° {{ $cotizacion->numero }}</h2>
            <p class="pf-sub">Si cambias la fecha o los días de vigencia, el vencimiento se recalcula.</p>
        </div>

        @include('cotizaciones.partials.errores')

        <form method="POST" action="{{ route('cotizaciones.update', $cotizacion) }}">
            @csrf @method('PUT')
            @include('cotizaciones._form')
        </form>
    </div>
</x-app-layout>
