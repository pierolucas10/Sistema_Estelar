<?php

namespace App\Http\Requests\Ventas;

class UpdateCotizacionRequest extends StoreCotizacionRequest
{
    public function authorize(): bool { return $this->user()->can('cotizaciones.editar'); }
}
