<?php
// EdificioService.php
namespace App\Services;

use App\Models\Bueldings;
use Exception;

class BuildingsService
{
    public function getAll()
    {
        return Bueldings::all();
    }

    public function getById($id)
    {
        return Bueldings::findOrFail($id);
    }

    public function create(array $data)
    {
        return Bueldings::create($data);
    }

    public function update($id, array $data)
    {
        $edificio = Bueldings::findOrFail($id);
        $edificio->update($data);
        return $edificio;
    }

    public function delete($id)
    {
        $edificio = Bueldings::findOrFail($id);
        $edificio->delete();
    }
}
