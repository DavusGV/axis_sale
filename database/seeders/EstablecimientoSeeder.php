<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstablecimientoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('establecimientos')->insert([
            'nombre'    => 'AXIS',
            'direccion' => 'TRIUNFO',
            'telefono'  => '9617751266',
            'email'     => 'davus@gmail.com',
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);
    }
}
