<?php

namespace App\Models;

use App\Support\CotizacionModelo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Quote extends Model
{
    use SoftDeletes;

    const ESTADOS = ['borrador', 'enviado', 'aceptado', 'rechazado', 'facturado'];

    const ESTADO_LABELS = [
        'borrador'  => 'Borrador',
        'enviado'   => 'Enviada',
        'aceptado'  => 'Contrato',
        'rechazado' => 'Rechazada',
        'facturado' => 'Facturada',
    ];

    /** Estados en los que la cotización sigue "viva" y necesita seguimiento. */
    const ABIERTAS = ['borrador', 'enviado'];

    const UNIDADES = ['servicio', 'hora', 'día', 'mes', 'unidad', 'licencia'];

    protected $fillable = [
        'numero', 'client_id', 'project_id', 'status',
        'titulo', 'servicio', 'numero_serie', 'tipo_visita', 'modalidad',
        'caracteristicas', 'precio_etiqueta', 'plan_pago',
        'fecha_emision', 'fecha_vencimiento', 'vigencia_dias', 'proximo_seguimiento',
        'moneda', 'tipo_cambio',
        'subtotal', 'igv', 'total', 'incluye_igv',
        'notas', 'terminos', 'created_by', 'sent_at', 'accepted_at',
        'motivo_rechazo', 'convertida_at',
    ];

    protected $casts = [
        'fecha_emision'       => 'date',
        'fecha_vencimiento'   => 'date',
        'proximo_seguimiento' => 'date',
        'incluye_igv'         => 'boolean',
        'caracteristicas'     => 'array',
        'plan_pago'           => 'array',
        'sent_at'             => 'datetime',
        'accepted_at'         => 'datetime',
        'convertida_at'       => 'datetime',
        'subtotal'            => 'decimal:2',
        'igv'                 => 'decimal:2',
        'total'               => 'decimal:2',
    ];

    // ── Relaciones ───────────────────────────────────────────────────
    public function client(): BelongsTo    { return $this->belongsTo(Client::class); }
    public function project(): BelongsTo   { return $this->belongsTo(Project::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany       { return $this->hasMany(QuoteItem::class)->orderBy('orden'); }
    public function payments(): HasMany    { return $this->hasMany(QuotePayment::class)->orderBy('orden'); }
    public function invoices(): HasMany    { return $this->hasMany(Invoice::class); }
    public function seguimientos(): HasMany { return $this->hasMany(QuoteFollowup::class)->latest(); }

    // ── Estado, vigencia y seguimiento ───────────────────────────────

    public function estaAbierta(): bool
    {
        return in_array($this->status, self::ABIERTAS, true);
    }

    public function estaConvertida(): bool
    {
        return $this->project_id !== null && $this->status === 'aceptado';
    }

    public function esEditable(): bool
    {
        return $this->estaAbierta();
    }

    public function estaVencida(): bool
    {
        return $this->estaAbierta()
            && $this->fecha_vencimiento
            && $this->fecha_vencimiento->lt(today());
    }

    /** Días que faltan para vencer (negativo si ya venció; null si no aplica). */
    public function diasParaVencer(): ?int
    {
        if (!$this->estaAbierta() || !$this->fecha_vencimiento) {
            return null;
        }
        return (int) today()->diffInDays($this->fecha_vencimiento, false);
    }

    public function seguimientoAtrasado(): bool
    {
        return $this->estaAbierta() && $this->proximo_seguimiento && $this->proximo_seguimiento->lt(today());
    }

    public function seguimientoHoy(): bool
    {
        return $this->estaAbierta() && $this->proximo_seguimiento && $this->proximo_seguimiento->isToday();
    }

    /** Estado para mostrar: agrega "vencida" sobre los estados guardados. */
    public function estadoVisual(): string
    {
        return $this->estaVencida() ? 'vencida' : $this->status;
    }

    public function statusLabel(): string
    {
        return $this->estaVencida() ? 'Vencida' : (self::ESTADO_LABELS[$this->status] ?? $this->status);
    }

    /** Color de la píldora en las vistas nuevas (sky, green, amber, red, violet). */
    public function estadoColor(): string
    {
        return match ($this->estadoVisual()) {
            'enviado'   => 'sky',
            'aceptado'  => 'green',
            'rechazado' => 'red',
            'vencida'   => 'amber',
            'facturado' => 'violet',
            default     => '',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->estadoVisual()) {
            'borrador'  => 'bg-slate-700/60 text-slate-400',
            'enviado'   => 'bg-sky-500/10 text-sky-400 ring-1 ring-sky-500/20',
            'aceptado'  => 'bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20',
            'rechazado' => 'bg-red-500/10 text-red-400 ring-1 ring-red-500/20',
            'facturado' => 'bg-violet-500/10 text-violet-400 ring-1 ring-violet-500/20',
            'vencida'   => 'bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/20',
            default     => 'bg-slate-700/60 text-slate-400',
        };
    }

    /**
     * Alertas de seguimiento comercial.
     *
     * @return array<int, array{nivel: string, texto: string}>
     */
    public function alertas(): array
    {
        if (!$this->estaAbierta()) {
            return [];
        }

        $alertas = [];
        $dias = $this->diasParaVencer();

        if ($dias !== null) {
            if ($dias < 0) {
                $alertas[] = ['nivel' => 'danger', 'texto' => 'Venció hace ' . abs($dias) . ' día(s). Renueva la vigencia o márcala como rechazada.'];
            } elseif ($dias === 0) {
                $alertas[] = ['nivel' => 'danger', 'texto' => 'Vence hoy. Llama al cliente para cerrar o renovar.'];
            } elseif ($dias <= 2) {
                $alertas[] = ['nivel' => 'warning', 'texto' => "Vence en {$dias} día(s) (" . $this->fecha_vencimiento->format('d/m') . ').'];
            }
        }

        if ($this->seguimientoAtrasado()) {
            $alertas[] = ['nivel' => 'warning', 'texto' => 'Seguimiento atrasado desde el ' . $this->proximo_seguimiento->format('d/m') . '.'];
        } elseif ($this->seguimientoHoy()) {
            $alertas[] = ['nivel' => 'info', 'texto' => 'Toca hacer seguimiento hoy.'];
        }

        if ($this->status === 'enviado' && $this->sent_at && $this->sent_at->lt(now()->subDays(2))
            && !$this->seguimientos()->exists()) {
            $alertas[] = ['nivel' => 'warning', 'texto' => 'Se envió hace ' . (int) $this->sent_at->diffInDays(now()) . ' días y aún no tiene ningún seguimiento.'];
        }

        if ($this->status === 'borrador' && $this->created_at && $this->created_at->lt(now()->subDay())) {
            $alertas[] = ['nivel' => 'info', 'texto' => 'Sigue en borrador: envíala al cliente y márcala como enviada.'];
        }

        return $alertas;
    }

    // ── Contenido del modelo ESTELAR ─────────────────────────────────

    /** @return string[] */
    public function vinetas(): array
    {
        return array_values(array_filter(array_map('trim', $this->caracteristicas ?? [])));
    }

    /**
     * Forma de pago con el monto de cada parte. La última absorbe el redondeo para que sume exacto.
     *
     * @return array<int, array{concepto: string, porcentaje: float, monto: float}>
     */
    public function planPago(): array
    {
        $plan = collect($this->plan_pago ?: [['concepto' => 'Pago único', 'porcentaje' => 100]])
            ->filter(fn ($p) => filled($p['concepto'] ?? null) && (float) ($p['porcentaje'] ?? 0) > 0)
            ->values();

        $total = (float) $this->total;
        $acumulado = 0.0;

        return $plan->map(function ($p, $i) use ($plan, $total, &$acumulado) {
            $monto = $i === $plan->count() - 1
                ? round($total - $acumulado, 2)
                : round($total * (float) $p['porcentaje'] / 100, 2);
            $acumulado += $monto;

            return ['concepto' => (string) $p['concepto'], 'porcentaje' => (float) $p['porcentaje'], 'monto' => $monto];
        })->all();
    }

    /** @return array<int, array{titulo: string, texto: string}> */
    public function clausulas(): array
    {
        return filled($this->terminos)
            ? CotizacionModelo::parsearTerminos($this->terminos)
            : CotizacionModelo::terminos((int) ($this->vigencia_dias ?: 5));
    }

    // ── Helpers de cobros (plan antiguo; los pagos ahora viven en el proyecto) ──
    public function montoCobrado(): float
    {
        return (float) $this->payments()->where('estado', 'pagada')->sum('monto');
    }

    public function montoPendiente(): float
    {
        return (float) $this->total - $this->montoCobrado();
    }

    public function tienePlanCobros(): bool
    {
        return $this->payments()->exists();
    }

    public function porcentajeCobrado(): int
    {
        return $this->total > 0
            ? (int) round($this->montoCobrado() / (float) $this->total * 100)
            : 0;
    }

    public function generarPlanDefault(): void
    {
        if ($this->tienePlanCobros()) return;

        $cuotas = [
            ['nombre' => 'Anticipo',     'porcentaje' => 40, 'orden' => 1],
            ['nombre' => '2da cuota',    'porcentaje' => 30, 'orden' => 2],
            ['nombre' => 'Cuota final',  'porcentaje' => 30, 'orden' => 3],
        ];

        foreach ($cuotas as $cuota) {
            $this->payments()->create([
                'nombre'     => $cuota['nombre'],
                'porcentaje' => $cuota['porcentaje'],
                'monto'      => round((float) $this->total * $cuota['porcentaje'] / 100, 2),
                'orden'      => $cuota['orden'],
                'estado'     => 'pendiente',
            ]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function monedaSimbolo(): string
    {
        return $this->moneda === 'USD' ? '$' : 'S/';
    }

    public function montoFormateado(): string
    {
        return $this->monedaSimbolo() . ' ' . number_format((float) $this->total, 2);
    }

    /** Número correlativo de 8 dígitos (N° 00001133, 00001134…). */
    public static function generarNumero(): string
    {
        $ultimo = static::withTrashed()
            ->pluck('numero')
            ->filter(fn ($n) => ctype_digit((string) $n))
            ->map(fn ($n) => (int) $n)
            ->max();

        // Continúa la numeración de las cotizaciones hechas fuera del sistema (última: 00001132).
        return str_pad((string) max(1133, (int) $ultimo + 1), 8, '0', STR_PAD_LEFT);
    }

    /** S/N del servicio, p. ej. APF257801. */
    public static function generarNumeroSerie(): string
    {
        return 'APF' . random_int(100000, 999999);
    }

    public static function calcularVencimiento(string|Carbon $emision, int $dias): Carbon
    {
        return Carbon::parse($emision)->addDays(max(1, $dias));
    }

    // ── Scopes ───────────────────────────────────────────────────────
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('numero', 'like', "%{$term}%")
              ->orWhere('servicio', 'like', "%{$term}%")
              ->orWhere('titulo', 'like', "%{$term}%")
              ->orWhereHas('client', fn ($c) => $c->where('razon_social', 'like', "%{$term}%")
                  ->orWhere('nombre_comercial', 'like', "%{$term}%")
                  ->orWhere('numero_documento', 'like', "%{$term}%"));
        });
    }

    public function scopeAbiertas($query)
    {
        return $query->whereIn('status', self::ABIERTAS);
    }

    public function scopeVencidas($query)
    {
        return $query->abiertas()->whereDate('fecha_vencimiento', '<', today());
    }

    /** Abiertas con seguimiento para hoy o atrasado, o que vencen en 2 días. */
    public function scopeRequierenAtencion($query)
    {
        return $query->abiertas()->where(function ($q) {
            $q->whereDate('proximo_seguimiento', '<=', today())
              ->orWhereDate('fecha_vencimiento', '<=', today()->addDays(2));
        });
    }
}
