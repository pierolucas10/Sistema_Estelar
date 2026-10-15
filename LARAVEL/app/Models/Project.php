<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id', 'quote_id', 'name', 'description',
        'checklist', 'notas_reunion',
        'status', 'progress', 'start_date', 'end_date',
        'responsible_user_id', 'created_by',
        'monto_contrato', 'moneda',
        'estimacion_dias', 'estimacion_fecha_fin', 'estimacion_detalle',
        'estimado_por', 'estimado_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'checklist'  => 'array',
        'deleted_at' => 'datetime',
        'monto_contrato'       => 'decimal:2',
        'estimacion_fecha_fin' => 'date',
        'estimado_at'          => 'datetime',
    ];

    const ESTADOS = [
        'planificado', 'en_curso', 'pausado',
        'en_revision', 'entregado', 'cancelado',
    ];

    const PARTES_DEFAULT = [
        'Levantamiento de requerimientos',
        'Diseño de base de datos',
        'Diseño de interfaces (mockups)',
        'Desarrollo backend',
        'Desarrollo frontend',
        'Pruebas funcionales',
        'Revisión con el cliente',
        'Despliegue en producción',
        'Capacitación al cliente',
        'Documentación técnica',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('order');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->orderByDesc('created_at');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(ProjectInstallment::class)->orderBy('orden')->orderBy('id');
    }

    public function reuniones(): HasMany
    {
        return $this->hasMany(ProjectMeeting::class)->orderByDesc('fecha');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(ProjectFile::class)->latest();
    }

    public function estimadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estimado_por');
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhereHas('client', fn($c) => $c->where('razon_social', 'like', "%{$term}%"));
        });
    }

    public function scopeAsignadoA($query, int $userId)
    {
        return $query->where('responsible_user_id', $userId);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function statusBadgeClass(): string
    {
        return match($this->status) {
            'en_curso'    => 'bg-sky-500/10 text-sky-400 ring-1 ring-sky-500/20',
            'entregado'   => 'bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20',
            'planificado' => 'bg-slate-700 text-slate-400',
            'pausado'     => 'bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/20',
            'en_revision' => 'bg-violet-500/10 text-violet-400 ring-1 ring-violet-500/20',
            'cancelado'   => 'bg-red-500/10 text-red-400 ring-1 ring-red-500/20',
            default       => 'bg-slate-700 text-slate-400',
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'planificado' => 'Planificado',
            'en_curso'    => 'En curso',
            'pausado'     => 'Pausado',
            'en_revision' => 'En revisión',
            'entregado'   => 'Entregado',
            'cancelado'   => 'Cancelado',
            default       => $this->status,
        };
    }

    public function checklistCompletados(): int
    {
        return collect($this->checklist ?? [])->where('completado', true)->count();
    }

    public function checklistTotal(): int
    {
        return count($this->checklist ?? []);
    }

    public function recalcularProgreso(): void
    {
        $fases = $this->phases;
        if ($fases->isEmpty()) return;
        $this->progress = (int) round($fases->avg('progress'));
        $this->saveQuietly();
    }

    // ── Finanzas ─────────────────────────────────────────────────────

    public function monedaSimbolo(): string
    {
        return $this->moneda === 'USD' ? '$' : 'S/';
    }

    public function montoPagado(): float
    {
        return (float) $this->cuotas->where('estado', 'pagada')->sum('monto');
    }

    public function montoProgramado(): float
    {
        return (float) $this->cuotas->sum('monto');
    }

    /** Base del porcentaje: el monto del contrato o, si no hay, la suma de las cuotas. */
    public function montoBase(): float
    {
        return (float) ($this->monto_contrato ?: $this->montoProgramado());
    }

    public function montoPendiente(): float
    {
        return max(0, $this->montoBase() - $this->montoPagado());
    }

    public function porcentajePagado(): int
    {
        $base = $this->montoBase();
        return $base > 0 ? (int) min(100, round($this->montoPagado() / $base * 100)) : 0;
    }

    /** @return array<int, array{nivel: string, texto: string}> */
    public function alertasFinanzas(): array
    {
        $alertas = [];
        $simbolo = $this->monedaSimbolo();

        foreach ($this->cuotas as $cuota) {
            if ($cuota->vencida()) {
                $dias = (int) $cuota->fecha_vencimiento->diffInDays(today());
                $alertas[] = ['nivel' => 'danger', 'texto' => "«{$cuota->nombre}» ({$simbolo} " . number_format($cuota->monto, 2) . ") venció hace {$dias} día(s)."];
            } elseif ($cuota->porVencer()) {
                $dias = (int) today()->diffInDays($cuota->fecha_vencimiento);
                $cuando = $dias === 0 ? 'hoy' : "en {$dias} día(s)";
                $alertas[] = ['nivel' => 'warning', 'texto' => "«{$cuota->nombre}» vence {$cuando}."];
            }
        }

        if (!$this->monto_contrato) {
            $alertas[] = ['nivel' => 'info', 'texto' => 'Define el monto del contrato para calcular el porcentaje pagado.'];
        } elseif ($this->cuotas->isNotEmpty() && abs($this->montoProgramado() - (float) $this->monto_contrato) >= 0.01) {
            $dif = (float) $this->monto_contrato - $this->montoProgramado();
            $alertas[] = ['nivel' => 'warning', 'texto' => $dif > 0
                ? "Faltan programar {$simbolo} " . number_format($dif, 2) . ' en cuotas.'
                : "Las cuotas superan el contrato por {$simbolo} " . number_format(abs($dif), 2) . '.'];
        }

        if ($this->archivos->where('categoria', 'contrato')->isEmpty()) {
            $alertas[] = ['nivel' => 'info', 'texto' => 'Aún no se ha subido el contrato firmado.'];
        }

        return $alertas;
    }

    // ── Flujo de desarrollo por pasos ────────────────────────────────

    public function tieneActaInicio(): bool
    {
        return $this->reuniones->contains(fn ($r) => $r->tipo === 'inicio' && $r->tieneActa());
    }

    public function tieneEstimacion(): bool
    {
        return $this->estimacion_dias !== null;
    }

    public function tieneDocumentacion(): bool
    {
        return $this->archivos->where('categoria', 'documentacion')->isNotEmpty();
    }

    /**
     * Los 4 pasos del flujo. Cada paso se da por completo con datos reales, no con un check manual.
     *
     * @return array<int, array{clave: string, titulo: string, descripcion: string, completo: bool}>
     */
    public function pasos(): array
    {
        $reqs = $this->requirements;
        $reqsTerminados = $reqs->isNotEmpty()
            && $reqs->every(fn ($r) => in_array($r->status, ['completado', 'rechazado']));

        return [
            1 => [
                'clave'       => 'requerimientos',
                'titulo'      => 'Requerimientos',
                'descripcion' => 'Reunión de inicio con acta y lista de requerimientos',
                'completo'    => $this->tieneActaInicio() && $reqs->isNotEmpty(),
            ],
            2 => [
                'clave'       => 'estimacion',
                'titulo'      => 'Estimación',
                'descripcion' => 'Tiempo de desarrollo y documentación',
                'completo'    => $this->tieneEstimacion() && $this->tieneDocumentacion(),
            ],
            3 => [
                'clave'       => 'desarrollo',
                'titulo'      => 'Desarrollo',
                'descripcion' => 'Sprints, dailies y avance',
                'completo'    => $reqsTerminados || $this->progress >= 100,
            ],
            4 => [
                'clave'       => 'entrega',
                'titulo'      => 'Entrega',
                'descripcion' => 'Entregables y cierre',
                'completo'    => $this->status === 'entregado',
            ],
        ];
    }

    /** Primer paso sin completar (5 = todo listo). */
    public function pasoActual(): int
    {
        foreach ($this->pasos() as $n => $paso) {
            if (!$paso['completo']) {
                return $n;
            }
        }
        return 5;
    }

    /** Fecha objetivo: la estimada por desarrollo o, si no hay, la de entrega pactada. */
    public function fechaObjetivo(): ?\Illuminate\Support\Carbon
    {
        return $this->estimacion_fecha_fin ?? $this->end_date;
    }

    /**
     * Alertas del equipo de desarrollo: plazos, sprints, bloqueos y dailies.
     *
     * @return array<int, array{nivel: string, texto: string}>
     */
    public function alertasDesarrollo(?User $usuario = null): array
    {
        if (in_array($this->status, ['entregado', 'cancelado'])) {
            return [];
        }

        $alertas = [];
        $paso = $this->pasoActual();

        if (!$this->tieneActaInicio()) {
            $alertas[] = ['nivel' => 'warning', 'texto' => 'Falta registrar la reunión de inicio con su acta (paso 1).'];
        }
        if ($paso >= 2 && !$this->tieneEstimacion()) {
            $alertas[] = ['nivel' => 'warning', 'texto' => 'Indica cuántos días tomará el desarrollo (paso 2).'];
        }
        if ($this->tieneEstimacion() && !$this->tieneDocumentacion()) {
            $alertas[] = ['nivel' => 'warning', 'texto' => 'Adjunta la documentación del proyecto (paso 2).'];
        }

        // Plazo general y ritmo de avance
        $objetivo = $this->fechaObjetivo();
        if ($objetivo) {
            if ($objetivo->lt(today())) {
                $alertas[] = ['nivel' => 'danger', 'texto' => 'La fecha objetivo (' . $objetivo->format('d/m/Y') . ') ya pasó y el proyecto no se ha entregado.'];
            } elseif ($objetivo->lte(today()->addDays(7))) {
                $alertas[] = ['nivel' => 'warning', 'texto' => 'Quedan ' . (int) today()->diffInDays($objetivo) . ' día(s) para la fecha objetivo.'];
            }

            if ($this->start_date && $objetivo->gt($this->start_date) && $this->start_date->lte(today())) {
                $total = $this->start_date->diffInDays($objetivo);
                $transcurrido = min($total, $this->start_date->diffInDays(today()));
                $esperado = (int) round($transcurrido / max(1, $total) * 100);
                if ($esperado - $this->progress >= 20) {
                    $alertas[] = ['nivel' => 'warning', 'texto' => "Avance atrasado: {$this->progress}% real frente a {$esperado}% esperado a la fecha."];
                }
            }
        }

        // Sprints
        $sprints = $this->sprints;
        $activos = $sprints->where('status', 'activo');

        foreach ($activos as $sprint) {
            $pendientes = $sprint->requirements->whereNotIn('status', ['completado', 'rechazado'])->count();
            if ($sprint->end_date && $sprint->end_date->lt(today())) {
                $alertas[] = ['nivel' => 'danger', 'texto' => "El sprint «{$sprint->name}» venció hace " . (int) $sprint->end_date->diffInDays(today()) . " día(s) con {$pendientes} requerimiento(s) pendiente(s)."];
            } elseif ($sprint->end_date && $sprint->end_date->lte(today()->addDays(2)) && $pendientes > 0) {
                $alertas[] = ['nivel' => 'warning', 'texto' => "El sprint «{$sprint->name}» cierra el " . $sprint->end_date->format('d/m') . " y quedan {$pendientes} requerimiento(s)."];
            }
        }

        foreach ($sprints->where('status', 'planificacion') as $sprint) {
            if ($sprint->start_date && $sprint->start_date->lt(today())) {
                $alertas[] = ['nivel' => 'warning', 'texto' => "El sprint «{$sprint->name}» debía iniciar el " . $sprint->start_date->format('d/m') . ' y sigue en planificación.'];
            }
        }

        if ($paso === 3 && $activos->isEmpty()) {
            $alertas[] = ['nivel' => 'info', 'texto' => 'No hay ningún sprint activo en este proyecto.'];
        }

        // Requerimientos críticos sin terminar
        $criticos = $this->requirements->where('priority', 'critica')->whereNotIn('status', ['completado', 'rechazado'])->count();
        if ($criticos > 0) {
            $alertas[] = ['nivel' => 'warning', 'texto' => "{$criticos} requerimiento(s) de prioridad crítica sin terminar."];
        }

        // Bloqueos reportados en los dailies de ayer y hoy
        $bloqueos = $this->dailyReports
            ->filter(fn ($d) => $d->date && $d->date->gte(today()->subDay()) && $d->tieneBlockers());
        foreach ($bloqueos as $daily) {
            $alertas[] = ['nivel' => 'danger', 'texto' => ($daily->user->name ?? 'Alguien') . ' reportó un bloqueo: «' . \Illuminate\Support\Str::limit($daily->blockers, 90) . '»'];
        }

        // Daily propio pendiente hoy
        if ($usuario && $activos->isNotEmpty()
            && !$this->dailyReports->contains(fn ($d) => $d->user_id === $usuario->id && $d->date?->isToday())) {
            $alertas[] = ['nivel' => 'info', 'texto' => 'Aún no registras tu daily de hoy.'];
        }

        return $alertas;
    }
}
