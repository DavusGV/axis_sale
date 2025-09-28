<?php
namespace App\Http\Controllers;

use App\DTOs\ProductsDTO;
use App\Services\ProductsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class ProductsController extends Controller
{
    protected $productsService;

    public function __construct(ProductsService $productsService)
    {
        $this->productsService = $productsService;
    }

    public function index()
    {
        try {
            $products = $this->productsService->getAll();
            return $this->Success($products);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error fetching products', 'message' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = ProductsDTO::validate($request->all(), 'store');
            $product = $this->productsService->create($data);
            DB::commit();
            return $this->Success([$product]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error creating product', 'message' => $e->getMessage()]);
        }
    }
}
