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
        // Quitamos los campos de descuento de ventas
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['tipo_descuento', 'descuento', 'descuento_aplicado']);
        });

        // Agregamos los campos de descuento por producto en venta_detalles
        Schema::table('venta_detalles', function (Blueprint $table) {
            // tipo_descuento: 'porcentaje' o 'cantidad'
            $table->string('tipo_descuento')->nullable()->after('subtotal');
            // descuento: el valor ingresado (ej: 20 para 20% o $20)
            $table->decimal('descuento', 10, 2)->default(0)->after('tipo_descuento');
            // descuento_aplicado: lo que realmente se desconto en dinero
            $table->decimal('descuento_aplicado', 10, 2)->default(0)->after('descuento');
        });
    }

    /**
     * Reverse the migrations.
     */
     public function down(): void
    {
        // Revertimos venta_detalles
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn(['tipo_descuento', 'descuento', 'descuento_aplicado']);
        });

        // Revertimos ventas
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_descuento')->nullable()->after('metodo_pago');
            $table->integer('descuento')->default(0)->after('tipo_descuento');
            $table->decimal('descuento_aplicado', 10, 2)->default(0)->after('descuento');
        });
    }
};
