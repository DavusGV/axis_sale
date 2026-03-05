<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gastos extends Model
{
    protected $table = 'gastos';

      public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    // relacion con el tipo de gasto
    public function tipoGasto()
    {
        return $this->belongsTo(TipoGasto::class, 'tipo_gasto_id');
    }

    // relacion con el usuario que registro el gasto
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}