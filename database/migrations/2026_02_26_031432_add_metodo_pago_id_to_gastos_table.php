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

            $table->unsignedBigInteger('metodo_pago_id')
                  ->after('tipo_gasto_id'); // ajusta la posición si quieres

            $table->foreign('metodo_pago_id')
                  ->references('id')
                  ->on('metodos_pago')
                  ->onDelete('restrict'); // 🔒 IMPORTANTE
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropForeign(['metodo_pago_id']);
            $table->dropColumn('metodo_pago_id');
        });
    }
};
