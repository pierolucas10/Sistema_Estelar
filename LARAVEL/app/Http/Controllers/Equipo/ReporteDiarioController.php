<?php

namespace App\Http\Controllers\Equipo;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReporteDiarioRequest;
use App\Models\HorarioBloque;
use App\Models\ReporteDiario;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReporteDiarioController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ReporteDiario::class);

        $user = auth()->user();
        $esGestor = $user->hasAnyPermission(['reportes_diarios.gestionar', 'reportes_diarios.revisar']);

        $query = ReporteDiario::with('user')
            ->withCount('bloques')
            ->when(!$esGestor, fn($q) => $q->where('user_id', auth()->id()))
            ->when($request->area, fn($q, $v) => $q->where('area', $v))
            ->when($request->fecha_desde, fn($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($request->fecha_hasta, fn($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($request->user_id && $esGestor, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->revision, fn($q, $v) => $v === 'por_revisar'
                ? $q->whereIn('estado_revision', ['pendiente', 'subsanado'])
                : $q->where('estado_revision', $v))
            ->orderByDesc('fecha')
            ->orderByDesc('created_at');

        $reportes = $query->paginate(20)->withQueryString();

        $colaboradores = $esGestor
            ? User::orderBy('name')->get(['id', 'name'])
            : collect();

        // Reporte diario: ¿ya registré el de hoy?
        $reporteHoy = $user->can('reportes_diarios.crear')
            ? ReporteDiario::where('user_id', auth()->id())->whereDate('fecha', today())->first()
            : null;
        $debeReportarHoy = $user->can('reportes_diarios.crear') && !$reporteHoy;

        // Mi horario: hoy, próximos 7 días y bloques pasados sin reportar
        $misBloques = HorarioBloque::with('project:id,name')
            ->deUsuario($user->id)
            ->entreFechas(today()->subDays(30)->toDateString(), today()->addDays(7)->toDateString())
            ->ordenados()
            ->get();
        $bloquesHoy = $misBloques->filter(fn ($b) => $b->fecha->isToday())->values();
        $proximos = $misBloques->filter(fn ($b) => $b->fecha->gt(today()))->groupBy(fn ($b) => $b->fecha->toDateString());
        $sinReportar = $misBloques->filter(fn ($b) => $b->atrasado())->values();

        // Cumplimiento del equipo (últimos 7 días, hasta hoy)
        $cumplimiento = $esGestor ? $this->cumplimientoEquipo() : collect();

        return view('reportes_diarios.index', compact(
            'reportes', 'esGestor', 'colaboradores', 'reporteHoy', 'debeReportarHoy',
            'bloquesHoy', 'proximos', 'sinReportar', 'cumplimiento'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', ReporteDiario::class);

        $fecha = $request->input('fecha', today()->toDateString());
        try {
            $fecha = Carbon::parse($fecha)->min(today())->toDateString();
        } catch (\Throwable) {
            $fecha = today()->toDateString();
        }

        // Bloques del horario de ese día que aún no se reportan
        $bloques = HorarioBloque::with('project:id,name')
            ->deUsuario(auth()->id())
            ->whereDate('fecha', $fecha)
            ->where('estado', 'pendiente')
            ->orderBy('hora_inicio')
            ->get();

        return view('reportes_diarios.create', compact('fecha', 'bloques'));
    }

    public function store(StoreReporteDiarioRequest $request)
    {
        $data = $request->validated();
        $bloquesData = $data['bloques'] ?? [];
        unset($data['bloques']);

        if ($request->hasFile('archivo_adjunto')) {
            $data['archivo_adjunto'] = $request->file('archivo_adjunto')
                ->store('reportes_diarios', 'private');
        }

        $data['user_id'] = auth()->id();
        $data['tareas'] = $data['tareas'] ?? [];

        // Filtrar filas vacías de impedimentos
        if (!empty($data['impedimentos'])) {
            $data['impedimentos'] = collect($data['impedimentos'])
                ->filter(fn($i) => !empty($i['descripcion']))
                ->values()
                ->all();
        }

        DB::transaction(function () use ($data, $bloquesData, $request) {
            $reporte = ReporteDiario::create($data);

            foreach ($bloquesData as $bloqueId => $info) {
                $bloque = HorarioBloque::where('user_id', auth()->id())->findOrFail($bloqueId);
                $bloque->update([
                    'estado'            => $info['estado'],
                    'detalle'           => $info['detalle'] ?? null,
                    'justificacion'     => $info['justificacion'] ?? null,
                    'reporte_diario_id' => $reporte->id,
                    'reportado_at'      => now(),
                ]);

                foreach ($request->file("bloques.{$bloqueId}.archivos", []) as $archivo) {
                    $bloque->archivos()->create([
                        'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                        'path'   => $archivo->store("reportes_diarios/bloques/{$bloque->id}", 'private'),
                        'mime'   => $archivo->getClientMimeType(),
                        'size'   => $archivo->getSize(),
                    ]);
                }
            }
        });

        return redirect()->route('reportes_diarios.index')
            ->with('success', 'Reporte del día registrado correctamente.');
    }

    public function show(ReporteDiario $reportes_diario)
    {
        $this->authorize('view', $reportes_diario);

        $reportes_diario->load(['user', 'bloques.archivos', 'bloques.project:id,name', 'revisiones.user:id,name', 'revisadoPor:id,name']);

        return view('reportes_diarios.show', ['reporte' => $reportes_diario]);
    }

    /** Gerencia: revisado, observado (se debe subsanar) o rechazado. */
    public function revisar(Request $request, ReporteDiario $reportes_diario)
    {
        $this->authorize('revisar', $reportes_diario);

        $data = $request->validate([
            'accion'     => ['required', 'in:revisado,observado,rechazado'],
            'comentario' => ['nullable', 'required_if:accion,observado,rechazado', 'string', 'max:3000'],
        ], [
            'comentario.required_if' => 'Escribe qué debe corregir (observado) o el motivo (rechazado).',
        ]);

        DB::transaction(function () use ($reportes_diario, $data) {
            $reportes_diario->update([
                'estado_revision'      => $data['accion'],
                'observacion_revision' => $data['comentario'] ?? null,
                'revisado_por'         => auth()->id(),
                'revisado_at'          => now(),
            ]);
            $reportes_diario->revisiones()->create([
                'user_id'    => auth()->id(),
                'accion'     => $data['accion'],
                'comentario' => $data['comentario'] ?? null,
            ]);
        });

        $mensajes = [
            'revisado'  => 'Reporte marcado como revisado.',
            'observado' => 'Reporte observado: el colaborador recibirá el aviso para subsanarlo.',
            'rechazado' => 'Reporte rechazado.',
        ];

        return redirect()->route('reportes_diarios.show', $reportes_diario)->with('success', $mensajes[$data['accion']]);
    }

    /** Formulario para subsanar un reporte observado. */
    public function subsanarForm(ReporteDiario $reportes_diario)
    {
        $this->authorize('subsanar', $reportes_diario);

        $reportes_diario->load(['bloques.archivos', 'bloques.project:id,name', 'revisadoPor:id,name']);

        return view('reportes_diarios.subsanar', ['reporte' => $reportes_diario]);
    }

    /** El colaborador corrige lo observado y el reporte vuelve a gerencia como «Subsanado». */
    public function subsanar(Request $request, ReporteDiario $reportes_diario)
    {
        $this->authorize('subsanar', $reportes_diario);

        $ids = $reportes_diario->bloques()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $data = $request->validate([
            'respuesta'                 => ['required', 'string', 'max:3000'],
            'logros_destacados'         => ['nullable', 'string', 'max:2000'],
            'plan_siguiente_dia'        => ['nullable', 'string', 'max:2000'],
            'bloques'                   => [count($ids) ? 'required' : 'nullable', 'array'],
            'bloques.*.estado'          => ['required', 'in:cumplido,parcial,no_cumplido'],
            'bloques.*.detalle'         => ['nullable', 'required_if:bloques.*.estado,cumplido,parcial', 'string', 'max:3000'],
            'bloques.*.justificacion'   => ['nullable', 'required_if:bloques.*.estado,parcial,no_cumplido', 'string', 'max:2000'],
            'bloques.*.archivos'        => ['nullable', 'array', 'max:8'],
            'bloques.*.archivos.*'      => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt'],
        ], [
            'respuesta.required'                  => 'Explica qué corregiste respecto a la observación.',
            'bloques.*.detalle.required_if'       => 'Cuenta qué hiciste en cada bloque cumplido o parcial.',
            'bloques.*.justificacion.required_if' => 'Justifica por qué no se completó cada bloque parcial o no cumplido.',
        ]);

        if (array_diff(array_keys($data['bloques'] ?? []), $ids)) {
            return back()->withInput()->withErrors(['bloques' => 'Hay bloques que no pertenecen a este reporte.']);
        }

        DB::transaction(function () use ($request, $reportes_diario, $data) {
            foreach ($data['bloques'] ?? [] as $bloqueId => $info) {
                $bloque = $reportes_diario->bloques()->findOrFail($bloqueId);
                $bloque->update([
                    'estado'        => $info['estado'],
                    'detalle'       => $info['detalle'] ?? null,
                    'justificacion' => $info['justificacion'] ?? null,
                ]);
                foreach ($request->file("bloques.{$bloqueId}.archivos", []) as $archivo) {
                    $bloque->archivos()->create([
                        'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                        'path'   => $archivo->store("reportes_diarios/bloques/{$bloque->id}", 'private'),
                        'mime'   => $archivo->getClientMimeType(),
                        'size'   => $archivo->getSize(),
                    ]);
                }
            }

            $reportes_diario->update([
                'logros_destacados'  => $data['logros_destacados'] ?? $reportes_diario->logros_destacados,
                'plan_siguiente_dia' => $data['plan_siguiente_dia'] ?? $reportes_diario->plan_siguiente_dia,
                'estado_revision'    => 'subsanado',
                'subsanado_at'       => now(),
            ]);
            $reportes_diario->revisiones()->create([
                'user_id'    => auth()->id(),
                'accion'     => 'subsanado',
                'comentario' => $data['respuesta'],
            ]);
        });

        return redirect()->route('reportes_diarios.show', $reportes_diario)
            ->with('success', 'Reporte subsanado. Gerencia lo volverá a revisar.');
    }

    public function descargarAdjunto(ReporteDiario $reportes_diario)
    {
        $this->authorize('view', $reportes_diario);

        if (! $reportes_diario->archivo_adjunto
            || ! Storage::disk('private')->exists($reportes_diario->archivo_adjunto)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('private')->download(
            $reportes_diario->archivo_adjunto,
            basename($reportes_diario->archivo_adjunto)
        );
    }

    public function destroy(ReporteDiario $reportes_diario)
    {
        $this->authorize('delete', $reportes_diario);

        DB::transaction(function () use ($reportes_diario) {
            // Los bloques del horario vuelven a quedar pendientes y se borran sus evidencias.
            foreach ($reportes_diario->bloques()->with('archivos')->get() as $bloque) {
                foreach ($bloque->archivos as $archivo) {
                    Storage::disk('private')->delete($archivo->path);
                }
                $bloque->archivos()->delete();
                $bloque->update([
                    'estado' => 'pendiente', 'detalle' => null, 'justificacion' => null,
                    'reporte_diario_id' => null, 'reportado_at' => null,
                ]);
            }

            if ($reportes_diario->archivo_adjunto) {
                Storage::disk('private')->delete($reportes_diario->archivo_adjunto);
            }

            $reportes_diario->delete();
        });

        return redirect()->route('reportes_diarios.index')
            ->with('success', 'Reporte eliminado.');
    }

    /**
     * Resumen por persona de los últimos 7 días: bloques planificados y cómo se cumplieron.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function cumplimientoEquipo()
    {
        return HorarioBloque::with('user:id,name')
            ->entreFechas(today()->subDays(6)->toDateString(), today()->toDateString())
            ->get()
            ->groupBy('user_id')
            ->map(function ($bloques) {
                $cumplidos = $bloques->where('estado', 'cumplido')->count();
                $parciales = $bloques->where('estado', 'parcial')->count();
                $evaluables = $bloques->filter(fn ($b) => $b->reportado() || $b->atrasado())->count();

                return [
                    'usuario'      => $bloques->first()->user,
                    'total'        => $bloques->count(),
                    'cumplidos'    => $cumplidos,
                    'parciales'    => $parciales,
                    'no_cumplidos' => $bloques->where('estado', 'no_cumplido')->count(),
                    'sin_reportar' => $bloques->filter(fn ($b) => $b->atrasado())->count(),
                    'porcentaje'   => $evaluables ? (int) round(($cumplidos + $parciales * 0.5) / $evaluables * 100) : null,
                ];
            })
            ->sortBy('porcentaje')
            ->values();
    }
}
