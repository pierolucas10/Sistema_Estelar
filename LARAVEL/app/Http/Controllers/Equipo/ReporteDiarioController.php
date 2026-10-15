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
        $esGestor = $user->hasPermissionTo('reportes_diarios.gestionar');

        $query = ReporteDiario::with('user')
            ->withCount('bloques')
            ->when(!$esGestor, fn($q) => $q->where('user_id', auth()->id()))
            ->when($request->area, fn($q, $v) => $q->where('area', $v))
            ->when($request->fecha_desde, fn($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($request->fecha_hasta, fn($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($request->user_id && $esGestor, fn($q, $v) => $q->where('user_id', $v))
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

        $reportes_diario->load(['user', 'bloques.archivos', 'bloques.project:id,name']);

        return view('reportes_diarios.show', ['reporte' => $reportes_diario]);
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
