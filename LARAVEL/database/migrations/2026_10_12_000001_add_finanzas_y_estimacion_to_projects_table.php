<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Finanzas (rol administrativo)
            $table->decimal('monto_contrato', 12, 2)->nullable()->after('progress');
            $table->char('moneda', 3)->default('PEN')->after('monto_contrato');

            // Paso 2 del flujo de desarrollo: estimación de tiempo
            $table->unsignedSmallInteger('estimacion_dias')->nullable()->after('end_date');
            $table->date('estimacion_fecha_fin')->nullable()->after('estimacion_dias');
            $table->text('estimacion_detalle')->nullable()->after('estimacion_fecha_fin');
            $table->foreignId('estimado_por')->nullable()->after('estimacion_detalle')->constrained('users')->nullOnDelete();
            $table->timestamp('estimado_at')->nullable()->after('estimado_por');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estimado_por');
            $table->dropColumn([
                'monto_contrato', 'moneda',
                'estimacion_dias', 'estimacion_fecha_fin', 'estimacion_detalle', 'estimado_at',
            ]);
        });
    }
};
