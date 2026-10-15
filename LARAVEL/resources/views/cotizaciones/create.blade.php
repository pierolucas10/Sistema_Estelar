<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('cotizaciones.index') }}" class="text-slate-600 hover:text-slate-400 font-mono">Cotizaciones</a>
            <span class="text-slate-700">/</span>
            <span class="text-white font-semibold">Nueva</span>
        </div>
    </x-slot>

    @include('proyectos.partials.estilos')

    <div class="pf">
        <div style="margin-bottom:18px">
            <h2 class="pf-title" style="font-size:22px">Nueva cotización</h2>
            <p class="pf-sub">Se genera con el modelo ESTELAR: portada, servicio, descripción del sistema, precio y términos.</p>
        </div>

        @include('cotizaciones.partials.errores')

        <form method="POST" action="{{ route('cotizaciones.store') }}">
            @csrf
            @include('cotizaciones._form')
        </form>
    </div>
</x-app-layout>
