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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->string('nombre');
            $table->string('apellido_p');
            $table->string('apellido_m')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('telefono1');
            $table->string('telefono2')->nullable();
            $table->string('email')->nullable();
            $table->text('direccion')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->enum('genero', ['masculino', 'femenino', 'otro'])->nullable();
            $table->string('foto')->nullable(); // ruta de la imagen guardada
            $table->timestamps();

            $table->foreign('establecimiento_id')
                  ->references('id')
                  ->on('establecimientos')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
