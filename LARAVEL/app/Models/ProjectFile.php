<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    const CATEGORIAS = [
        'contrato'      => 'Contrato',
        'documentacion' => 'Documentación',
        'acta'          => 'Acta de reunión',
        'otro'          => 'Otro',
    ];

    protected $fillable = [
        'project_id', 'meeting_id', 'categoria', 'nombre', 'path',
        'mime', 'size', 'descripcion', 'subido_por',
    ];

    public function project(): BelongsTo   { return $this->belongsTo(Project::class); }
    public function meeting(): BelongsTo   { return $this->belongsTo(ProjectMeeting::class, 'meeting_id'); }
    public function subidoPor(): BelongsTo { return $this->belongsTo(User::class, 'subido_por'); }

    public function categoriaLabel(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? $this->categoria;
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->nombre, PATHINFO_EXTENSION) ?: 'ARCH');
    }

    public function tamanoLegible(): string
    {
        $kb = $this->size / 1024;
        return $kb >= 1024 ? number_format($kb / 1024, 1) . ' MB' : max(1, (int) round($kb)) . ' KB';
    }
}
