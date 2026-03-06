<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoGasto extends Model
{
    protected $table = 'tipos_gastos';

    // realcion de gastos por varios tipos de gasto
    public function gastos()
    {
        return $this->hasMany(Gastos::class, 'tipo_gasto_id');
    }
}