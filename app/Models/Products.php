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
        'unidad_medida_id',
        'nombre',
        'codigo',
        'descripcion',
        'precio_compra',
        'precio_venta',
        'stock',
        'clave',
        'imagen',
        'iva',
        'es_servicio',
    ];

    // para construir la ruta de la imagen
    protected $appends = ['imagen_url'];

    protected $casts = [
        'es_servicio' => 'boolean',
    ];

    public function getImagenUrlAttribute()
    {
        if (!$this->imagen) {
            return asset('images/cart.png'); // opcional
        }

        return asset("storage/products/{$this->imagen}");
    }

    public function categoria()
    {
        return $this->belongsTo(Category::class, 'categoria_id');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

}
