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
       Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('establecimiento_id');

            $table->string('nombre');
            $table->string('codigo')->nullable();
            $table->boolean('requiere_referencia')->default(false);
            $table->decimal('comision', 8, 2)->default(0);
            $table->boolean('estado')->default(true);

            $table->timestamps();
            $table->softDeletes(); // 👈 IMPORTANTE

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
        Schema::dropIfExists('metodos_pago');
    }
};
