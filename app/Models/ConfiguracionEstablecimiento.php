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
    ];

    protected $casts = [
        'imprimir_ticket_venta' => 'boolean',
    ];

    // establecimiento al que pertenece esta configuracion
    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }
}