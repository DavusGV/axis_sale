<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Ventas;
use App\Models\Products;

class VentasDetalles extends Model
{
    protected $table = 'venta_detalles';
    protected $fillable = [
        'venta_id',
        'producto_id',
        'cantidad',
        'precio',
        'precio_compra',
        'subtotal',
        'tipo_descuento',
        'descuento',
        'descuento_aplicado',
    ];

    public function producto()
    {
        return $this->belongsTo(Products::class, 'producto_id');
    }

    public function venta()
    {
        return $this->belongsTo(Ventas::class, 'venta_id');
    }
}