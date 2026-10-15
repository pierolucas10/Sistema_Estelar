{{-- $archivo (ProjectFile), $proyecto, $puedeBorrar (bool), $vista (finanzas|desarrollo) --}}
@php
    $ext = strtolower(pathinfo($archivo->nombre, PATHINFO_EXTENSION));
    $tipo = match (true) {
        $ext === 'pdf'                                      => 'is-pdf',
        in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) => 'is-img',
        in_array($ext, ['doc', 'docx', 'txt', 'md'])        => 'is-doc',
        in_array($ext, ['xls', 'xlsx', 'csv'])              => 'is-xls',
        default                                             => '',
    };
    $abreEnNavegador = $tipo === 'is-pdf' || $tipo === 'is-img';
@endphp
<div class="pf-file">
    <span class="pf-file__ext {{ $tipo }}">{{ \Illuminate\Support\Str::limit($archivo->extension(), 4, '') }}</span>
    <div class="pf-file__info">
        <a class="pf-file__name" title="{{ $archivo->nombre }}"
           href="{{ route('proyectos.archivos.download', [$proyecto, $archivo, 'ver' => $abreEnNavegador ? 1 : null]) }}"
           @if($abreEnNavegador) target="_blank" rel="noopener" @endif>{{ $archivo->nombre }}</a>
        <div class="pf-file__meta" title="{{ $archivo->descripcion }}">
            {{ $archivo->tamanoLegible() }} · {{ $archivo->created_at->format('d/m/Y') }}
            @if($archivo->subidoPor) · {{ \Illuminate\Support\Str::before($archivo->subidoPor->name, ' ') }} @endif
            @if($archivo->descripcion) · {{ $archivo->descripcion }} @endif
        </div>
    </div>
    <div class="pf-file__acts">
        <a href="{{ route('proyectos.archivos.download', [$proyecto, $archivo]) }}" class="pf-btn pf-btn--ghost pf-btn--icon" title="Descargar">
            <x-pf-icon name="download" style="width:16px;height:16px" />
        </a>
        @if($puedeBorrar)
        <form method="POST" action="{{ route('proyectos.archivos.destroy', [$proyecto, $archivo]) }}"
              x-data @submit.prevent="if (confirm('¿Eliminar «{{ addslashes($archivo->nombre) }}»?')) $el.submit()">
            @csrf @method('DELETE')
            <input type="hidden" name="vista" value="{{ $vista }}">
            <button type="submit" class="pf-btn pf-btn--ghost pf-btn--icon" title="Eliminar">
                <x-pf-icon name="trash" style="width:16px;height:16px;color:#f87171" />
            </button>
        </form>
        @endif
    </div>
</div>
