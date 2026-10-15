<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los permisos de Reportes diarios no aparecían en la pantalla de roles, así que al
     * guardar un rol se borraban. Se devuelven según el seeder (solo se agregan, no se quita nada).
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['reportes_diarios.ver', 'reportes_diarios.crear', 'reportes_diarios.gestionar'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $asignar = [
            'super-admin'    => ['reportes_diarios.ver', 'reportes_diarios.crear', 'reportes_diarios.gestionar'],
            'administrativo' => ['reportes_diarios.ver', 'reportes_diarios.crear', 'reportes_diarios.gestionar'],
            'ventas'         => ['reportes_diarios.ver', 'reportes_diarios.crear'],
            'desarrollador'  => ['reportes_diarios.ver', 'reportes_diarios.crear'],
            'practicante'    => ['reportes_diarios.ver', 'reportes_diarios.crear'],
        ];

        foreach ($asignar as $rol => $permisos) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo($permisos);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No se revierte: quitar permisos podría dejar sin acceso a quien ya los usa.
    }
};
