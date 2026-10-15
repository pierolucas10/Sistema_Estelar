<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cuotas de pago del proyecto (independientes de la cotización)
        Schema::create('project_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->decimal('monto', 12, 2);
            $table->date('fecha_vencimiento')->nullable();
            $table->enum('estado', ['pendiente', 'pagada'])->default('pendiente');

            $table->date('fecha_pago')->nullable();
            $table->string('metodo_pago', 30)->nullable();
            $table->string('referencia', 120)->nullable();
            $table->string('voucher_path', 500)->nullable();
            $table->string('voucher_nombre', 255)->nullable();
            $table->text('notas')->nullable();

            $table->unsignedBigInteger('cash_movement_id')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['project_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_installments');
    }
};
