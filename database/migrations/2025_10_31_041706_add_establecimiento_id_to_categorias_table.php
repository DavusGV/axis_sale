<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEstablecimientoIdToCategoriasTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->unsignedBigInteger('establecimiento_id')->nullable()->after('id');
            $table->foreign('establecimiento_id')
                  ->references('id')->on('establecimientos')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropForeign(['establecimiento_id']);
            $table->dropColumn('establecimiento_id');
        });
    }
}
