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
        Schema::create('tipos_gastos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');

            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('state', ['activo', 'inactivo'])->default('activo');

            $table->timestamps();

            // Foreign key
            $table->foreign('establecimiento_id')
                  ->references('id')
                  ->on('establecimientos')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_gastos');
    }
};
