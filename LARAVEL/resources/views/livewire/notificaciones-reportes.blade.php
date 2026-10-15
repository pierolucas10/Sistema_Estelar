<div wire:poll.60s x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">

    @if($total > 0)
    @php $hayUrgentes = $items->contains(fn ($i) => in_array($i['nivel'], ['danger', 'warning'])); @endphp
    <button @click="open = !open"
            class="relative flex items-center justify-center w-9 h-9 rounded-xl transition-all duration-200
                   {{ $hayUrgentes ? 'bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:bg-amber-500/20' : 'bg-sky-500/10 border border-sky-500/30 text-sky-400 hover:bg-sky-500/20' }}"
            aria-label="Avisos de reportes diarios" title="Reportes diarios">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
        </svg>
        <span class="absolute -top-1.5 -right-1.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full
                     {{ $hayUrgentes ? 'bg-amber-500' : 'bg-sky-500' }} text-white text-[10px] font-bold leading-none">
            {{ $total > 9 ? '9+' : $total }}
        </span>
    </button>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.outside="open = false"
         class="absolute right-0 top-11 z-50 w-80 bg-slate-900 border border-slate-700/60 rounded-2xl shadow-xl shadow-black/40 overflow-hidden"
         style="display:none">

        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800">
            <p class="text-xs font-semibold text-white">Reportes diarios</p>
            <span class="text-[10px] font-mono text-slate-400 bg-slate-800 px-2 py-0.5 rounded-lg">{{ $total }} aviso(s)</span>
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-slate-800/60">
            @foreach($items as $item)
            @php
                $punto = ['danger' => 'bg-red-500', 'warning' => 'bg-amber-500', 'violet' => 'bg-violet-500'][$item['nivel']] ?? 'bg-sky-500';
            @endphp
            <a href="{{ $item['url'] }}" @click="open = false" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-800/50 transition-colors group">
                <span class="w-2 h-2 rounded-full {{ $punto }} flex-shrink-0 mt-1.5"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-200 group-hover:text-white transition-colors">{{ $item['titulo'] }}</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">{{ $item['detalle'] }}</p>
                </div>
            </a>
            @endforeach
        </div>

        <div class="px-4 py-2.5 border-t border-slate-800 bg-slate-950/40">
            <a href="{{ route('reportes_diarios.index') }}" @click="open = false" class="text-xs text-sky-400 hover:text-sky-300 transition-colors font-medium">
                Ir a Reportes diarios →
            </a>
        </div>
    </div>
    @endif

</div>
