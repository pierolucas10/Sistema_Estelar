<?php

namespace App\Livewire\Proyectos;

use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

class ProyectosList extends Component
{
    use WithPagination;

    public string $search  = '';
    public string $status  = '';
    public string $cliente = '';

    public function updatingSearch(): void  { $this->resetPage(); }
    public function updatingStatus(): void  { $this->resetPage(); }
    public function updatingCliente(): void { $this->resetPage(); }

    public function render()
    {
        $user = auth()->user();

        // Practicante: solo ve sus proyectos asignados
        $soloAsignados = !$user->can('proyectos.ver') && $user->can('proyectos.ver_asignados');

        // Administración ve el % pagado; desarrollo ve en qué paso va cada proyecto.
        $relaciones = ['client', 'responsible'];
        if ($user->can('proyectos.finanzas')) {
            $relaciones[] = 'cuotas';
        }
        if ($user->can('proyectos.desarrollo')) {
            array_push($relaciones, 'requirements:id,project_id,status', 'reuniones.archivos', 'archivos');
        }

        $proyectos = Project::with($relaciones)
            ->when($soloAsignados,    fn($q) => $q->asignadoA($user->id))
            ->when($this->search,     fn($q) => $q->search($this->search))
            ->when($this->status,     fn($q) => $q->where('status', $this->status))
            ->when($this->cliente,    fn($q) => $q->where('client_id', $this->cliente))
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('livewire.proyectos.proyectos-list', compact('proyectos'));
    }
}
