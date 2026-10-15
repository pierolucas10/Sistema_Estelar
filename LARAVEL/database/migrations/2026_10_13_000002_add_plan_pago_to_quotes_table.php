<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forma de pago de la cotización: [{concepto, porcentaje}], p. ej. 50% al inicio + 50% al finalizar.
        // Al convertir en contrato se usa para crear las cuotas del proyecto.
        Schema::table('quotes', function (Blueprint $table) {
            $table->json('plan_pago')->nullable()->after('precio_etiqueta');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('plan_pago');
        });
    }
};
