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
        Schema::create('cotizacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_id');
            $table->unsignedBigInteger('producto_id')->nullable(); // nullable por si el producto se elimina
            $table->string('nombre_producto');   // copia del nombre al momento de cotizar
            $table->decimal('precio', 10, 2);
            $table->decimal('precio_compra', 10, 2)->default(0);
            $table->integer('cantidad');
            $table->decimal('subtotal', 10, 2);
            $table->string('tipo_descuento')->nullable();
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('descuento_aplicado', 10, 2)->default(0);
            $table->decimal('iva_porcentaje', 5, 2)->default(0); // iva del producto al momento de cotizar
            $table->timestamps();

            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_detalles');
    }
};
