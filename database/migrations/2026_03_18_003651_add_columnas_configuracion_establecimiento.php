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
            $table->string('formato_hora', 3)->default('12h')->after('impresora_alto');
            $table->string('formato_fecha', 10)->default('d/m/Y')->after('formato_hora');
            $table->string('num_cuenta')->nullable()->after('formato_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracion_establecimiento', function (Blueprint $table) {
            $table->dropColumn(['formato_hora', 'formato_fecha', 'num_cuenta']);
        });
    }
};
