<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\HistorialCajas;

class Cajas extends Model
{
    protected $table = 'cajas';
    protected $fillable = [
        'establecimiento_id',
        'nombre',
        'abierta',
    ];

    public function historiales()
    {
        return $this->hasMany(HistorialCajas::class, 'caja_id');
    }
}

