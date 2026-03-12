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
    ];
    public function planPago()
    {
        return $this->hasOne(PlanPago::class, 'venta_id');
    }
}

