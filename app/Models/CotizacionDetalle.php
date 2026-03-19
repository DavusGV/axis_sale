<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionDetalle extends Model
{
    protected $table = 'cotizacion_detalles';

    protected $fillable = [
        'cotizacion_id',
        'producto_id',
        'nombre_producto',
        'precio',
        'precio_compra',
        'cantidad',
        'subtotal',
        'tipo_descuento',
        'descuento',
        'descuento_aplicado',
        'iva_porcentaje',
    ];

    // relacion con la cotizacion padre
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    // relacion con el producto, puede ser null si fue eliminado
    public function producto()
    {
        return $this->belongsTo(Products::class, 'producto_id');
    }
}
