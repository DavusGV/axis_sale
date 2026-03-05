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
        Schema::create('planes_pago', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('historial_caja_id'); // caja donde se creo el credito
            $table->unsignedBigInteger('usuario_id');        // quien autorizo el credito

            $table->decimal('total_venta', 10, 2);           // cuanto fue la compra
            $table->string('interes_tipo')->nullable();       // 'porcentaje' o 'monto'
            $table->decimal('interes_valor', 10, 2)->default(0);   // valor ingresado
            $table->decimal('interes_aplicado', 10, 2)->default(0); // cuanto resulto en dinero
            $table->decimal('total_a_pagar', 10, 2);         // total_venta + interes_aplicado
            $table->decimal('anticipo', 10, 2)->default(0);   // pago inicial opcional
            $table->decimal('total_financiado', 10, 2)->default(0); // total_a_pagar - anticipo, lo que se divide entre plazos
            $table->integer('num_plazos');                   // cuantos pagos seran
            $table->enum('tipo_plazo', ['dias', 'semanal', 'mensual']);
            $table->decimal('monto_cuota', 10, 2);           // cuota ya redondeada
            $table->date('fecha_inicio');
            $table->date('fecha_proximo_pago');
            $table->decimal('saldo_pendiente', 10, 2);       // se reduce con cada abono
            $table->enum('estado', ['activo', 'liquidado', 'vencido', 'cancelado'])->default('activo');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('establecimiento_id')->references('id')->on('establecimientos');
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('venta_id')->references('id')->on('ventas');
            $table->foreign('historial_caja_id')->references('id')->on('historial_cajas');
            $table->foreign('usuario_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes_pago');
    }
};
