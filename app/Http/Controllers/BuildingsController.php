<?php
namespace App\Http\Controllers;

use App\Models\Bueldings;
use App\DTOs\BuildingsDTO;
use App\Services\BuildingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class BuildingsController extends Controller
{
    protected $buildingsService;

    public function __construct(BuildingsService $buildingsService)
    {
        $this->buildingsService = $buildingsService;
    }

    public function index()
    {
        try {
            $building = $this->buildingsService->getAll();
            return $this->Success(['building' => $building]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error fetching building', 'message' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $building = $this->buildingsService->getById($id);
            return $this->Success(['building' => $building]);
        } catch (Exception $e) {
            return $this->NotFound(['error' => 'building not found', 'message' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = BuildingsDTO::validate($request->all(), 'store');
            $building = $this->buildingsService->create($data);
            DB::commit();
            return $this->Success(['building' => $building]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error creating building', 'message' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = BuildingsDTO::validate($request->all(), 'update');
            $building = $this->buildingsService->update($id, $data);
            DB::commit();
            return $this->Success(['building' => $building]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error updating building', 'message' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $this->buildingsService->delete($id);
            DB::commit();
            return $this->Success(['message' => 'building deleted successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error deleting building', 'message' => $e->getMessage()]);
        }
    }
}
