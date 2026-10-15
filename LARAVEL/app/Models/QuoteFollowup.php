<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteFollowup extends Model
{
    const TIPOS = [
        'llamada'  => 'Llamada',
        'whatsapp' => 'WhatsApp',
        'correo'   => 'Correo',
        'reunion'  => 'Reunión',
        'nota'     => 'Nota',
    ];

    protected $fillable = ['quote_id', 'user_id', 'tipo', 'nota', 'proximo_seguimiento'];

    protected $casts = [
        'proximo_seguimiento' => 'date',
    ];

    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
    public function user(): BelongsTo  { return $this->belongsTo(User::class); }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
