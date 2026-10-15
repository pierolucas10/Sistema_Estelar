<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCotizacionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('cotizaciones.crear'); }

    protected function prepareForValidation(): void
    {
        // Las viñetas vacías del formulario no cuentan.
        $this->merge([
            'caracteristicas' => array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), (array) $this->input('caracteristicas', [])),
                fn ($v) => $v !== ''
            )),
            'incluye_igv' => $this->boolean('incluye_igv'),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_id'           => ['required', 'exists:clients,id'],
            'titulo'              => ['required', 'string', 'max:150'],
            'servicio'            => ['required', 'string', 'max:150'],
            'numero_serie'        => ['nullable', 'string', 'max:30'],
            'tipo_visita'         => ['required', 'string', 'max:60'],
            'modalidad'           => ['nullable', 'string', 'max:40'],
            'caracteristicas'     => ['required', 'array', 'min:1', 'max:80'],
            'caracteristicas.*'   => ['string', 'max:1000'],
            'precio_etiqueta'     => ['required', 'string', 'max:40'],
            'plan_pago'              => ['required', 'array', 'min:1', 'max:12'],
            'plan_pago.*.concepto'   => ['required', 'string', 'max:100'],
            'plan_pago.*.porcentaje' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'total'               => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'moneda'              => ['required', Rule::in(['PEN', 'USD'])],
            'incluye_igv'         => ['boolean'],
            'fecha_emision'       => ['required', 'date'],
            'vigencia_dias'       => ['required', 'integer', 'min:1', 'max:365'],
            'proximo_seguimiento' => ['nullable', 'date'],
            'terminos'            => ['nullable', 'string', 'max:30000'],
            'notas'               => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $suma = collect($this->input('plan_pago', []))->sum(fn ($p) => (float) ($p['porcentaje'] ?? 0));
            if ($this->filled('plan_pago') && abs($suma - 100) > 0.01) {
                $validator->errors()->add('plan_pago', 'La forma de pago debe sumar 100% (ahora suma ' . rtrim(rtrim(number_format($suma, 2), '0'), '.') . '%).');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'plan_pago.required'            => 'Indica la forma de pago.',
            'plan_pago.*.concepto.required' => 'Cada parte de la forma de pago necesita un concepto.',
            'caracteristicas.required' => 'Agrega al menos un punto en «Descripción del sistema web».',
            'caracteristicas.min'      => 'Agrega al menos un punto en «Descripción del sistema web».',
        ];
    }
}
