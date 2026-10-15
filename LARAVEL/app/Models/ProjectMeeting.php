<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMeeting extends Model
{
    const TIPOS = [
        'inicio'         => 'Reunión de inicio',
        'requerimientos' => 'Toma de requerimientos',
        'seguimiento'    => 'Seguimiento',
        'entrega'        => 'Entrega / cierre',
    ];

    protected $fillable = [
        'project_id', 'tipo', 'titulo', 'fecha',
        'asistentes', 'acta', 'pendientes', 'created_by',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function project(): BelongsTo   { return $this->belongsTo(Project::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function archivos(): HasMany    { return $this->hasMany(ProjectFile::class, 'meeting_id'); }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function tieneActa(): bool
    {
        return filled($this->acta) || $this->archivos->isNotEmpty();
    }
}
