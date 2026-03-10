<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cajas;

class HistorialCajas extends Model
{
    protected $table = 'historial_cajas';
    protected $fillable = [
        'caja_id',
        'usuario_id',
        'estado',
        'saldo_inicial',
        'saldo_final',
        'descripcion',
        'fecha_apertura',
        'fecha_cierre',
    ];

    public function caja()
    {
        return $this->belongsTo(Cajas::class, 'caja_id');
    }
}

