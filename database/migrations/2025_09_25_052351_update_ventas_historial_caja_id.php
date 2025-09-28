<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateVentasHistorialCajaId extends Migration
{
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            // 1. Elimina la foreign key y columna caja_id si existe
            if (Schema::hasColumn('ventas', 'caja_id')) {
                $table->dropForeign(['caja_id']);
                $table->dropColumn('caja_id');
            }
            // 2. Agrega el campo correcto
            $table->foreignId('historial_caja_id')
                ->nullable()
                ->after('establecimiento_id')
                ->constrained('historial_cajas');
        });
    }

    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Reviertes los cambios
            $table->dropForeign(['historial_caja_id']);
            $table->dropColumn('historial_caja_id');
            $table->foreignId('caja_id')->nullable()->constrained();
        });
    }
}
