<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'establecimiento_id',
        'nombre',
        'apellido_p',
        'apellido_m',
        'telefono1',
        'telefono2',
        'email',
        'direccion',
        'fecha_nacimiento',
        'genero',
        'foto',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo'           => 'boolean',
    ];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }
}