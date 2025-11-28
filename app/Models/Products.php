<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Products extends Model
{
    protected $table = 'productos';
    protected $fillable = [
        'establecimiento_id',
        'categoria_id',
        'nombre',
        'codigo',
        'descripcion',
        'precio_compra',
        'precio_venta',
        'stock',
        'clave',
        'imagen'
    ];

    // para construir la ruta de la imagen
    protected $appends = ['imagen_url'];

    public function getImagenUrlAttribute()
    {
        if (!$this->imagen) {
            return asset('images/no-image.png'); // opcional
        }

        return asset("storage/products/{$this->imagen}");
    }

}
