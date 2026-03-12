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
        Schema::create('configuracion_establecimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')
                ->constrained('establecimientos')
                ->onDelete('cascade');

            // modo de manejo del iva en ventas y tickets
            // sin_iva: no se aplica iva
            // iva_incluido: el precio ya contiene iva, se desglosa en ticket
            // iva_adicional: el iva se suma al precio base en el total
            $table->enum('modo_iva', ['sin_iva', 'iva_incluido', 'iva_adicional'])
                ->default('sin_iva');

            // permite descargar o imprimir el ticket al finalizar la venta
            $table->boolean('imprimir_ticket_venta')->default(true);

            // ancho del papel de la impresora en mm, los mas comunes son 58 y 80
            $table->integer('impresora_ancho')->default(80);
            // alto maximo del ticket en mm, auto si se deja en 0
            $table->integer('impresora_alto')->default(200);

            $table->timestamps();

            // un establecimiento solo tiene una configuracion
            $table->unique('establecimiento_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_establecimiento');
    }
};
