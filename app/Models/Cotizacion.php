<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cliente;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'establecimiento_id',
        'usuario_id',
        'cliente_id',
        'historial_caja_id',
        'folio',
        'status',
        'modo_iva',
        'iva_total',
        'subtotal',
        'total',
        'notas',
        'expires_at',
        'venta_id',
        'venta_folio',
        'converted_at',
    ];

    // relacion con el establecimiento
    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    // relacion con el operador que creo la cotizacion
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // relacion con el cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    // relacion con el historial de caja
    public function historialCaja()
    {
        return $this->belongsTo(HistorialCajas::class, 'historial_caja_id');
    }

    // relacion con los detalles de la cotizacion
    public function detalles()
    {
        return $this->hasMany(CotizacionDetalle::class, 'cotizacion_id');
    }

    // relacion con la venta generada si ya se convirtio
    public function venta()
    {
        return $this->belongsTo(Ventas::class, 'venta_id');
    }
}
