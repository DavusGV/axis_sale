<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'stock'
    ];
}
