<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\VentasDetalles;
use App\Models\HistorialCajas;
use App\Models\User;

class Ventas extends Model
{
    protected $table = 'ventas';
    protected $fillable = [
        'establecimiento_id',
        'historial_caja_id',
        'usuario_id',
        'total',
        'pago',
        'cambio',
        'metodo_pago',
    ];


    public function detalles()
    {
        return $this->hasMany(VentasDetalles::class, 'venta_id');
    }

    public function historialCaja()
    {
        return $this->belongsTo(HistorialCajas::class, 'historial_caja_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    
}

