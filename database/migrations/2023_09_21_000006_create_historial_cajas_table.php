<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistorialCajasTable extends Migration
{
    public function up()
    {
        Schema::create('historial_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained();
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->enum('estado', ['abierta', 'cerrada']);
            $table->decimal('saldo_inicial', 10, 2)->default(0);
            $table->decimal('saldo_final', 10, 2)->default(0);
            $table->text('descripcion')->nullable();
            //fechas y horas de apertura y cierre
            $table->timestamp('fecha_apertura')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('historial_cajas');
    }
}
