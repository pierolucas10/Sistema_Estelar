<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * proyectos.finanzas   -> % pagado, cuotas, vouchers, contrato y archivos del proyecto.
     * proyectos.desarrollo -> flujo por pasos: requerimientos, estimación, sprints y entrega.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $finanzas   = Permission::firstOrCreate(['name' => 'proyectos.finanzas',   'guard_name' => 'web']);
        $desarrollo = Permission::firstOrCreate(['name' => 'proyectos.desarrollo', 'guard_name' => 'web']);

        $asignar = [
            'super-admin'    => [$finanzas, $desarrollo],
            'administrativo' => [$finanzas],
            'desarrollador'  => [$desarrollo],
            'practicante'    => [$desarrollo],
        ];

        foreach ($asignar as $rol => $permisos) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo($permisos);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['proyectos.finanzas', 'proyectos.desarrollo'])->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
