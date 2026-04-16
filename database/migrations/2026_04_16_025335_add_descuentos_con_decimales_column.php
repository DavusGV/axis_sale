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
        Schema::table('configuracion_establecimiento', function (Blueprint $table) {
            $table->boolean('descuento_con_decimales')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracion_establecimiento', function (Blueprint $table) {
            $table->dropColumn('descuento_con_decimales');
        });
    }
};
