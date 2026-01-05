<?php
namespace App\Models;
use App\Models\User;

use Illuminate\Database\Eloquent\Model;

class Establecimiento extends Model 
{
    protected $table = 'establecimientos';
    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'email'
    ];


     public function users()
    {
        return $this->belongsToMany(
            User::class,
            'establecimiento_user'
        )->withTimestamps();
    }
}