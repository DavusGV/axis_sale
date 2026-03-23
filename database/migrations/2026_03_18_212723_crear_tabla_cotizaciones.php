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
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('caja_id')->nullable(); // referencia a la caja activa al cotizar
            $table->unsignedBigInteger('historial_caja_id')->nullable(); // historial de caja activa
            $table->string('folio')->unique();
            $table->enum('status', ['pendiente', 'cancelado', 'vendido'])->default('pendiente');
            $table->string('modo_iva')->default('sin_iva');
            $table->decimal('iva_total', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('notas')->nullable();
            $table->date('expires_at')->nullable(); // fecha definida por el operador
            $table->unsignedBigInteger('venta_id')->nullable(); // se llena al convertir
            $table->string('venta_folio')->nullable();          // copia del folio de venta
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->foreign('establecimiento_id')->references('id')->on('establecimientos');
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('caja_id')->references('id')->on('cajas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
