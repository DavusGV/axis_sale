<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVentasTable extends Migration
{
    public function up()
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained();
            $table->foreignId('caja_id')->nullable()->constrained();
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->decimal('total', 10, 2);
            $table->decimal('pago', 10, 2)->default(0);
            $table->decimal('cambio', 10, 2)->default(0);
            $table->string('metodo_pago')->default('efectivo');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ventas');
    }
}
