<?php

namespace App\Livewire;

use App\Models\HorarioBloque;
use App\Models\ReporteDiario;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Campana de reportes diarios.
 * - Colaborador: días sin reportar, recordatorio del reporte de hoy (desde las 17:00) y reportes observados.
 * - Gerencia (reportes_diarios.revisar): reportes por revisar / subsanados y quién no reportó.
 */
class NotificacionesReportes extends Component
{
    private const HORA_RECORDATORIO = 17;

    public function render()
    {
        $user = auth()->user();
        $items = collect();

        if ($user?->can('reportes_diarios.crear')) {
            // Gerencia observó mi reporte: hay que subsanar
            foreach (ReporteDiario::where('user_id', $user->id)->where('estado_revision', 'observado')->orderByDesc('fecha')->limit(5)->get() as $r) {
                $items->push([
                    'nivel'  => 'warning',
                    'titulo' => 'Tu reporte del ' . $r->fecha->format('d/m') . ' fue observado',
                    'detalle' => 'Subsánalo: ' . \Illuminate\Support\Str::limit((string) $r->observacion_revision, 60),
                    'url'    => route('reportes_diarios.subsanar', $r),
                ]);
            }

            // Rechazos recientes
            foreach (ReporteDiario::where('user_id', $user->id)->where('estado_revision', 'rechazado')
                         ->where('revisado_at', '>=', now()->subDays(3))->get() as $r) {
                $items->push([
                    'nivel'  => 'danger',
                    'titulo' => 'Tu reporte del ' . $r->fecha->format('d/m') . ' fue rechazado',
                    'detalle' => \Illuminate\Support\Str::limit((string) $r->observacion_revision, 70),
                    'url'    => route('reportes_diarios.show', $r),
                ]);
            }

            // Días de mi horario sin reportar (últimas 2 semanas)
            $atrasados = HorarioBloque::deUsuario($user->id)
                ->where('estado', 'pendiente')
                ->entreFechas(today()->subDays(14)->toDateString(), today()->subDay()->toDateString())
                ->get()
                ->groupBy(fn ($b) => $b->fecha->toDateString());
            foreach ($atrasados as $fecha => $bloques) {
                $items->push([
                    'nivel'  => 'danger',
                    'titulo' => 'No reportaste el ' . Carbon::parse($fecha)->locale('es')->isoFormat('ddd D/MM'),
                    'detalle' => $bloques->count() . ' bloque(s) de tu horario sin reporte ni justificación',
                    'url'    => route('reportes_diarios.create', ['fecha' => $fecha]),
                ]);
            }

            // Recordatorio del reporte de hoy, al final de la jornada
            if (now()->hour >= self::HORA_RECORDATORIO
                && HorarioBloque::deUsuario($user->id)->whereDate('fecha', today())->where('estado', 'pendiente')->exists()
                && !ReporteDiario::where('user_id', $user->id)->whereDate('fecha', today())->exists()) {
                $items->push([
                    'nivel'  => 'info',
                    'titulo' => 'Registra tu reporte de hoy',
                    'detalle' => 'Tu horario de hoy aún no tiene reporte.',
                    'url'    => route('reportes_diarios.create'),
                ]);
            }
        }

        if ($user?->can('reportes_diarios.revisar')) {
            $porRevisar = ReporteDiario::with('user:id,name')
                ->whereIn('estado_revision', ['pendiente', 'subsanado'])
                ->where('user_id', '!=', $user->id)
                ->orderByRaw("CASE WHEN estado_revision = 'subsanado' THEN 0 ELSE 1 END")
                ->orderByDesc('fecha')
                ->get();

            foreach ($porRevisar->where('estado_revision', 'subsanado')->take(5) as $r) {
                $items->push([
                    'nivel'  => 'violet',
                    'titulo' => 'Subsanado: ' . ($r->user->name ?? '—'),
                    'detalle' => 'Reporte del ' . $r->fecha->format('d/m') . ' corregido, vuelve a revisión',
                    'url'    => route('reportes_diarios.show', $r),
                ]);
            }
            $nuevos = $porRevisar->where('estado_revision', 'pendiente');
            if ($nuevos->isNotEmpty()) {
                $items->push([
                    'nivel'  => 'info',
                    'titulo' => $nuevos->count() . ' reporte(s) por revisar',
                    'detalle' => $nuevos->pluck('user.name')->unique()->take(4)->implode(', '),
                    'url'    => route('reportes_diarios.index', ['revision' => 'pendiente']),
                ]);
            }

            // Quién no reportó (bloques de días pasados sin reporte, última semana)
            $faltas = HorarioBloque::with('user:id,name')
                ->where('estado', 'pendiente')
                ->where('user_id', '!=', $user->id)
                ->entreFechas(today()->subDays(7)->toDateString(), today()->subDay()->toDateString())
                ->get()
                ->groupBy('user_id');
            foreach ($faltas as $userId => $bloques) {
                $dias = $bloques->map(fn ($b) => $b->fecha->format('d/m'))->unique()->values();
                $items->push([
                    'nivel'  => 'danger',
                    'titulo' => ($bloques->first()->user->name ?? '—') . ' no reportó',
                    'detalle' => $dias->count() . ' día(s): ' . $dias->take(4)->implode(', '),
                    'url'    => route('reportes_diarios.horario', ['user_id' => $userId]),
                ]);
            }
        }

        return view('livewire.notificaciones-reportes', [
            'items' => $items,
            'total' => $items->count(),
        ]);
    }
}
