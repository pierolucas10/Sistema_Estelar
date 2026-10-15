<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteRevision extends Model
{
    protected $table = 'reporte_revisiones';

    protected $fillable = ['reporte_diario_id', 'user_id', 'accion', 'comentario'];

    public function reporte(): BelongsTo { return $this->belongsTo(ReporteDiario::class, 'reporte_diario_id'); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    public function accionLabel(): string
    {
        return ReporteDiario::ESTADOS_REVISION[$this->accion] ?? $this->accion;
    }
}
