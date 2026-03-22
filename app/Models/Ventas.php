<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PlanPago;

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
        'metodo_pago_id',
        'folio',
        'modo_iva',
        'iva_total',
    ];
    public function planPago()
    {
        return $this->hasOne(PlanPago::class, 'venta_id');
    }

    public function detalles()
    {
        return $this->hasMany(VentasDetalles::class, 'venta_id');
    }

    // establecimiento donde se realizo la venta
    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function historialCaja()
    {
        return $this->belongsTo(HistorialCajas::class, 'historial_caja_id');

    }
}

