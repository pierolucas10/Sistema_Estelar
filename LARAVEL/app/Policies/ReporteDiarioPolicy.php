<?php

namespace App\Policies;

use App\Models\ReporteDiario;
use App\Models\User;

class ReporteDiarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['reportes_diarios.ver', 'reportes_diarios.gestionar', 'reportes_diarios.revisar']);
    }

    public function view(User $user, ReporteDiario $reporte): bool
    {
        if ($user->hasAnyPermission(['reportes_diarios.gestionar', 'reportes_diarios.revisar'])) {
            return true;
        }
        return $user->hasAnyPermission(['reportes_diarios.ver', 'reportes_diarios.crear']) && $reporte->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('reportes_diarios.crear');
    }

    /** Gerencia: revisado / observado / rechazado. */
    public function revisar(User $user, ReporteDiario $reporte): bool
    {
        return $user->hasPermissionTo('reportes_diarios.revisar') && $reporte->estado_revision !== 'rechazado';
    }

    /** Solo el autor, y solo si gerencia lo observó. */
    public function subsanar(User $user, ReporteDiario $reporte): bool
    {
        return $reporte->user_id === $user->id && $reporte->debeSubsanar();
    }

    public function delete(User $user, ReporteDiario $reporte): bool
    {
        // Un reporte ya revisado o rechazado por gerencia no se borra (queda como constancia).
        if (in_array($reporte->estado_revision, ['revisado', 'rechazado'], true)
            && !$user->hasPermissionTo('reportes_diarios.gestionar')) {
            return false;
        }
        if ($user->hasPermissionTo('reportes_diarios.gestionar')) {
            return true;
        }
        return $reporte->user_id === $user->id;
    }
}
