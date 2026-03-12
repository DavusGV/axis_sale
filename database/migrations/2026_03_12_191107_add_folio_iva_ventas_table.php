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
            // folio unico legible por establecimiento
            $table->string('folio')->nullable()->unique()->after('id');

            // modo de iva con el que se proceso la venta, se guarda para reimprimir correctamente
            $table->enum('modo_iva', ['sin_iva', 'iva_incluido', 'iva_adicional'])
                ->default('sin_iva')->after('folio');

            // suma total del iva calculado en todos los detalles de la venta
            $table->decimal('iva_total', 10, 2)->default(0)->after('modo_iva');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['folio', 'modo_iva', 'iva_total']);
        });
    }
};
