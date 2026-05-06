<?php
namespace App\Services;

use App\Models\Category;

class CategoryService
{
    public function getAll()
    {
        // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
        // enviado por el frontend y validado previamente por middleware.
        $establecimiento_id = app('establishment_id');

        return Category::where('establecimiento_id', $establecimiento_id)->get();
    }
}
