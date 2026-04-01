<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE planes_pago
            MODIFY COLUMN estado
            ENUM('activo','atrasado','vencido','liquidado','cancelado')
            NOT NULL DEFAULT 'activo'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE planes_pago SET estado = 'activo' WHERE estado = 'atrasado'");

        DB::statement("
            ALTER TABLE planes_pago
            MODIFY COLUMN estado
            ENUM('activo','vencido','liquidado','cancelado')
            NOT NULL DEFAULT 'activo'
        ");
    }
};
