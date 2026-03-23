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
        Schema::table('cotizaciones', function (Blueprint $table) {
            // quitamos el foreign key y la columna caja_id
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');

            // agregamos el constraint a historial_caja_id que ya existe como columna
            $table->foreign('historial_caja_id')
                ->references('id')
                ->on('historial_cajas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            // quitamos el constraint de historial_caja_id
            $table->dropForeign(['historial_caja_id']);

            // recreamos caja_id con su foreign key
            $table->unsignedBigInteger('caja_id')->nullable()->after('cliente_id');
            $table->foreign('caja_id')->references('id')->on('cajas');
        });
    }
};
