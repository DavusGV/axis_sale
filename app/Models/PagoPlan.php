<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoPlan extends Model
{
    protected $table = 'pagos_plan';

    protected $fillable = [
        'plan_pago_id',
        'historial_caja_id',
        'usuario_id',
        'numero_cuota',
        'monto_pagado',
        'saldo_anterior',
        'saldo_despues',
        'fecha_pago',
        'metodo_pago',
        'notas',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
    ];

    public function plan()
    {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
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