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
        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono')->nullable()->after('email');
            $table->string('foto_perfil')->nullable()->after('telefono');
            $table->date('fecha_nacimiento')->nullable()->after('foto_perfil');
            $table->string('direccion')->nullable()->after('fecha_nacimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'foto_perfil', 'fecha_nacimiento', 'direccion']);
        });
    }
};
