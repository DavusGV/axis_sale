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
        Schema::create('unidades_medidas', function (Blueprint $table) {
            $table->id();

            // referencia al establecimiento dueno de esta unidad
            $table->foreignId('establecimiento_id')
                ->constrained('establecimientos')
                ->onDelete('restrict');

            $table->string('unidad', 100);
            $table->string('abreviatura', 20);
            $table->text('descripcion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_medidas');
    }
};
