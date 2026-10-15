<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteDiario extends Model
{
    protected $table = 'reportes_diarios';

    protected $fillable = [
        'user_id',
        'area',
        'fecha',
        'proyectos_asignados',
        'sprint_iteracion',
        'modulo_componente',
        'horas_trabajadas',
        'tareas',
        'logros_destacados',
        'impedimentos',
        'plan_siguiente_dia',
        'archivo_adjunto',
        'estado_revision',
        'observacion_revision',
        'revisado_por',
        'revisado_at',
        'subsanado_at',
    ];

    protected $casts = [
        'fecha'       => 'date',
        'tareas'      => 'array',
        'impedimentos'=> 'array',
        'horas_trabajadas' => 'decimal:1',
        'revisado_at'  => 'datetime',
        'subsanado_at' => 'datetime',
    ];

    /** Revisión de gerencia. «Subsanado» = el colaborador corrigió una observación y vuelve a revisión. */
    const ESTADOS_REVISION = [
        'pendiente' => 'Por revisar',
        'revisado'  => 'Revisado',
        'observado' => 'Observado',
        'subsanado' => 'Subsanado',
        'rechazado' => 'Rechazado',
    ];

    const AREAS = [
        'desarrollo' => 'Desarrollo',
        'ventas'     => 'Ventas',
    ];

    const TIPOS_TAREA = ['Desarrollo', 'Diseño', 'Testing', 'Reunión', 'Documentación', 'Soporte', 'Otro'];

    const ESTADOS_TAREA = ['Completado', 'Incompleto', 'En Progreso'];

    const IMPACTOS = ['Alto', 'Medio', 'Bajo'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function revisiones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReporteRevision::class)->latest();
    }

    public function estadoRevisionLabel(): string
    {
        return self::ESTADOS_REVISION[$this->estado_revision] ?? (string) $this->estado_revision;
    }

    /** Color de píldora (estilos pf-pill). */
    public function estadoRevisionColor(): string
    {
        return match ($this->estado_revision) {
            'revisado'  => 'green',
            'observado' => 'amber',
            'subsanado' => 'violet',
            'rechazado' => 'red',
            default     => 'sky',
        };
    }

    /** Espera la revisión de gerencia: es nuevo o ya fue subsanado. */
    public function porRevisar(): bool
    {
        return in_array($this->estado_revision, ['pendiente', 'subsanado'], true);
    }

    /** El colaborador debe corregirlo. */
    public function debeSubsanar(): bool
    {
        return $this->estado_revision === 'observado';
    }

    /** Bloques del horario de los que rinde cuentas este reporte. */
    public function bloques(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HorarioBloque::class)->orderBy('hora_inicio');
    }

    public function areaLabel(): string
    {
        return self::AREAS[$this->area] ?? $this->area;
    }

    public function areaColor(): string
    {
        return match($this->area) {
            'desarrollo' => 'sky',
            'ventas'     => 'emerald',
            default      => 'slate',
        };
    }

    public function tareasCompletadas(): int
    {
        return collect($this->tareas)->where('estado', 'Completado')->count();
    }

    public function tareasEnProgreso(): int
    {
        return collect($this->tareas)->where('estado', 'En Progreso')->count();
    }

    public function tareasIncompletas(): int
    {
        return collect($this->tareas)->where('estado', 'Incompleto')->count();
    }

    public function tieneImpedimentos(): bool
    {
        return !empty($this->impedimentos);
    }
}
