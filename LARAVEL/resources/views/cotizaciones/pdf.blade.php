<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cotización N° {{ $cotizacion->numero }}</title>
@php
    $cli = $cotizacion->client;
    $simbolo = $cotizacion->monedaSimbolo();
    $precio = number_format((float) $cotizacion->total, (float) $cotizacion->total == floor((float) $cotizacion->total) ? 0 : 2);
    $lineaCliente = trim(implode(' – ', array_filter([$cli->numero_documento ?? null, $cli->razon_social ?? null, $cli->nombre_comercial ?? null])));
@endphp
<style>
    @page { margin: 34pt 40pt 40pt 40pt; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #222; margin: 0; }

    /* ── Portada ─────────────────────────────────────────── */
    .cover { margin: -34pt -40pt -40pt -40pt; height: 841pt; position: relative; page-break-after: always; background: #ffffff; }
    .cover-top { height: 26pt; background: #1b2a49; }
    .cover-brand { text-align: center; padding: 14pt 0 8pt; }
    .cover-brand .marca { font-size: 34pt; font-weight: bold; color: #1f3864; letter-spacing: 1pt; line-height: 1; }
    .cover-brand .sub { font-size: 13pt; font-weight: bold; color: #1f3864; letter-spacing: .5pt; margin-top: 4pt; }
    .cover-rule { height: 1pt; background: #c0504d; margin: 0 40pt; }
    .cover-band { height: 16pt; background: #dfe6f0; margin-top: 10pt; }
    .cover-img { background: #0b1f3f; text-align: center; }
    .cover-img img { width: 595pt; height: 560pt; display: block; }
    .cover-foot { position: absolute; left: 40pt; right: 40pt; bottom: 34pt; }
    .cover-foot .titulo { font-size: 16pt; font-weight: bold; color: #404040; letter-spacing: .3pt; }
    .cover-foot .cliente { font-size: 13pt; font-weight: bold; color: #1f3864; margin-top: 14pt; line-height: 1.35; }
    .cover-foot .fecha { font-size: 9pt; color: #6b7280; margin-top: 8pt; }

    /* ── Encabezado de la propuesta ─────────────────────── */
    .head { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
    .head td { vertical-align: top; }
    .head .logo img { width: 96pt; height: auto; }
    .head .meta { text-align: right; font-size: 9pt; color: #333; line-height: 1.45; }
    .head .meta .t1 { font-size: 13pt; color: #222; }
    .head .meta .t2 { font-style: italic; color: #2f5496; }

    .banner { background: #1b2a49; color: #fff; text-align: center; font-weight: bold; font-size: 14pt; letter-spacing: 1pt; padding: 7pt 0; margin: 0 0 12pt; }

    .info { background: #1b2a49; color: #fff; width: 58%; border-collapse: collapse; margin-bottom: 18pt; }
    .info td { padding: 5pt 10pt; font-size: 9.5pt; }
    .info td.k { font-weight: bold; width: 90pt; }

    /* ── Descripción y precio ───────────────────────────── */
    .det { width: 100%; border-collapse: collapse; }
    .det th { background: #1b2a49; color: #fff; font-size: 10.5pt; font-weight: bold; padding: 10pt 12pt; text-align: center; }
    .det th.desc { width: 68%; }
    .det td { background: #efefef; vertical-align: top; }
    .det td.desc { padding: 3.5pt 16pt; font-size: 9pt; font-style: italic; font-weight: bold; color: #333; text-align: justify; line-height: 1.33; }
    .det td.precio { border-left: 1pt solid #555; text-align: center; padding: 4pt 8pt; }
    .det tr:first-child td.precio { padding-top: 12pt; }
    .det tr:first-child td.desc { padding-top: 12pt; }
    .det tr.ultima td { padding-bottom: 14pt; }
    .precio .lbl { font-weight: bold; font-size: 10.5pt; color: #222; }
    .precio .monto { font-size: 13pt; color: #222; margin-top: 3pt; }
    .precio .nota { font-size: 7.5pt; color: #666; margin-top: 4pt; }
    .det td.precio .pago-t { font-weight: bold; font-size: 8.5pt; color: #1b2a49; letter-spacing: .3pt; margin-bottom: 4pt; padding-top: 6pt; border-top: 1pt solid #bdbdbd; }
    .precio .pago-c { font-size: 8pt; color: #333; line-height: 1.25; }
    .precio .pago-m { font-size: 9.5pt; font-weight: bold; color: #222; margin-top: 1pt; }

    .resumen { font-size: 8pt; color: #555; margin: 8pt 0 0; }

    /* ── Términos ───────────────────────────────────────── */
    .terminos { margin-top: 22pt; font-size: 8.3pt; color: #111; text-align: justify; line-height: 1.38; }
    .terminos .h { font-weight: bold; font-size: 8.6pt; margin-bottom: 2pt; }
    .terminos .c-t { font-weight: bold; margin-top: 3pt; }
    .terminos .c-x { margin: 0; }

    .pie { margin-top: 22pt; padding-top: 8pt; border-top: 1pt solid #d1d5db; text-align: center; font-size: 7.5pt; color: #6b7280; }
</style>
</head>
<body>

{{-- ═════════ PORTADA ═════════ --}}
<div class="cover">
    <div class="cover-top"></div>
    <div class="cover-brand">
        <div class="marca">ESTELAR</div>
        <div class="sub">SOFTWARE EMPRESARIAL</div>
    </div>
    <div class="cover-rule"></div>
    <div class="cover-band"></div>
    @if($portada)
    <div class="cover-img"><img src="{{ $portada }}" alt=""></div>
    @endif
    <div class="cover-foot">
        <div class="titulo">COTIZACIÓN DE {{ $cotizacion->titulo }}</div>
        <div class="cliente">{{ $lineaCliente }}</div>
        <div class="fecha">{{ ucfirst($cotizacion->fecha_emision?->locale('es')->isoFormat('D [de] MMMM [de] YYYY')) }}</div>
    </div>
</div>

{{-- ═════════ PROPUESTA ═════════ --}}
<table class="head">
    <tr>
        <td class="logo">@if($logo)<img src="{{ $logo }}" alt="ESTELAR">@endif</td>
        <td class="meta">
            <div class="t1">Propuesta de Servicio</div>
            <div class="t2">{{ $cotizacion->modalidad ?: 'Fixed Price' }}</div>
            <div>Cotización N° {{ $cotizacion->numero }}</div>
            <div>Fecha: {{ $cotizacion->fecha_emision?->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>

<div class="banner">SERVICIO DE {{ $cotizacion->servicio }}</div>

<table class="info">
    <tr><td class="k">Servicio</td><td>{{ $cotizacion->servicio }}</td></tr>
    @if($cotizacion->numero_serie)<tr><td class="k">S/N</td><td>{{ $cotizacion->numero_serie }}</td></tr>@endif
    <tr><td class="k">Tipo de Visita</td><td>{{ $cotizacion->tipo_visita }}</td></tr>
</table>

<table class="det">
    <thead>
        <tr><th class="desc">DESCRIPCION SISTEMA WEB</th><th>PRECIO DEL SISTEMA</th></tr>
    </thead>
    @php
        // La columna derecha se reparte fila por fila para no estirar la primera viñeta:
        // fila 1 = precio; fila 2 = «FORMA DE PAGO» + 1ª parte; siguientes = una parte cada una.
        $partes = $cotizacion->planPago();
        $filas = max(count($vinetas), count($partes) + 1);
    @endphp
    <tbody>
        @for($i = 0; $i < $filas; $i++)
        <tr class="{{ $i === $filas - 1 ? 'ultima' : '' }}">
            <td class="desc">@isset($vinetas[$i])• {{ $vinetas[$i] }}@endisset</td>
            <td class="precio">
                @if($i === 0)
                    <div class="lbl">{{ $cotizacion->precio_etiqueta ?: 'PRECIO ÚNICO' }}</div>
                    <div class="monto">{{ $simbolo }} {{ $precio }}</div>
                    <div class="nota">{{ $cotizacion->incluye_igv ? 'Incluye IGV' : 'No incluye IGV' }}</div>
                @elseif(isset($partes[$i - 1]))
                    @php $parte = $partes[$i - 1]; @endphp
                    @if($i === 1)<div class="pago-t">FORMA DE PAGO</div>@endif
                    <div class="pago-c">{{ rtrim(rtrim(number_format($parte['porcentaje'], 2), '0'), '.') }}% · {{ $parte['concepto'] }}</div>
                    <div class="pago-m">{{ $simbolo }} {{ number_format($parte['monto'], 2) }}</div>
                @endif
            </td>
        </tr>
        @endfor
    </tbody>
</table>
<p class="resumen">
    Validez: {{ $cotizacion->vigencia_dias }} días naturales desde la emisión (hasta el {{ $cotizacion->fecha_vencimiento?->format('d/m/Y') }}).
    Moneda: {{ $cotizacion->moneda === 'USD' ? 'dólares americanos' : 'soles' }}.
</p>

<div class="terminos">
    <div class="h">TÉRMINOS Y CONDICIONES GENERALES DE LA COTIZACIÓN</div>
    @foreach($cotizacion->clausulas() as $clausula)
        @if($clausula['titulo'])<div class="c-t">{{ $clausula['titulo'] }}</div>@endif
        @foreach(preg_split('/
+/', $clausula['texto']) as $parrafo)<p class="c-x">{{ $parrafo }}</p>@endforeach
    @endforeach
</div>

@if($config)
<div class="pie">
    {{ $config->razon_social ?: 'ESTELAR SOFTWARE EMPRESARIAL' }}@if($config->ruc) · RUC {{ $config->ruc }}@endif
    @if($config->telefono) · {{ $config->telefono }}@endif
    @if($config->email) · {{ $config->email }}@endif
    @if($config->web) · {{ $config->web }}@endif
</div>
@endif

</body>
</html>
