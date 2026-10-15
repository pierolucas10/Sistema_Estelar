<?php

namespace App\Http\Controllers\Equipo;

use App\Http\Controllers\Controller;
use App\Models\HorarioBloque;
use App\Models\HorarioBloqueArchivo;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Horario de trabajo por bloques. El reporte diario rinde cuentas de cada bloque.
 * - reportes_diarios.horario   → botón «Crear horario» (el propio)
 * - reportes_diarios.gestionar → ver y armar el horario de cualquier persona
 */
class HorarioController extends Controller
{
    private const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public function index(Request $request)
    {
        $user = $request->user();
        $esGestor = $user->can('reportes_diarios.gestionar');

        $persona = $esGestor && $request->filled('user_id')
            ? User::findOrFail($request->integer('user_id'))
            : $user;

        $lunes = Carbon::parse($request->input('semana', today()->toDateString()))->startOfWeek(Carbon::MONDAY);
        $domingo = $lunes->copy()->addDays(6);

        $bloques = HorarioBloque::with(['project:id,name', 'archivos', 'creadoPor:id,name'])
            ->deUsuario($persona->id)
            ->entreFechas($lunes->toDateString(), $domingo->toDateString())
            ->ordenados()
            ->get()
            ->groupBy(fn ($b) => $b->fecha->toDateString());

        $dias = collect(range(0, 6))->map(fn ($i) => $lunes->copy()->addDays($i));

        $resumen = HorarioBloque::deUsuario($persona->id)
            ->entreFechas($lunes->toDateString(), $domingo->toDateString())
            ->get();

        return view('reportes_diarios.horario', [
            'persona'    => $persona,
            'esGestor'   => $esGestor,
            'puedeCrear' => $user->can('reportes_diarios.horario'),
            'lunes'      => $lunes,
            'dias'       => $dias,
            'bloques'    => $bloques,
            'resumen'    => $resumen,
            'nombresDia' => self::DIAS,
            'usuarios'   => $esGestor ? User::where('activo', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'proyectos'  => Project::whereNotIn('status', ['entregado', 'cancelado'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Crea uno o varios bloques: misma hora y actividad, repetida en los días elegidos del rango. */
    public function store(Request $request)
    {
        $user = $request->user();
        $esGestor = $user->can('reportes_diarios.gestionar');

        $data = $request->validate([
            'user_id'     => ['nullable', 'exists:users,id'],
            'project_id'  => ['nullable', 'exists:projects,id'],
            'actividad'   => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'desde'       => ['required', 'date', 'after_or_equal:' . today()->subDays(7)->toDateString()],
            'hasta'       => ['nullable', 'date', 'after_or_equal:desde', 'before_or_equal:' . Carbon::parse($request->input('desde', today()))->addDays(62)->toDateString()],
            'dias'        => ['nullable', 'array'],
            'dias.*'      => ['integer', 'between:1,7'],
        ], [
            'hora_fin.after'       => 'La hora de fin debe ser posterior a la de inicio.',
            'hasta.before_or_equal' => 'Puedes programar como máximo 2 meses de una vez.',
            'desde.after_or_equal' => 'No se pueden programar bloques de hace más de una semana.',
        ]);

        // Solo un gestor arma el horario de otra persona.
        $personaId = $esGestor && !empty($data['user_id']) ? (int) $data['user_id'] : $user->id;

        // Siempre HH:MM:SS: así 12:00 no "choca" con un bloque que termina a las 12:00.
        $inicio = $data['hora_inicio'] . ':00';
        $fin = $data['hora_fin'] . ':00';
        $repetir = !empty($data['hasta']);

        $desde = Carbon::parse($data['desde']);
        $hasta = Carbon::parse($data['hasta'] ?? $data['desde']);
        $diasPermitidos = !empty($data['dias']) ? array_map('intval', $data['dias']) : [1, 2, 3, 4, 5, 6, 7];

        $creados = 0;
        $choques = [];
        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            if ($repetir && !in_array($d->dayOfWeekIso, $diasPermitidos, true)) {
                continue;
            }

            // No se cruzan dos bloques de la misma persona.
            $choca = HorarioBloque::deUsuario($personaId)
                ->whereDate('fecha', $d)
                ->where('hora_inicio', '<', $fin)
                ->where('hora_fin', '>', $inicio)
                ->exists();
            if ($choca) {
                $choques[] = $d->format('d/m');
                continue;
            }

            HorarioBloque::create([
                'user_id'     => $personaId,
                'fecha'       => $d->toDateString(),
                'hora_inicio' => $inicio,
                'hora_fin'    => $fin,
                'project_id'  => $data['project_id'] ?? null,
                'actividad'   => $data['actividad'],
                'descripcion' => $data['descripcion'] ?? null,
                'creado_por'  => $user->id,
            ]);
            $creados++;
        }

        $mensaje = $creados ? "Se programaron {$creados} bloque(s)." : 'No se programó ningún bloque.';
        if ($choques) {
            $mensaje .= ' Se omitieron ' . count($choques) . ' día(s) porque ya había otro bloque a esa hora: ' . implode(', ', array_slice($choques, 0, 8)) . '.';
        }

        return redirect()
            ->route('reportes_diarios.horario', array_filter(['semana' => $desde->toDateString(), 'user_id' => $personaId !== $user->id ? $personaId : null]))
            ->with($creados ? 'success' : 'error', $mensaje);
    }

    public function destroy(Request $request, HorarioBloque $bloque)
    {
        $user = $request->user();
        abort_unless($user->can('reportes_diarios.gestionar')
            || ($bloque->user_id === $user->id && $user->can('reportes_diarios.horario')), 403);

        if ($bloque->reportado()) {
            return back()->with('error', 'Ese bloque ya fue reportado; no se puede eliminar.');
        }

        $bloque->delete();

        return back()->with('success', 'Bloque eliminado del horario.');
    }

    /** Justificar un bloque de un día pasado que no se hizo (sin necesidad de un reporte completo). */
    public function justificar(Request $request, HorarioBloque $bloque)
    {
        abort_unless($bloque->user_id === $request->user()->id, 403);

        if ($bloque->reportado()) {
            return back()->with('error', 'Ese bloque ya fue reportado.');
        }

        $data = $request->validate(['justificacion' => ['required', 'string', 'max:2000']]);

        $bloque->update([
            'estado'        => 'no_cumplido',
            'justificacion' => $data['justificacion'],
            'reportado_at'  => now(),
        ]);

        return back()->with('success', 'Justificación registrada.');
    }

    public function archivo(Request $request, HorarioBloqueArchivo $archivo)
    {
        $archivo->load('bloque');
        $user = $request->user();
        abort_unless($user->can('reportes_diarios.gestionar') || $archivo->bloque->user_id === $user->id, 403);
        abort_unless(Storage::disk('private')->exists($archivo->path), 404);

        return Storage::disk('private')->response($archivo->path, $archivo->nombre, [
            'Cache-Control' => 'private, no-store',
        ], $request->boolean('descargar') ? 'attachment' : 'inline');
    }
}
