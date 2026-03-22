<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Establecimiento;
use App\Models\User;

class UserEstablecimiento extends Model
{
    protected $table = 'establecimiento_user';
    
    protected $fillable = [
        'establecimiento_id',
        'user_id'
    ];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }
    
}

