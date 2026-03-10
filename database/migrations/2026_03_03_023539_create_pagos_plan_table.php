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
        Schema::create('pagos_plan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_pago_id');
            $table->unsignedBigInteger('historial_caja_id'); // caja donde se recibio el abono
            $table->unsignedBigInteger('usuario_id');        // quien recibio el abono

            $table->integer('numero_cuota');                 // que cuota es (1, 2, 3...)
            $table->decimal('monto_pagado', 10, 2);
            $table->decimal('saldo_anterior', 10, 2);        // cuanto debia antes
            $table->decimal('saldo_despues', 10, 2);         // cuanto quedo debiendo
            $table->date('fecha_pago');
            $table->string('metodo_pago')->default('efectivo');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('plan_pago_id')->references('id')->on('planes_pago');
            $table->foreign('historial_caja_id')->references('id')->on('historial_cajas');
            $table->foreign('usuario_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos_plan');
    }
};
