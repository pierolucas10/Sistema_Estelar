{{-- $alertas: array de ['nivel' => danger|warning|info, 'texto' => ...]; $vacio: texto cuando no hay alertas --}}
@php
    $orden = ['danger' => 0, 'warning' => 1, 'info' => 2];
    $alertas = collect($alertas)->sortBy(fn ($a) => $orden[$a['nivel']] ?? 3)->values();
@endphp
<div class="pf-alerts">
    @forelse($alertas as $alerta)
        <div class="pf-alert pf-alert--{{ $alerta['nivel'] }}" role="{{ $alerta['nivel'] === 'danger' ? 'alert' : 'status' }}">
            <x-pf-icon :name="$alerta['nivel'] === 'info' ? 'info' : 'alert'" />
            <span>{{ $alerta['texto'] }}</span>
        </div>
    @empty
        @if(!empty($vacio))
        <div class="pf-alert pf-alert--ok">
            <x-pf-icon name="check-circle" />
            <span>{{ $vacio }}</span>
        </div>
        @endif
    @endforelse
</div>
