<?php

namespace App\Http\Controllers\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Project;
use App\Models\ProjectInstallment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Pagos del proyecto (rol administrativo): monto del contrato, cuotas y vouchers.
 * Los vouchers se guardan en el disco privado y se sirven solo a quien tiene proyectos.finanzas.
 */
class ProyectoFinanzasController extends Controller
{
    private const VOUCHER_REGLAS = ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'];

    public function updateContrato(Request $request, Project $proyecto)
    {
        $data = $request->validate([
            'monto_contrato' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'moneda'         => ['required', Rule::in(['PEN', 'USD'])],
        ]);

        $proyecto->update($data);

        return $this->volver($proyecto, 'Datos del contrato actualizados.');
    }

    public function storeCuota(Request $request, Project $proyecto)
    {
        $data = $request->validate([
            'nombre'            => ['required', 'string', 'max:150'],
            'monto'             => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'notas'             => ['nullable', 'string', 'max:1000'],
        ]);

        $proyecto->cuotas()->create($data + [
            'estado' => 'pendiente',
            'orden'  => (int) $proyecto->cuotas()->max('orden') + 1,
        ]);

        return $this->volver($proyecto, 'Cuota agregada.');
    }

    /**
     * Reparte el monto del contrato en N cuotas iguales o con un esquema (50/50, 40/30/30).
     * Solo se usa cuando el proyecto aún no tiene cuotas.
     */
    public function generarPlan(Request $request, Project $proyecto)
    {
        $data = $request->validate([
            'esquema'      => ['required', Rule::in(['50-50', '40-30-30', 'iguales'])],
            'cantidad'     => ['required_if:esquema,iguales', 'nullable', 'integer', 'min:2', 'max:24'],
            'primera_fecha' => ['nullable', 'date'],
            'cada_dias'    => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if (!$proyecto->monto_contrato) {
            return $this->volver($proyecto, 'Primero define el monto del contrato.', 'error');
        }
        if ($proyecto->cuotas()->exists()) {
            return $this->volver($proyecto, 'El proyecto ya tiene cuotas. Elimínalas o agrégalas una por una.', 'error');
        }

        $porcentajes = match ($data['esquema']) {
            '50-50'    => [50, 50],
            '40-30-30' => [40, 30, 30],
            default    => array_fill(0, (int) $data['cantidad'], 100 / (int) $data['cantidad']),
        };

        $total = (float) $proyecto->monto_contrato;
        $fecha = !empty($data['primera_fecha']) ? \Illuminate\Support\Carbon::parse($data['primera_fecha']) : null;
        $cada  = (int) ($data['cada_dias'] ?? 30);
        $n     = count($porcentajes);
        $acumulado = 0;

        DB::transaction(function () use ($proyecto, $porcentajes, $total, $fecha, $cada, $n, &$acumulado) {
            foreach ($porcentajes as $i => $pct) {
                // La última cuota absorbe el redondeo para que la suma cuadre exacto.
                $monto = $i === $n - 1 ? round($total - $acumulado, 2) : round($total * $pct / 100, 2);
                $acumulado += $monto;

                $proyecto->cuotas()->create([
                    'nombre'            => $i === 0 ? 'Adelanto' : ($i === $n - 1 ? 'Cuota final' : 'Cuota ' . ($i + 1)),
                    'monto'             => $monto,
                    'fecha_vencimiento' => $fecha?->copy()->addDays($cada * $i),
                    'estado'            => 'pendiente',
                    'orden'             => $i + 1,
                ]);
            }
        });

        return $this->volver($proyecto, "Se generaron {$n} cuotas.");
    }

    public function updateCuota(Request $request, Project $proyecto, ProjectInstallment $cuota)
    {
        $this->asegurarCuota($proyecto, $cuota);

        $data = $request->validate([
            'nombre'            => ['required', 'string', 'max:150'],
            'monto'             => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'notas'             => ['nullable', 'string', 'max:1000'],
        ]);

        if ($cuota->pagada() && (float) $data['monto'] !== (float) $cuota->monto) {
            return $this->volver($proyecto, 'No se puede cambiar el monto de una cuota pagada. Revierte el pago primero.', 'error');
        }

        $cuota->update($data);

        return $this->volver($proyecto, 'Cuota actualizada.');
    }

    /** Registra el pago: guarda el voucher, marca la cuota y crea el ingreso en Caja. */
    public function pagar(Request $request, Project $proyecto, ProjectInstallment $cuota)
    {
        $this->asegurarCuota($proyecto, $cuota);

        if ($cuota->pagada()) {
            return $this->volver($proyecto, 'Esa cuota ya está pagada.', 'error');
        }

        $data = $request->validate([
            'fecha_pago'  => ['required', 'date', 'before_or_equal:today'],
            'metodo_pago' => ['required', Rule::in(array_keys(CashMovement::METODOS_PAGO))],
            'referencia'  => ['nullable', 'string', 'max:120'],
            'notas'       => ['nullable', 'string', 'max:1000'],
            'voucher'     => ['nullable', ...self::VOUCHER_REGLAS],
        ]);

        $archivo = $request->file('voucher');
        $path = $archivo?->store("proyectos/{$proyecto->id}/vouchers", 'local');

        DB::transaction(function () use ($proyecto, $cuota, $data, $archivo, $path) {
            $movimiento = CashMovement::create([
                'tipo'        => 'ingreso',
                'concepto'    => "Cobro {$cuota->nombre} — {$proyecto->name}",
                'monto'       => $cuota->monto,
                'moneda'      => $proyecto->moneda ?? 'PEN',
                'metodo_pago' => $data['metodo_pago'],
                'referencia'  => $data['referencia'] ?? null,
                'fecha'       => $data['fecha_pago'],
                'categoria'   => 'cobro_cliente',
                'client_id'   => $proyecto->client_id,
                'quote_id'    => $proyecto->quote_id,
                'user_id'     => auth()->id(),
                'notas'       => $data['notas'] ?? null,
            ]);

            $cuota->update([
                'estado'           => 'pagada',
                'fecha_pago'       => $data['fecha_pago'],
                'metodo_pago'      => $data['metodo_pago'],
                'referencia'       => $data['referencia'] ?? null,
                'notas'            => $data['notas'] ?? $cuota->notas,
                'voucher_path'     => $path,
                'voucher_nombre'   => $archivo?->getClientOriginalName(),
                'cash_movement_id' => $movimiento->id,
                'registrado_por'   => auth()->id(),
            ]);
        });

        return $this->volver($proyecto, "Pago de «{$cuota->nombre}» registrado y enviado a Caja.");
    }

    /** Sube o reemplaza el voucher de una cuota ya pagada. */
    public function subirVoucher(Request $request, Project $proyecto, ProjectInstallment $cuota)
    {
        $this->asegurarCuota($proyecto, $cuota);
        $request->validate(['voucher' => ['required', ...self::VOUCHER_REGLAS]]);

        if ($cuota->voucher_path) {
            Storage::disk('local')->delete($cuota->voucher_path);
        }

        $archivo = $request->file('voucher');
        $cuota->update([
            'voucher_path'   => $archivo->store("proyectos/{$proyecto->id}/vouchers", 'local'),
            'voucher_nombre' => $archivo->getClientOriginalName(),
        ]);

        return $this->volver($proyecto, 'Voucher guardado.');
    }

    public function verVoucher(Project $proyecto, ProjectInstallment $cuota)
    {
        $this->asegurarCuota($proyecto, $cuota);
        abort_unless($cuota->voucher_path && Storage::disk('local')->exists($cuota->voucher_path), 404);

        return Storage::disk('local')->response($cuota->voucher_path, $cuota->voucher_nombre, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function revertir(Project $proyecto, ProjectInstallment $cuota)
    {
        $this->asegurarCuota($proyecto, $cuota);

        DB::transaction(function () use ($cuota) {
            if ($cuota->cash_movement_id) {
                CashMovement::find($cuota->cash_movement_id)?->delete();
            }
            if ($cuota->voucher_path) {
                Storage::disk('local')->delete($cuota->voucher_path);
            }
            $cuota->update([
                'estado'           => 'pendiente',
                'fecha_pago'       => null,
                'metodo_pago'      => null,
                'referencia'       => null,
                'voucher_path'     => null,
                'voucher_nombre'   => null,
                'cash_movement_id' => null,
                'registrado_por'   => null,
            ]);
        });

        return $this->volver($proyecto, 'Pago revertido: la cuota volvió a pendiente y se quitó de Caja.');
    }

    public function destroyCuota(Project $proyecto, ProjectInstallment $cuota)
    {
        $this->asegurarCuota($proyecto, $cuota);

        if ($cuota->pagada()) {
            return $this->volver($proyecto, 'No se puede eliminar una cuota pagada. Revierte el pago primero.', 'error');
        }

        $cuota->delete();

        return $this->volver($proyecto, 'Cuota eliminada.');
    }

    private function asegurarCuota(Project $proyecto, ProjectInstallment $cuota): void
    {
        abort_unless($cuota->project_id === $proyecto->id, 404);
    }

    private function volver(Project $proyecto, string $mensaje, string $tipo = 'success')
    {
        return redirect()
            ->to(route('proyectos.show', [$proyecto, 'vista' => 'finanzas']) . '#pagos')
            ->with($tipo, $mensaje);
    }
}
