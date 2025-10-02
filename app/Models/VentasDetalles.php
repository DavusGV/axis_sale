<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentasDetalles extends Model
{
    protected $table = 'venta_detalles';
    protected $fillable = [
        'venta_id',
        'producto_id',
        'cantidad',
        'precio',
        'precio_compra',
        'subtotal'
    ];

    public function producto()
    {
        return $this->belongsTo(Products::class, 'producto_id');
    }
}

