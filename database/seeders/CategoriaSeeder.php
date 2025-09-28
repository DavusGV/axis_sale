<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('categorias')->insert([
            [
                'nombre' => 'Papelería',
                'descripcion' => 'Artículos y materiales de oficina, escolares y de papelería en general.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Servicio de Impresión',
                'descripcion' => 'Servicios de impresión de documentos, fotografías y materiales gráficos.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Servicio Informático',
                'descripcion' => 'Servicios relacionados con soporte técnico, mantenimiento y reparación de equipos informáticos.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
