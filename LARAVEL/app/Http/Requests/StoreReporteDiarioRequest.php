<?php

namespace App\Http\Requests;

use App\Models\HorarioBloque;
use App\Models\ReporteDiario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReporteDiarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ReporteDiario::class);
    }

    public function rules(): array
    {
        return [
            'area'                 => ['required', Rule::in(array_keys(ReporteDiario::AREAS))],
            // Es un reporte diario: uno por persona y por día, nunca de días futuros.
            'fecha'                => [
                'required', 'date', 'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    $existe = ReporteDiario::where('user_id', $this->user()->id)->whereDate('fecha', $value)->exists();
                    if ($existe) {
                        $fail('Ya registraste tu reporte de ese día. El reporte es uno por día.');
                    }
                },
            ],
            'proyectos_asignados'  => ['nullable', 'string', 'max:255'],
            'sprint_iteracion'     => ['nullable', 'string', 'max:255'],
            'modulo_componente'    => ['nullable', 'string', 'max:255'],
            'horas_trabajadas'     => ['required', 'numeric', 'min:0.5', 'max:24'],

            // Si el día tiene horario, los bloques reemplazan a las tareas sueltas (que pasan a ser opcionales).
            'tareas'               => [$this->filled('bloques') ? 'nullable' : 'required', 'array', $this->filled('bloques') ? 'min:0' : 'min:1'],
            'tareas.*.descripcion' => ['required', 'string', 'max:500'],
            'tareas.*.tipo'        => ['required', Rule::in(ReporteDiario::TIPOS_TAREA)],
            'tareas.*.estado'      => ['required', Rule::in(ReporteDiario::ESTADOS_TAREA)],
            'tareas.*.tiempo_horas'=> ['required', 'numeric', 'min:0.1', 'max:24'],

            'logros_destacados'    => ['nullable', 'string', 'max:2000'],

            'impedimentos'              => ['nullable', 'array'],
            'impedimentos.*.descripcion'=> ['required_with:impedimentos', 'string', 'max:500'],
            'impedimentos.*.impacto'    => ['required_with:impedimentos', Rule::in(ReporteDiario::IMPACTOS)],
            'impedimentos.*.requiere_apoyo' => ['required_with:impedimentos', 'boolean'],

            'plan_siguiente_dia'   => ['nullable', 'string', 'max:2000'],
            'archivo_adjunto'      => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            // Cumplimiento del horario: un registro por bloque del día
            'bloques'                   => ['nullable', 'array'],
            'bloques.*.estado'          => ['required', Rule::in(['cumplido', 'parcial', 'no_cumplido'])],
            'bloques.*.detalle'         => ['nullable', 'required_if:bloques.*.estado,cumplido,parcial', 'string', 'max:3000'],
            'bloques.*.justificacion'   => ['nullable', 'required_if:bloques.*.estado,parcial,no_cumplido', 'string', 'max:2000'],
            'bloques.*.archivos'        => ['nullable', 'array', 'max:8'],
            'bloques.*.archivos.*'      => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt'],
        ];
    }

    /** Todos los bloques del horario de ese día deben reportarse, y solo los de esa persona. */
    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->has('fecha') || !$this->filled('fecha')) {
                return;
            }

            $delDia = HorarioBloque::deUsuario($this->user()->id)
                ->whereDate('fecha', $this->input('fecha'))
                ->where('estado', 'pendiente')
                ->pluck('id')->map(fn ($id) => (string) $id)->all();
            $enviados = array_map('strval', array_keys((array) $this->input('bloques', [])));

            if (array_diff($enviados, $delDia)) {
                $validator->errors()->add('bloques', 'Hay bloques que no pertenecen a tu horario de ese día o ya fueron reportados.');
            } elseif (array_diff($delDia, $enviados)) {
                $validator->errors()->add('bloques', 'Debes reportar todos los bloques de tu horario de ese día (' . count($delDia) . ').');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'tareas.required'              => 'Debes registrar al menos una tarea.',
            'tareas.min'                   => 'Debes registrar al menos una tarea.',
            'tareas.*.descripcion.required'=> 'La descripción de cada tarea es obligatoria.',
            'tareas.*.tipo.required'       => 'El tipo de cada tarea es obligatorio.',
            'tareas.*.estado.required'     => 'El estado de cada tarea es obligatorio.',
            'tareas.*.tiempo_horas.required'=> 'El tiempo de cada tarea es obligatorio.',
            'archivo_adjunto.max'          => 'El archivo no puede superar los 5 MB.',
            'bloques.*.detalle.required_if'       => 'Cuenta qué hiciste en cada bloque cumplido o parcial.',
            'bloques.*.justificacion.required_if' => 'Justifica por qué no se completó cada bloque parcial o no cumplido.',
            'bloques.*.archivos.*.max'            => 'Cada evidencia puede pesar hasta 10 MB.',
            'bloques.*.archivos.*.mimes'          => 'Las evidencias pueden ser fotos, PDF, Word, Excel, PowerPoint, ZIP o texto.',
            'fecha.unique'                 => 'Ya registraste tu reporte de ese día. El reporte es uno por día.',
            'fecha.before_or_equal'        => 'No puedes registrar el reporte de un día que aún no llega.',
        ];
    }
}
