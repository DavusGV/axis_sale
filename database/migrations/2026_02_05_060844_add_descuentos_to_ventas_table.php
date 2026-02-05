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
        Schema::table('ventas', function (Blueprint $table) {
            //
            $table->string('tipo_descuento')->nullable()->after('metodo_pago');
            $table->integer('descuento')->default(0)->after('tipo_descuento');
            $table->decimal('descuento_aplicado', 10, 2)->default(0)->after('descuento');
            $table->decimal('subtotal', 10, 2)->default(0)->after('descuento_aplicado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            //
            $table->dropColumn('tipo_descuento');
            $table->dropColumn('descuento');
            $table->dropColumn('descuento_aplicado');
            $table->dropColumn('subtotal');
        });
    }
};
