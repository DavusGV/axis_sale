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
        Schema::table('venta_detalles', function (Blueprint $table) {
            // porcentaje de iva que tenia el producto al momento de registrar la venta
            // se guarda aqui porque el producto puede cambiar su iva despues
            $table->decimal('iva_porcentaje', 5, 2)->nullable()->after('descuento_aplicado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn('iva_porcentaje');
        });
    }
};
