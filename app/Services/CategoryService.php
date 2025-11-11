<?php
// EdificioService.php
namespace App\Services;

use App\Models\Category;
use App\Models\UserEstablecimiento;
class CategoryService
{
    public function getAll()
    {
        $user = auth()->user();
                //vamoas a obtener de pronto el primer establecimiento asignado al usuario
        $establecimiento = UserEstablecimiento::where('user_id', $user->id)->first();
        $establecimiento_id = $establecimiento->establecimiento_id ?? 0;
        return Category::where('establecimiento_id', $establecimiento_id)->get();
    }
}
