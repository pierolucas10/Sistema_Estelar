<?php

namespace App\Http\Controllers\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Archivos del proyecto en disco privado.
 * - contrato: solo proyectos.finanzas
 * - documentacion / acta: proyectos.desarrollo (finanzas también puede verlos)
 * - otro: cualquiera de los dos
 */
class ProyectoArchivoController extends Controller
{
    public function store(Request $request, Project $proyecto)
    {
        $this->authorize('view', $proyecto);

        $data = $request->validate([
            'categoria'   => ['required', Rule::in(array_keys(ProjectFile::CATEGORIAS))],
            'archivos'    => ['required', 'array', 'max:10'],
            'archivos.*'  => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp,zip,rar,sql,md'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'meeting_id'  => ['nullable', Rule::exists('project_meetings', 'id')->where('project_id', $proyecto->id)],
            'vista'       => ['nullable', Rule::in(['finanzas', 'desarrollo'])],
        ]);

        abort_unless($this->puedeGestionar($data['categoria']), 403);

        foreach ($request->file('archivos') as $archivo) {
            $proyecto->archivos()->create([
                'categoria'   => $data['categoria'],
                'meeting_id'  => $data['meeting_id'] ?? null,
                'nombre'      => mb_substr($archivo->getClientOriginalName(), 0, 255),
                'path'        => $archivo->store("proyectos/{$proyecto->id}/{$data['categoria']}", 'local'),
                'mime'        => $archivo->getClientMimeType(),
                'size'        => $archivo->getSize(),
                'descripcion' => $data['descripcion'] ?? null,
                'subido_por'  => auth()->id(),
            ]);
        }

        $n = count($request->file('archivos'));

        return $this->volver($proyecto, $data['vista'] ?? null, $n === 1 ? 'Archivo subido.' : "{$n} archivos subidos.");
    }

    public function download(Project $proyecto, ProjectFile $archivo)
    {
        $this->authorize('view', $proyecto);
        abort_unless($archivo->project_id === $proyecto->id, 404);
        abort_unless($this->puedeVer($archivo->categoria), 403);
        abort_unless(Storage::disk('local')->exists($archivo->path), 404);

        // ?ver=1 abre en el navegador (PDF / imágenes); sin él, descarga.
        $disposicion = request()->boolean('ver') ? 'inline' : 'attachment';

        return Storage::disk('local')->response($archivo->path, $archivo->nombre, [
            'Cache-Control' => 'private, no-store',
        ], $disposicion);
    }

    public function destroy(Project $proyecto, ProjectFile $archivo)
    {
        $this->authorize('view', $proyecto);
        abort_unless($archivo->project_id === $proyecto->id, 404);
        abort_unless($this->puedeGestionar($archivo->categoria), 403);

        Storage::disk('local')->delete($archivo->path);
        $archivo->delete();

        return $this->volver($proyecto, request('vista'), 'Archivo eliminado.');
    }

    private function puedeGestionar(string $categoria): bool
    {
        $user = auth()->user();
        return match ($categoria) {
            'contrato'              => $user->can('proyectos.finanzas'),
            'documentacion', 'acta' => $user->can('proyectos.desarrollo'),
            default                 => $user->canAny(['proyectos.finanzas', 'proyectos.desarrollo']),
        };
    }

    private function puedeVer(string $categoria): bool
    {
        $user = auth()->user();
        return $categoria === 'contrato'
            ? $user->can('proyectos.finanzas')
            : $user->canAny(['proyectos.finanzas', 'proyectos.desarrollo']);
    }

    private function volver(Project $proyecto, ?string $vista, string $mensaje)
    {
        $ancla = $vista === 'desarrollo' ? '#paso-actual' : '#archivos';
        return redirect()
            ->to(route('proyectos.show', array_filter([$proyecto->id, 'vista' => $vista])) . $ancla)
            ->with('success', $mensaje);
    }
}
