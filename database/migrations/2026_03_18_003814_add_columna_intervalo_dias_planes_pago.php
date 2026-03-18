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
        Schema::table('planes_pago', function (Blueprint $table) {
            $table->integer('intervalo_dias')->nullable()->after('tipo_plazo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planes_pago', function (Blueprint $table) {
            $table->dropColumn('intervalo_dias');
        });
    }
};
