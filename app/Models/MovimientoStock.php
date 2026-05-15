<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    protected $table = 'movimientos_stock';
 
    protected $fillable = [
        'establecimiento_id',
        'producto_id',
        'usuario_id',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'motivo',
    ];
 
    protected $casts = [
        'cantidad'       => 'integer',
        'stock_anterior' => 'integer',
        'stock_nuevo'    => 'integer',
    ];
 
    public function producto()
    {
        return $this->belongsTo(Products::class, 'producto_id');
    }
 
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
 
    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }
}