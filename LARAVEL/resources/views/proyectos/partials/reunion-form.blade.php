{{-- Formulario de reunión con acta. Params: $proyecto, $reunion (null = nueva), $tipoDefault, $tituloDefault --}}
@php
    $reunion       = $reunion ?? null;
    $tipoActual    = $reunion->tipo ?? ($tipoDefault ?? 'requerimientos');
    $tituloActual  = $reunion->titulo ?? ($tituloDefault ?? '');
    $plantillaActa = "Objetivo de la reunión:\n- \n\nTemas tratados:\n- \n\nRequerimientos identificados:\n- \n\nAcuerdos:\n- ";
@endphp
<form method="POST"
      action="{{ $reunion ? route('proyectos.reuniones.update', [$proyecto, $reunion]) : route('proyectos.reuniones.store', $proyecto) }}"
      enctype="multipart/form-data" class="pf-box pf-form">
    @csrf
    @if($reunion) @method('PATCH') @endif

    <label class="pf-field">
        <span class="pf-label">Tipo de reunión</span>
        <select name="tipo" class="pf-input" required>
            @foreach(\App\Models\ProjectMeeting::TIPOS as $clave => $etiqueta)
                <option value="{{ $clave }}" @selected($tipoActual === $clave)>{{ $etiqueta }}</option>
            @endforeach
        </select>
    </label>
    <label class="pf-field">
        <span class="pf-label">Fecha y hora</span>
        <input type="datetime-local" name="fecha" class="pf-input" required
               value="{{ ($reunion?->fecha ?? now())->format('Y-m-d\TH:i') }}">
    </label>
    <label class="pf-field full">
        <span class="pf-label">Título</span>
        <input type="text" name="titulo" class="pf-input" required maxlength="200" value="{{ $tituloActual }}" placeholder="Ej.: Reunión de inicio con el cliente">
    </label>
    <label class="pf-field full">
        <span class="pf-label">Asistentes</span>
        <input type="text" name="asistentes" class="pf-input" maxlength="500" value="{{ $reunion->asistentes ?? '' }}"
               placeholder="Ej.: Juan Pérez (cliente), Ana Ruiz (dev), Piero (PM)">
    </label>
    <label class="pf-field full">
        <span class="pf-label">Acta: temas tratados y acuerdos</span>
        <textarea name="acta" class="pf-input" rows="8" maxlength="20000">{{ $reunion->acta ?? $plantillaActa }}</textarea>
    </label>
    <label class="pf-field full">
        <span class="pf-label">Compromisos y pendientes</span>
        <textarea name="pendientes" class="pf-input" rows="3" maxlength="5000" placeholder="Ej.: El cliente envía logos y accesos al hosting — viernes">{{ $reunion->pendientes ?? '' }}</textarea>
    </label>
    <label class="pf-field full">
        <span class="pf-label">{{ $reunion ? 'Agregar adjuntos' : 'Acta firmada / adjuntos (opcional)' }}</span>
        <input type="file" name="adjuntos[]" class="pf-input" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
        <span class="pf-hint">PDF, Word o fotos del acta firmada. Hasta 5 archivos.</span>
    </label>
    <div class="pf-form__actions">
        <button type="button" class="pf-btn pf-btn--ghost" @click="form = false">Cancelar</button>
        <button type="submit" class="pf-btn pf-btn--primary"><x-pf-icon name="check" /> {{ $reunion ? 'Guardar cambios' : 'Guardar reunión y acta' }}</button>
    </div>
</form>
