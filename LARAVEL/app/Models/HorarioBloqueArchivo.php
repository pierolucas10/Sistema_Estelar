<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioBloqueArchivo extends Model
{
    protected $fillable = ['horario_bloque_id', 'nombre', 'path', 'mime', 'size'];

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(HorarioBloque::class, 'horario_bloque_id');
    }

    public function esImagen(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->nombre, PATHINFO_EXTENSION) ?: 'ARCH');
    }
}
