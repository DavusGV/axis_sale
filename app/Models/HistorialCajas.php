<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ventas;
use App\Models\User;

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

    public function ventas()
    {
        return $this->hasMany(Ventas::class,'historial_caja_id');
    }
    public function usuario()
    {
        return $this->belongsTo(User::class,'usuario_id');
    }

    public function caja()
    {
        return $this->belongsTo(Cajas::class,'caja_id');
    }
}

