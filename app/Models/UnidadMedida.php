<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidades_medidas';

    protected $fillable = [
        'establecimiento_id',
        'unidad',
        'abreviatura',
        'descripcion',
    ];
    
    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    // productos que usan esta unidad
    public function productos()
    {
        return $this->hasMany(Products::class, 'unidad_medida_id');
    }
}