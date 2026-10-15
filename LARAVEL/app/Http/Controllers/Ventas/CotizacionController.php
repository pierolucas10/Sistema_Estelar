<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreCotizacionRequest;
use App\Http\Requests\Ventas\UpdateCotizacionRequest;
use App\Models\Client;
use App\Models\EmpresaConfig;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteFollowup;
use App\Models\User;
use App\Support\CotizacionModelo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Cotizaciones con el modelo ESTELAR: se crean, se les hace seguimiento hasta su
 * vencimiento y, si el cliente acepta, se convierten en contrato (= proyecto).
 */
class CotizacionController extends Controller
{
    public function index()
    {
        $abiertas = Quote::abiertas();

        $kpis = [
            'abiertas'      => (clone $abiertas)->count(),
            'monto_abierto' => (float) (clone $abiertas)->where('moneda', 'PEN')->sum('total'),
            'vencidas'      => Quote::vencidas()->count(),
            'atencion'      => Quote::requierenAtencion()->count(),
            'contratos_mes' => Quote::where('status', 'aceptado')->whereNotNull('convertida_at')
                                    ->where('convertida_at', '>=', now()->startOfMonth())->count(),
            'monto_mes'     => (float) Quote::where('status', 'aceptado')->whereNotNull('convertida_at')
                                    ->where('convertida_at', '>=', now()->startOfMonth())->where('moneda', 'PEN')->sum('total'),
        ];

        // Tasa de cierre de los últimos 90 días: contratos / (contratos + rechazadas)
        $cerradas = Quote::whereIn('status', ['aceptado', 'rechazado'])->where('updated_at', '>=', now()->subDays(90));
        $totalCerradas = (clone $cerradas)->count();
        $kpis['tasa_cierre'] = $totalCerradas ? (int) round((clone $cerradas)->where('status', 'aceptado')->count() / $totalCerradas * 100) : null;

        $atencion = Quote::requierenAtencion()
            ->with('client')
            ->withCount('seguimientos')
            ->orderByRaw('COALESCE(proximo_seguimiento, fecha_vencimiento)')
            ->limit(8)
            ->get();

        return view('cotizaciones.index', compact('kpis', 'atencion'));
    }

    public function create(Request $request)
    {
        $cotizacion = new Quote([
            'titulo'          => 'IMPLEMENTACION ADMINISTRATIVA',
            'servicio'        => 'IMPLEMENTACION ESTELAR',
            'numero_serie'    => Quote::generarNumeroSerie(),
            'tipo_visita'     => 'PLAN MENSUAL',
            'modalidad'       => 'Fixed Price',
            'precio_etiqueta' => 'PRECIO ÚNICO',
            'plan_pago'       => CotizacionModelo::PLANES_PAGO['50-50']['partes'],
            'moneda'          => 'PEN',
            'incluye_igv'     => false,
            'fecha_emision'   => today(),
            'vigencia_dias'   => 5,
            'proximo_seguimiento' => today()->addDays(2),
            'caracteristicas' => [''],
            'client_id'       => $request->integer('client_id') ?: null,
        ]);

        // Duplicar una cotización existente (nuevo número, nuevas fechas)
        if ($origen = Quote::find($request->integer('duplicar'))) {
            $cotizacion->fill($origen->only([
                'client_id', 'titulo', 'servicio', 'tipo_visita', 'modalidad', 'caracteristicas',
                'precio_etiqueta', 'plan_pago', 'total', 'moneda', 'incluye_igv', 'vigencia_dias', 'terminos', 'notas',
            ]));
        }

        return view('cotizaciones.create', $this->formData() + compact('cotizacion'));
    }

    public function store(StoreCotizacionRequest $request)
    {
        $quote = DB::transaction(function () use ($request) {
            $quote = Quote::create($this->datosDesdeFormulario($request->validated()) + [
                'numero'     => Quote::generarNumero(),
                'status'     => 'borrador',
                'created_by' => auth()->id(),
            ]);
            $this->sincronizarItem($quote);
            return $quote;
        });

        return redirect()->route('cotizaciones.show', $quote)
            ->with('success', "Cotización N° {$quote->numero} creada. Descarga el PDF y envíala al cliente.");
    }

    public function show(Quote $cotizacion)
    {
        $cotizacion->load(['client', 'project', 'createdBy', 'seguimientos.user']);
        $usuarios = User::where('activo', true)->orderBy('name')->get(['id', 'name']);

        return view('cotizaciones.show', compact('cotizacion', 'usuarios'));
    }

    public function edit(Quote $cotizacion)
    {
        if (!$cotizacion->esEditable()) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Solo se pueden editar cotizaciones en borrador o enviadas.');
        }

        if (empty($cotizacion->caracteristicas)) {
            // Cotizaciones antiguas: sus ítems pasan a ser las viñetas.
            $cotizacion->caracteristicas = $cotizacion->items()->pluck('descripcion')->all() ?: [''];
        }

        return view('cotizaciones.edit', $this->formData() + compact('cotizacion'));
    }

    public function update(UpdateCotizacionRequest $request, Quote $cotizacion)
    {
        if (!$cotizacion->esEditable()) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Esta cotización ya no se puede editar.');
        }

        DB::transaction(function () use ($request, $cotizacion) {
            $cotizacion->update($this->datosDesdeFormulario($request->validated()));
            $this->sincronizarItem($cotizacion);
        });

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', 'Cotización actualizada.');
    }

    public function destroy(Quote $cotizacion)
    {
        if ($cotizacion->estaConvertida()) {
            return back()->with('error', 'No se puede eliminar: esta cotización ya es un contrato (proyecto).');
        }

        $cotizacion->delete();

        return redirect()->route('cotizaciones.index')
            ->with('success', "Cotización N° {$cotizacion->numero} eliminada.");
    }

    // ── Seguimiento comercial ────────────────────────────────────────

    public function enviar(Quote $cotizacion)
    {
        abort_unless($cotizacion->estaAbierta(), 422);

        $cotizacion->update([
            'status'              => 'enviado',
            'sent_at'             => $cotizacion->sent_at ?? now(),
            'proximo_seguimiento' => $cotizacion->proximo_seguimiento && $cotizacion->proximo_seguimiento->gte(today())
                ? $cotizacion->proximo_seguimiento : today()->addDays(2),
        ]);

        return back()->with('success', 'Marcada como enviada. Te avisaremos cuando toque hacer seguimiento.');
    }

    public function seguimiento(Request $request, Quote $cotizacion)
    {
        $data = $request->validate([
            'tipo'                => ['required', Rule::in(array_keys(QuoteFollowup::TIPOS))],
            'nota'                => ['required', 'string', 'max:2000'],
            'proximo_seguimiento' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($cotizacion, $data) {
            $cotizacion->seguimientos()->create($data + ['user_id' => auth()->id()]);
            $cotizacion->update(['proximo_seguimiento' => $data['proximo_seguimiento'] ?? null]);
        });

        return back()->with('success', 'Seguimiento registrado.');
    }

    public function renovar(Request $request, Quote $cotizacion)
    {
        abort_unless($cotizacion->estaAbierta(), 422);

        $dias = (int) $request->validate(['vigencia_dias' => ['required', 'integer', 'min:1', 'max:365']])['vigencia_dias'];

        $cotizacion->update([
            'fecha_emision'     => today(),
            'vigencia_dias'     => $dias,
            'fecha_vencimiento' => Quote::calcularVencimiento(today(), $dias),
        ]);
        $cotizacion->seguimientos()->create([
            'user_id' => auth()->id(),
            'tipo'    => 'nota',
            'nota'    => "Vigencia renovada por {$dias} días (nueva fecha de emisión: " . today()->format('d/m/Y') . ').',
            'proximo_seguimiento' => $cotizacion->proximo_seguimiento,
        ]);

        return back()->with('success', 'Vigencia renovada hasta el ' . $cotizacion->fecha_vencimiento->format('d/m/Y') . '. Vuelve a enviar el PDF actualizado.');
    }

    public function rechazar(Request $request, Quote $cotizacion)
    {
        abort_unless($cotizacion->estaAbierta(), 422);

        $motivo = $request->validate(['motivo_rechazo' => ['required', 'string', 'max:255']])['motivo_rechazo'];

        $cotizacion->update(['status' => 'rechazado', 'motivo_rechazo' => $motivo, 'proximo_seguimiento' => null]);
        $cotizacion->seguimientos()->create(['user_id' => auth()->id(), 'tipo' => 'nota', 'nota' => "Rechazada: {$motivo}"]);

        return back()->with('success', 'Cotización marcada como rechazada.');
    }

    public function reabrir(Quote $cotizacion)
    {
        abort_unless($cotizacion->status === 'rechazado', 422);

        $cotizacion->update(['status' => 'enviado', 'motivo_rechazo' => null, 'proximo_seguimiento' => today()->addDay()]);
        $cotizacion->seguimientos()->create(['user_id' => auth()->id(), 'tipo' => 'nota', 'nota' => 'Cotización reabierta.']);

        return back()->with('success', 'Cotización reabierta.');
    }

    // ── Convertir en contrato (= proyecto) ───────────────────────────

    public function convertir(Request $request, Quote $cotizacion)
    {
        if (!$cotizacion->estaAbierta()) {
            return back()->with('error', 'Solo se pueden convertir cotizaciones en borrador o enviadas.');
        }

        $data = $request->validate([
            'nombre'              => ['required', 'string', 'max:200'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'start_date'          => ['nullable', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
            'crear_requerimientos' => ['nullable', 'boolean'],
        ]);

        $cotizacion->load('client');

        $proyecto = DB::transaction(function () use ($cotizacion, $data) {
            $proyecto = Project::create([
                'client_id'           => $cotizacion->client_id,
                'quote_id'            => $cotizacion->id,
                'name'                => $data['nombre'],
                'description'         => "Cotización N° {$cotizacion->numero} · Servicio de {$cotizacion->servicio} · {$cotizacion->tipo_visita}",
                'status'              => 'planificado',
                'start_date'          => $data['start_date'] ?? null,
                'end_date'            => $data['end_date'] ?? null,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'created_by'          => auth()->id(),
                'monto_contrato'      => $cotizacion->total,
                'moneda'              => $cotizacion->moneda,
                'checklist'           => collect(Project::PARTES_DEFAULT)
                    ->map(fn ($nombre) => ['nombre' => $nombre, 'completado' => false])->all(),
            ]);

            // Cada punto de la descripción pasa a ser un requerimiento para el equipo de desarrollo.
            if (!empty($data['crear_requerimientos'])) {
                foreach ($cotizacion->vinetas() as $vineta) {
                    $proyecto->requirements()->create([
                        'title'       => Str::limit($vineta, 195),
                        'description' => mb_strlen($vineta) > 195 ? $vineta : null,
                        'type'        => 'funcional',
                        'priority'    => 'media',
                        'status'      => 'pendiente',
                        'created_by'  => auth()->id(),
                    ]);
                }
            }

            // La forma de pago de la cotización se vuelve el plan de cuotas del proyecto:
            // la primera vence al inicio y la última en la entrega pactada.
            $partes = $cotizacion->planPago();
            foreach ($partes as $i => $parte) {
                $vence = match (true) {
                    $i === 0                  => $data['start_date'] ?? today()->toDateString(),
                    $i === count($partes) - 1 => $data['end_date'] ?? null,
                    default                   => null,
                };
                $proyecto->cuotas()->create([
                    'nombre'            => Str::limit($parte['concepto'] . ' (' . rtrim(rtrim(number_format($parte['porcentaje'], 2), '0'), '.') . '%)', 150, ''),
                    'monto'             => $parte['monto'],
                    'fecha_vencimiento' => $vence,
                    'estado'            => 'pendiente',
                    'orden'             => $i + 1,
                ]);
            }

            $cotizacion->update([
                'status'              => 'aceptado',
                'project_id'          => $proyecto->id,
                'accepted_at'         => $cotizacion->accepted_at ?? now(),
                'convertida_at'       => now(),
                'proximo_seguimiento' => null,
            ]);
            $cotizacion->seguimientos()->create([
                'user_id' => auth()->id(),
                'tipo'    => 'nota',
                'nota'    => "Convertida en contrato: proyecto «{$proyecto->name}».",
            ]);

            return $proyecto;
        });

        // La cotización aceptada queda guardada como archivo del proyecto.
        $path = "proyectos/{$proyecto->id}/otro/cotizacion-{$cotizacion->numero}.pdf";
        Storage::disk('local')->put($path, $this->generarPdf($cotizacion)->output());
        $proyecto->archivos()->create([
            'categoria'   => 'otro',
            'nombre'      => "Cotización N° {$cotizacion->numero}.pdf",
            'path'        => $path,
            'mime'        => 'application/pdf',
            'size'        => Storage::disk('local')->size($path),
            'descripcion' => 'Cotización aceptada',
            'subido_por'  => auth()->id(),
        ]);

        return redirect()
            ->to(route('proyectos.show', [$proyecto, 'vista' => 'finanzas']) . '#pagos')
            ->with('success', "¡Contrato cerrado! Se creó el proyecto «{$proyecto->name}» por {$cotizacion->montoFormateado()}. Ya tiene sus cuotas según la forma de pago: sube el contrato y registra los pagos.");
    }

    // ── PDF ──────────────────────────────────────────────────────────

    public function pdf(Request $request, Quote $cotizacion)
    {
        $pdf = $this->generarPdf($cotizacion);
        $nombre = 'Cotizacion-' . $cotizacion->numero . '-' . Str::slug($cotizacion->client->nombre_comercial ?: $cotizacion->client->razon_social ?? 'cliente') . '.pdf';

        return $request->boolean('descargar') ? $pdf->download($nombre) : $pdf->stream($nombre);
    }

    private function generarPdf(Quote $cotizacion)
    {
        $cotizacion->loadMissing(['client', 'items']);
        $config = EmpresaConfig::first();

        $imagen = function (?string $ruta) {
            if (!$ruta || !is_file($ruta)) return null;
            $mime = mime_content_type($ruta) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($ruta));
        };

        $portada = $imagen(resource_path('images/cotizacion/portada.jpg'));
        $logo = $config?->logo_documentos
            ? ($imagen(Storage::disk('public')->path($config->logo_documentos)) ?? $imagen(resource_path('images/cotizacion/logo.jpg')))
            : $imagen(resource_path('images/cotizacion/logo.jpg'));

        // Cotizaciones antiguas sin viñetas: se usan sus ítems.
        $vinetas = $cotizacion->vinetas() ?: $cotizacion->items->pluck('descripcion')->all();

        return Pdf::loadView('cotizaciones.pdf', compact('cotizacion', 'config', 'portada', 'logo', 'vinetas'))
            ->setPaper('A4', 'portrait');
    }

    // ── Helpers privados ─────────────────────────────────────────────

    private function datosDesdeFormulario(array $data): array
    {
        $total = round((float) $data['total'], 2);
        $incluyeIgv = (bool) ($data['incluye_igv'] ?? false);
        $igvPct = (float) (EmpresaConfig::first()?->igv_porcentaje ?? 18);
        $subtotal = $incluyeIgv ? round($total / (1 + $igvPct / 100), 2) : $total;

        // Si los términos son exactamente los del modelo, se guardan vacíos para que la vigencia se actualice sola.
        $terminos = trim((string) ($data['terminos'] ?? ''));
        if ($terminos === trim(CotizacionModelo::terminosTexto((int) $data['vigencia_dias']))) {
            $terminos = '';
        }

        return [
            'client_id'           => $data['client_id'],
            'titulo'              => mb_strtoupper($data['titulo']),
            'servicio'            => mb_strtoupper($data['servicio']),
            'numero_serie'        => $data['numero_serie'] ?? null,
            'tipo_visita'         => mb_strtoupper($data['tipo_visita']),
            'modalidad'           => $data['modalidad'] ?: 'Fixed Price',
            'caracteristicas'     => $data['caracteristicas'],
            'precio_etiqueta'     => mb_strtoupper($data['precio_etiqueta']),
            'plan_pago'           => collect($data['plan_pago'])->map(fn ($p) => [
                'concepto'   => trim($p['concepto']),
                'porcentaje' => round((float) $p['porcentaje'], 2),
            ])->values()->all(),
            'total'               => $total,
            'subtotal'            => $subtotal,
            'igv'                 => round($total - $subtotal, 2),
            'incluye_igv'         => $incluyeIgv,
            'moneda'              => $data['moneda'],
            'fecha_emision'       => $data['fecha_emision'],
            'vigencia_dias'       => (int) $data['vigencia_dias'],
            'fecha_vencimiento'   => Quote::calcularVencimiento($data['fecha_emision'], (int) $data['vigencia_dias']),
            'proximo_seguimiento' => $data['proximo_seguimiento'] ?? null,
            'terminos'            => $terminos ?: null,
            'notas'               => $data['notas'] ?? null,
        ];
    }

    /** Un único ítem con el servicio y el precio, para que Facturación pueda prellenar. */
    private function sincronizarItem(Quote $quote): void
    {
        $quote->items()->delete();
        $quote->items()->create([
            'descripcion'     => Str::limit('SERVICIO DE ' . $quote->servicio . ' — ' . $quote->tipo_visita, 250),
            'cantidad'        => 1,
            'unidad'          => 'servicio',
            'precio_unitario' => $quote->subtotal,
            'descuento'       => 0,
            'subtotal'        => $quote->subtotal,
            'orden'           => 0,
        ]);
    }

    private function formData(): array
    {
        $clientes = Client::activos()->orderBy('razon_social')
            ->get(['id', 'razon_social', 'nombre_comercial', 'numero_documento']);

        return [
            'clientes'    => $clientes,
            'plantillas'  => CotizacionModelo::plantillas(),
            'tiposVisita' => CotizacionModelo::TIPOS_VISITA,
            'planesPago'  => CotizacionModelo::PLANES_PAGO,
        ];
    }
}
