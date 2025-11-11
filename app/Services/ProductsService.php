<?php
// EdificioService.php
namespace App\Services;

use App\Models\Products;
use App\Models\UserEstablecimiento;
use Exception;

class ProductsService
{
    public function getAll()
    {
        $user = auth()->user();
                //vamoas a obtener de pronto el primer establecimiento asignado al usuario
        $establecimiento = UserEstablecimiento::where('user_id', $user->id)->first();
        $establecimiento_id = $establecimiento->establecimiento_id ?? 0;
        return Products::where('establecimiento_id', $establecimiento_id)->get();
    }

    public function getById($id)
    {

        return Products::findOrFail($id);
    }

    public function create(array $data)
    {
        $user = auth()->user();
                //vamoas a obtener de pronto el primer establecimiento asignado al usuario
        $establecimiento = UserEstablecimiento::where('user_id', $user->id)->first();
        $establecimiento_id = $establecimiento->establecimiento_id ?? 0;
        $data['establecimiento_id'] = $establecimiento_id;
        return Products::create($data);
    }

    public function update($id, array $data)
    {
        $product = Products::findOrFail($id);
        $product->update($data);
        return $product;
    }

    public function delete($id)
    {
        $product = Products::findOrFail($id);
        $product->delete();
    }
}
