<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPago extends Model
{
    protected $table = 'planes_pago';

    protected $fillable = [
        'establecimiento_id',
        'cliente_id',
        'venta_id',
        'historial_caja_id',
        'usuario_id',
        'total_venta',
        'interes_tipo',
        'interes_valor',
        'interes_aplicado',
        'total_a_pagar',
        'anticipo',
        'total_financiado',
        'num_plazos',
        'tipo_plazo',
        'monto_cuota',
        'fecha_inicio',
        'fecha_proximo_pago',
        'saldo_pendiente',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio'        => 'date',
        'fecha_proximo_pago'  => 'date',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function venta()
    {
        return $this->belongsTo(Ventas::class, 'venta_id');
    }

    public function historialCaja()
    {
        return $this->belongsTo(HistorialCajas::class, 'historial_caja_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function pagos()
    {
        return $this->hasMany(PagoPlan::class, 'plan_pago_id');
    }
}