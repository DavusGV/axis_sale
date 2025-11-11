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
        Schema::create('establecimiento_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('establecimiento_id')
                ->references('id')->on('establecimientos')
                ->onDelete('cascade');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            // Para evitar duplicados en la relación, usa un índice compuesto (opcional pero recomendado):
            $table->unique(['establecimiento_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('establecimiento_user');
    }
};
