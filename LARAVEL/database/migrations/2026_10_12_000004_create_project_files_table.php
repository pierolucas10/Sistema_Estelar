<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Archivos del proyecto (contrato, documentación, actas firmadas, otros).
        // Se guardan en el disco privado y solo se descargan con sesión iniciada.
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained('project_meetings')->nullOnDelete();
            $table->enum('categoria', ['contrato', 'documentacion', 'acta', 'otro'])->default('otro');
            $table->string('nombre', 255);
            $table->string('path', 500);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};
