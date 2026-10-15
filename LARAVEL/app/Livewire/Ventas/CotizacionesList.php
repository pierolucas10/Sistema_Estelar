<?php

namespace App\Livewire\Ventas;

use App\Models\Quote;
use Livewire\Component;
use Livewire\WithPagination;

class CotizacionesList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filtro = 'abiertas';

    protected $queryString = [
        'search' => ['except' => ''],
        'filtro' => ['except' => 'abiertas'],
    ];

    public const FILTROS = [
        'abiertas'  => 'Abiertas',
        'atencion'  => 'Seguimiento',
        'vencidas'  => 'Vencidas',
        'contratos' => 'Contratos',
        'rechazadas' => 'Rechazadas',
        'todas'     => 'Todas',
    ];

    public function updatingSearch(): void { $this->resetPage(); }

    public function setFiltro(string $filtro): void
    {
        $this->filtro = array_key_exists($filtro, self::FILTROS) ? $filtro : 'abiertas';
        $this->resetPage();
    }

    private function aplicarFiltro($query, string $filtro)
    {
        return match ($filtro) {
            'abiertas'   => $query->abiertas(),
            'atencion'   => $query->requierenAtencion(),
            'vencidas'   => $query->vencidas(),
            'contratos'  => $query->where('status', 'aceptado'),
            'rechazadas' => $query->where('status', 'rechazado'),
            default      => $query,
        };
    }

    public function render()
    {
        $quotes = $this->aplicarFiltro(Quote::with(['client', 'project'])->withCount('seguimientos'), $this->filtro)
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate(15);

        $conteos = collect(self::FILTROS)->keys()
            ->mapWithKeys(fn ($f) => [$f => $f === 'todas' ? null : $this->aplicarFiltro(Quote::query(), $f)->count()]);

        return view('livewire.ventas.cotizaciones-list', [
            'quotes'  => $quotes,
            'filtros' => self::FILTROS,
            'conteos' => $conteos,
        ]);
    }
}
