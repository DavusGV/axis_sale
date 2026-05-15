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
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('usuario_id');
 
            // solo entradas y reducciones manuales
            // las salidas por venta se calculan de la tabla ventas
            $table->enum('tipo', ['entrada', 'reduccion']);
 
            // siempre positiva, el tipo define si suma o resta
            $table->integer('cantidad');
 
            // snapshot del stock antes y despues para auditoria
            $table->integer('stock_anterior');
            $table->integer('stock_nuevo');
 
            // obligatorio en reducciones a nivel de validacion frontend
            // en BD es nullable porque entradas pueden no tener motivo
            $table->text('motivo')->nullable();
 
            $table->timestamps();
 
            // indices para consultas del dashboard
            $table->index(['producto_id', 'created_at']);
            $table->index(['establecimiento_id', 'created_at']);
 
            $table->foreign('establecimiento_id')
                ->references('id')->on('establecimientos')
                ->onUpdate('restrict')->onDelete('restrict');
 
            $table->foreign('producto_id')
                ->references('id')->on('productos')
                ->onUpdate('restrict')->onDelete('restrict');
 
            $table->foreign('usuario_id')
                ->references('id')->on('users')
                ->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
