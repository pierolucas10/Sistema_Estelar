<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bloque del horario de trabajo de una persona (p. ej. 08:00–12:00 · Proyecto A).
 * El reporte diario indica si se cumplió, qué se hizo, evidencias y, si no, la justificación.
 */
class HorarioBloque extends Model
{
    const ESTADOS = [
        'pendiente'   => 'Pendiente',
        'cumplido'    => 'Cumplido',
        'parcial'     => 'Parcial',
        'no_cumplido' => 'No cumplido',
    ];

    protected $fillable = [
        'user_id', 'fecha', 'hora_inicio', 'hora_fin', 'project_id', 'actividad', 'descripcion',
        'creado_por', 'estado', 'detalle', 'justificacion', 'reporte_diario_id', 'reportado_at',
    ];

    protected $casts = [
        'fecha'        => 'date',
        'reportado_at' => 'datetime',
    ];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function project(): BelongsTo  { return $this->belongsTo(Project::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'creado_por'); }
    public function reporte(): BelongsTo  { return $this->belongsTo(ReporteDiario::class, 'reporte_diario_id'); }
    public function archivos(): HasMany   { return $this->hasMany(HorarioBloqueArchivo::class); }

    public function inicio(): string { return substr((string) $this->hora_inicio, 0, 5); }
    public function fin(): string    { return substr((string) $this->hora_fin, 0, 5); }

    public function horas(): float
    {
        [$h1, $m1] = array_map('intval', explode(':', $this->inicio()));
        [$h2, $m2] = array_map('intval', explode(':', $this->fin()));
        return round(max(0, ($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 60, 1);
    }

    public function reportado(): bool
    {
        return $this->estado !== 'pendiente';
    }

    /** Ya pasó el día y nadie dijo nada. */
    public function atrasado(): bool
    {
        return $this->estado === 'pendiente' && $this->fecha->lt(today());
    }

    public function estadoLabel(): string
    {
        return $this->atrasado() ? 'Sin reportar' : (self::ESTADOS[$this->estado] ?? $this->estado);
    }

    /** Color de píldora (estilos pf-pill). */
    public function estadoColor(): string
    {
        if ($this->atrasado()) return 'red';
        return match ($this->estado) {
            'cumplido'    => 'green',
            'parcial'     => 'amber',
            'no_cumplido' => 'red',
            default       => 'sky',
        };
    }

    public function scopeDeUsuario($q, int $userId) { return $q->where('user_id', $userId); }

    /** Rango de días inclusivo, comparando solo la fecha (sin hora). */
    public function scopeEntreFechas($q, string $desde, string $hasta)
    {
        return $q->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta);
    }

    public function scopeOrdenados($q)
    {
        return $q->orderBy('fecha')->orderBy('hora_inicio');
    }
}
