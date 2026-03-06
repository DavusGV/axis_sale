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
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('establecimiento_id')
                ->constrained()
                ->onDelete('restrict');

            $table->foreignId('tipo_gasto_id')
                ->constrained('tipos_gastos')
                ->onDelete('restrict');

            $table->string('concepto');
            $table->text('descripcion')->nullable();

            $table->decimal('monto', 10, 2);

            $table->date('fecha');

            $table->boolean('state')->default(1);

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->onDelete('restrict');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
