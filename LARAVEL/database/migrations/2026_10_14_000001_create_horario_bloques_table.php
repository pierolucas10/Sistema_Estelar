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
        // Horario / cronograma: bloques de trabajo planificados (p. ej. 08:00–12:00 · Proyecto A).
        // El reporte diario rinde cuentas de cada bloque: cumplido, parcial o no cumplido (con justificación).
        Schema::create('horario_bloques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('actividad', 200);
            $table->text('descripcion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('estado', ['pendiente', 'cumplido', 'parcial', 'no_cumplido'])->default('pendiente');
            $table->text('detalle')->nullable();        // qué se hizo
            $table->text('justificacion')->nullable();  // por qué no se cumplió
            $table->foreignId('reporte_diario_id')->nullable()->constrained('reportes_diarios')->nullOnDelete();
            $table->timestamp('reportado_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fecha']);
            $table->index(['fecha', 'estado']);
        });

        // Evidencias de cada bloque (fotos y archivos)
        Schema::create('horario_bloque_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_bloque_id')->constrained('horario_bloques')->cascadeOnDelete();
            $table->string('nombre', 255);
            $table->string('path', 500);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        // Permiso para el botón «Crear horario». Se habilita por rol o por usuario en Roles y permisos.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $permiso = Permission::firstOrCreate(['name' => 'reportes_diarios.horario', 'guard_name' => 'web']);
        foreach (['super-admin', 'administrativo'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo($permiso);
        }
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('horario_bloque_archivos');
        Schema::dropIfExists('horario_bloques');
        Permission::where('name', 'reportes_diarios.horario')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
