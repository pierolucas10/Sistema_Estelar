<?php

namespace App\Http\Controllers\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMeeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Flujo de desarrollo por pasos: reuniones con acta (paso 1) y estimación (paso 2).
 * Los sprints (paso 3) siguen en SprintController.
 */
class ProyectoDesarrolloController extends Controller
{
    public function storeReunion(Request $request, Project $proyecto)
    {
        $this->authorize('view', $proyecto);

        $data = $this->validarReunion($request);

        DB::transaction(function () use ($request, $proyecto, $data) {
            $reunion = $proyecto->reuniones()->create($data + ['created_by' => auth()->id()]);
            $this->guardarAdjuntos($request, $proyecto, $reunion);
        });

        // Si es la reunión de inicio y el proyecto seguía planificado, arranca.
        if ($data['tipo'] === 'inicio' && $proyecto->status === 'planificado') {
            $proyecto->update([
                'status'     => 'en_curso',
                'start_date' => $proyecto->start_date ?? \Illuminate\Support\Carbon::parse($data['fecha'])->toDateString(),
            ]);
        }

        return $this->volver($proyecto, 'Reunión y acta registradas.');
    }

    public function updateReunion(Request $request, Project $proyecto, ProjectMeeting $reunion)
    {
        $this->authorize('view', $proyecto);
        abort_unless($reunion->project_id === $proyecto->id, 404);

        $reunion->update($this->validarReunion($request));
        $this->guardarAdjuntos($request, $proyecto, $reunion);

        return $this->volver($proyecto, 'Acta actualizada.');
    }

    public function destroyReunion(Project $proyecto, ProjectMeeting $reunion)
    {
        $this->authorize('view', $proyecto);
        abort_unless($reunion->project_id === $proyecto->id, 404);

        foreach ($reunion->archivos as $archivo) {
            Storage::disk('local')->delete($archivo->path);
            $archivo->delete();
        }
        $reunion->delete();

        return $this->volver($proyecto, 'Reunión eliminada.');
    }

    public function updateEstimacion(Request $request, Project $proyecto)
    {
        $this->authorize('view', $proyecto);

        $data = $request->validate([
            'estimacion_dias'    => ['required', 'integer', 'min:1', 'max:1000'],
            'estimacion_detalle' => ['nullable', 'string', 'max:5000'],
            'desde'              => ['nullable', 'date'],
        ]);

        // Días hábiles (lunes a viernes) a partir del inicio del proyecto o de hoy.
        $desde = \Illuminate\Support\Carbon::parse($data['desde'] ?? $proyecto->start_date ?? today());
        // El formulario envía texto ("10"); Carbon 3 exige un número.
        $dias = (int) $data['estimacion_dias'];
        $fin = $desde->copy()->addWeekdays($dias);

        $proyecto->update([
            'estimacion_dias'      => $dias,
            'estimacion_detalle'   => $data['estimacion_detalle'] ?? null,
            'estimacion_fecha_fin' => $fin,
            'estimado_por'         => auth()->id(),
            'estimado_at'          => now(),
            'start_date'           => $proyecto->start_date ?? $desde->toDateString(),
        ]);

        return $this->volver($proyecto, 'Estimación guardada: entrega estimada el ' . $fin->format('d/m/Y') . '.');
    }

    private function validarReunion(Request $request): array
    {
        return $request->validate([
            'tipo'       => ['required', Rule::in(array_keys(ProjectMeeting::TIPOS))],
            'titulo'     => ['required', 'string', 'max:200'],
            'fecha'      => ['required', 'date'],
            'asistentes' => ['nullable', 'string', 'max:500'],
            'acta'       => ['nullable', 'string', 'max:20000', 'required_without:adjuntos'],
            'pendientes' => ['nullable', 'string', 'max:5000'],
            'adjuntos'   => ['nullable', 'array', 'max:5'],
            'adjuntos.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
        ], [
            'acta.required_without' => 'Escribe el acta o adjunta el acta firmada.',
        ]);
    }

    private function guardarAdjuntos(Request $request, Project $proyecto, ProjectMeeting $reunion): void
    {
        foreach ($request->file('adjuntos', []) as $archivo) {
            $proyecto->archivos()->create([
                'categoria'  => 'acta',
                'meeting_id' => $reunion->id,
                'nombre'     => mb_substr($archivo->getClientOriginalName(), 0, 255),
                'path'       => $archivo->store("proyectos/{$proyecto->id}/acta", 'local'),
                'mime'       => $archivo->getClientMimeType(),
                'size'       => $archivo->getSize(),
                'subido_por' => auth()->id(),
            ]);
        }
    }

    private function volver(Project $proyecto, string $mensaje)
    {
        return redirect()
            ->to(route('proyectos.show', [$proyecto, 'vista' => 'desarrollo']) . '#paso-actual')
            ->with('success', $mensaje);
    }
}
