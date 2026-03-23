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
        Schema::table('gastos', function (Blueprint $table) {
            $table->foreignId('historial_caja_id')
                ->nullable()
                ->after('metodo_pago_id')
                ->constrained('historial_cajas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropForeign(['historial_caja_id']);
            $table->dropColumn('historial_caja_id');
        });
    }
};
