<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reuniones con acta (paso 1: toma de requerimientos)
        Schema::create('project_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['inicio', 'requerimientos', 'seguimiento', 'entrega'])->default('requerimientos');
            $table->string('titulo', 200);
            $table->dateTime('fecha');
            $table->string('asistentes', 500)->nullable();
            $table->text('acta')->nullable();       // temas tratados y acuerdos
            $table->text('pendientes')->nullable(); // compromisos y responsables
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_meetings');
    }
};
