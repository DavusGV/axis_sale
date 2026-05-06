<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // nueva columna FK, nullable para no romper registros existentes
            $table->foreignId('unidad_medida_id')
                ->nullable()
                ->after('es_servicio')
                ->constrained('unidades_medidas')
                ->onDelete('set null');

            // eliminamos el campo texto anterior
            $table->dropColumn('unidad_medida');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['unidad_medida_id']);
            $table->dropColumn('unidad_medida_id');

            // restauramos el campo texto si se hace rollback
            $table->string('unidad_medida', 255)->default('unidad');
        });
    }
};
