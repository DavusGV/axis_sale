<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionEstablecimiento extends Model
{
    protected $table = 'configuracion_establecimiento';

    protected $fillable = [
        'establecimiento_id',
        'modo_iva',
        'imprimir_ticket_venta',
        'impresora_ancho',
        'impresora_alto',
        'impresora_ticket',
        'impresion_automatica',
        'formato_hora',
        'formato_fecha',
        'num_cuenta',
        'descuento_con_decimales',
        'arrastre_saldo',
    ];

    protected $casts = [
        'imprimir_ticket_venta' => 'boolean',
        'descuento_con_decimales' => 'boolean',
        'impresion_automatica'   => 'boolean',
        'arrastre_saldo'          => 'boolean',
    ];

    // establecimiento al que pertenece esta configuracion
    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }
}
