<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInstallment extends Model
{
    protected $fillable = [
        'project_id', 'nombre', 'monto', 'fecha_vencimiento', 'estado',
        'fecha_pago', 'metodo_pago', 'referencia',
        'voucher_path', 'voucher_nombre', 'notas',
        'cash_movement_id', 'registrado_por', 'orden',
    ];

    protected $casts = [
        'monto'             => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_pago'        => 'date',
    ];

    public function project(): BelongsTo       { return $this->belongsTo(Project::class); }
    public function registradoPor(): BelongsTo { return $this->belongsTo(User::class, 'registrado_por'); }
    public function cashMovement(): BelongsTo  { return $this->belongsTo(CashMovement::class); }

    public function pagada(): bool
    {
        return $this->estado === 'pagada';
    }

    public function vencida(): bool
    {
        return !$this->pagada()
            && $this->fecha_vencimiento
            && $this->fecha_vencimiento->lt(today());
    }

    /** Pendiente que vence en los próximos 7 días. */
    public function porVencer(): bool
    {
        return !$this->pagada()
            && $this->fecha_vencimiento
            && $this->fecha_vencimiento->between(today(), today()->addDays(7));
    }

    public function estadoVisual(): string
    {
        return match (true) {
            $this->pagada()    => 'pagada',
            $this->vencida()   => 'vencida',
            $this->porVencer() => 'por_vencer',
            default            => 'pendiente',
        };
    }

    public function estadoLabel(): string
    {
        return match ($this->estadoVisual()) {
            'pagada'     => 'Pagada',
            'vencida'    => 'Vencida',
            'por_vencer' => 'Por vencer',
            default      => 'Pendiente',
        };
    }

    public function metodoLabel(): string
    {
        return CashMovement::METODOS_PAGO[$this->metodo_pago] ?? (string) $this->metodo_pago;
    }

    public function voucherEsImagen(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', (string) $this->voucher_nombre);
    }
}
