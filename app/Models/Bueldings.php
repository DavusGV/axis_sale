<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Monolog\Level;

class Bueldings extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'direccion', 'usuario_id'];

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
