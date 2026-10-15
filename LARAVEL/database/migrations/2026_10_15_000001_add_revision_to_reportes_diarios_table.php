<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Revisión de gerencia: revisado / observado (se subsana) / rechazado.
        Schema::table('reportes_diarios', function (Blueprint $table) {
            $table->enum('estado_revision', ['pendiente', 'revisado', 'observado', 'subsanado', 'rechazado'])
                ->default('pendiente')->after('archivo_adjunto');
            $table->text('observacion_revision')->nullable()->after('estado_revision');
            $table->foreignId('revisado_por')->nullable()->after('observacion_revision')->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable()->after('revisado_por');
            $table->timestamp('subsanado_at')->nullable()->after('revisado_at');

            $table->index('estado_revision');
        });

        // Historial: cada revisión y cada subsanación queda registrada.
        Schema::create('reporte_revisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_diario_id')->constrained('reportes_diarios')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('accion', ['revisado', 'observado', 'rechazado', 'subsanado']);
            $table->text('comentario')->nullable();
            $table->timestamps();
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $permiso = Permission::firstOrCreate(['name' => 'reportes_diarios.revisar', 'guard_name' => 'web']);
        Role::where('name', 'super-admin')->where('guard_name', 'web')->first()?->givePermissionTo($permiso);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_revisiones');
        Schema::table('reportes_diarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropIndex(['estado_revision']);
            $table->dropColumn(['estado_revision', 'observacion_revision', 'revisado_at', 'subsanado_at']);
        });
        Permission::where('name', 'reportes_diarios.revisar')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
