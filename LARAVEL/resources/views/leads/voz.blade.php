<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-600 font-mono">Inicio</span>
            <span class="text-slate-700">/</span>
            <h1 class="text-sm font-semibold text-white">Leads</h1>
        </div>
    </x-slot>

    @if($configurado)
        {{-- La URL del iframe pasa por Laravel, que firma el token al vuelo y redirige a VOZ --}}
        <iframe src="{{ route('leads.voz.entrar') }}"
                title="Prospección por voz"
                allow="microphone; clipboard-write"
                referrerpolicy="no-referrer"
                class="w-full rounded-xl border border-slate-700/60"
                style="display: block; height: calc(100vh - 8rem); min-height: 560px; background: #fff;"></iframe>
    @else
        <div class="rounded-xl border border-slate-700/60 p-6 text-sm text-slate-400">
            Falta configurar <span class="font-mono text-slate-300">VOZ_URL</span> y
            <span class="font-mono text-slate-300">VOZ_SSO_SECRET</span> en el <span class="font-mono">.env</span>.
        </div>
    @endif

</x-app-layout>
