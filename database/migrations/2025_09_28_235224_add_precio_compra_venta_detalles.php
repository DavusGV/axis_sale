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
            //
               $table->decimal('precio_compra', 12, 2)->after('precio')->default(0)->comment('Precio de compra del producto en el momento de la venta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            //
            $table->dropColumn('precio_compra');
        });
    }
};
