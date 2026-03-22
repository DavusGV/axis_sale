<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Establecimiento;

class Cajas extends Model
{
    protected $table = 'cajas';
    protected $fillable = [
        'establecimiento_id',
        'nombre',
        'abierta',
    ];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }


}


