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
            $table->string('impresora_ticket', 255)->nullable()->after('impresora_alto');
            $table->boolean('impresion_automatica')->default(false)->after('impresora_ticket');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracion_establecimiento', function (Blueprint $table) {
            $table->dropColumn('impresora_ticket');
            $table->dropColumn('impresion_automatica');
        });
    }
};
