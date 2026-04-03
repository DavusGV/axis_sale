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
        DB::statement("ALTER TABLE ventas MODIFY COLUMN status ENUM('vendido','pendiente','cancelada','no_pagado') NOT NULL DEFAULT 'vendido'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ventas MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'vendido'");
    }
};
