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

    public function index(Request $request)
    {
        try {

            
            $query = $this->productsService->getAll($request);

            $perPage = $request->get('per_page', 10);
            $paginator = $query->paginate($perPage);

            return response()->json([
                'data'          => $paginator->items(),
                'total'         => $paginator->total(),
                'per_page'      => $paginator->perPage(),
                'current_page'  => $paginator->currentPage(),
                'last_page'     => $paginator->lastPage(),
                'from'          => $paginator->firstItem(),
                'to'            => $paginator->lastItem()
            ], 200);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error fetching products', 'message' => $e->getMessage()]);
        }
    }





    public function store(Request $request)
{
    DB::beginTransaction();
    try {
        $data = ProductsDTO::validate($request->all(), 'store');

        // Adjuntar archivo si existe
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen');
        }
        $product = $this->productsService->create($data);
        DB::commit();
        return $this->Success($product);

    } catch (ValidationException $e) {
        DB::rollBack();
        return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()
        ]);

    } catch (Exception $e) {
        DB::rollBack();
        return $this->InternalError([ 'error' => 'Error creating product', 'message' => $e->getMessage()]);
    }
}


    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = ProductsDTO::validate($request->all(), 'update');

            // procesar la imagen nueva
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen');
        } else {
            // si no, se conservar la anterior
            unset($data['imagen']);
        }

            $product = $this->productsService->update($id, $data);
            DB::commit();
            return $this->Success($product);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error updating product', 'message' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $this->productsService->delete($id);
            DB::commit();
            return $this->Success(['message' => 'Product deleted successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error deleting product', 'message' => $e->getMessage()]);
        }
    }
}
