<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cajas;

class CajaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Cajas::create([
            'establecimiento_id' => 1,
            'nombre' => 'C-01',
            'abierta' => false,
        ]);

        Cajas::create([
            'establecimiento_id' => 1,
            'nombre' => 'C-02',
            'abierta' => false,
        ]);
    }
}
