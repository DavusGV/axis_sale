<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Establecimiento;
use App\Models\UserEstablecimiento;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'telefono', 'foto_perfil', 'fecha_nacimiento', 'direccion',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public  function establecimientos()
    {
        return $this->belongsToMany(
            Establecimiento::class,
            'establecimiento_user'
        )->withTimestamps();
    }

    public function userEstablecimientos()
    {
        return $this->belongsToMany(
            UserEstablecimiento::class,
            'establecimiento_id'
        )->withTimestamps();
    }
}
