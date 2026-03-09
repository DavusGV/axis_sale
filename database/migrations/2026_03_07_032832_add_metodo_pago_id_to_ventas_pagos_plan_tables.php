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
        // agregamos metodo_pago_id en ventas
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('metodo_pago_id')
                  ->nullable()
                  ->after('metodo_pago')
                  ->comment('FK a metodos_pago, nullable para compatibilidad con registros anteriores');

            $table->foreign('metodo_pago_id')
                  ->references('id')
                  ->on('metodos_pago')
                  ->nullOnDelete();
        });

        // agregamos metodo_pago_id en pagos_plan
        Schema::table('pagos_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('metodo_pago_id')
                  ->nullable()
                  ->after('metodo_pago')
                  ->comment('FK a metodos_pago, nullable para compatibilidad con registros anteriores');

            $table->foreign('metodo_pago_id')
                  ->references('id')
                  ->on('metodos_pago')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['metodo_pago_id']);
            $table->dropColumn('metodo_pago_id');
        });

        Schema::table('pagos_plan', function (Blueprint $table) {
            $table->dropForeign(['metodo_pago_id']);
            $table->dropColumn('metodo_pago_id');
        });
    }
};
