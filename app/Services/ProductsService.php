<?php
// EdificioService.php
namespace App\Services;

use App\Models\Products;
use Exception;

class ProductsService
{
    public function getAll()
    {
        return Products::all();
    }

    public function getById($id)
    {
        return Products::findOrFail($id);
    }

    public function create(array $data)
    {
        $data['establecimiento_id'] = 1; // Valor fijo para establecimiento_id
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
