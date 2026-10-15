<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campos del modelo de cotización ESTELAR (portada, servicio, S/N, tipo de visita,
        // descripción del sistema en viñetas y precio único) + vigencia y seguimiento.
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('titulo', 150)->default('IMPLEMENTACION ADMINISTRATIVA')->after('status');
            $table->string('servicio', 150)->default('IMPLEMENTACION ESTELAR')->after('titulo');
            $table->string('numero_serie', 30)->nullable()->after('servicio');
            $table->string('tipo_visita', 60)->default('PLAN MENSUAL')->after('numero_serie');
            $table->string('modalidad', 40)->default('Fixed Price')->after('tipo_visita');
            $table->json('caracteristicas')->nullable()->after('modalidad');
            $table->string('precio_etiqueta', 40)->default('PRECIO ÚNICO')->after('caracteristicas');
            $table->unsignedSmallInteger('vigencia_dias')->default(5)->after('fecha_vencimiento');
            $table->date('proximo_seguimiento')->nullable()->after('vigencia_dias');
            $table->string('motivo_rechazo', 255)->nullable()->after('accepted_at');
            $table->timestamp('convertida_at')->nullable()->after('motivo_rechazo');
        });

        // Historial de seguimiento comercial (llamadas, WhatsApp, correos, reuniones)
        Schema::create('quote_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo', ['llamada', 'whatsapp', 'correo', 'reunion', 'nota'])->default('llamada');
            $table->text('nota');
            $table->date('proximo_seguimiento')->nullable();
            $table->timestamps();

            $table->index(['quote_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_followups');
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'titulo', 'servicio', 'numero_serie', 'tipo_visita', 'modalidad',
                'caracteristicas', 'precio_etiqueta', 'vigencia_dias', 'proximo_seguimiento',
                'motivo_rechazo', 'convertida_at',
            ]);
        });
    }
};
