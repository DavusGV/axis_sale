<?php
namespace App\Http\Controllers;

use App\DTOs\ProductsDTO;
use App\Services\ProductsService;
use App\Exports\ProductsTemplateExport;
use App\Exports\ProductsErrorsExport;
use App\Services\ProductsImportService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class ProductsController extends Controller
{
    protected $productsService;
    protected $importService;

    public function __construct(
        ProductsService $productsService,
        ProductsImportService $importService
    ) {
        $this->productsService = $productsService;
        $this->importService   = $importService;
    }

    public function index(Request $request)
    {
        try {
            $query = $this->productsService->getAll($request);
            $perPage = $request->get('per_page', 10);
            $paginator = $query->paginate($perPage);
            //armar los datos de la data

            $data  = [];
            foreach($paginator->items() as $item){
                $data[] = [
                    'id' => $item->id,
                    'nombre' => $item->nombre,
                    'codigo' => $item->codigo,
                    'descripcion' => $item->descripcion,
                    'precio_compra' => $item->precio_compra,
                    'precio_venta' => $item->precio_venta,
                    'iva' => $item->iva,
                    'es_servicio' => $item->es_servicio,
                    'unidad_medida_id' => $item->unidad_medida_id,
                    'unidad_medida'    => $item->unidadMedida ? $item->unidadMedida->unidad . ' (' . $item->unidadMedida->abreviatura . ')' : null,
                    'servicio' => $item->es_servicio ? 'Servicio' : 'Producto',
                    'stock' => $item->stock,
                    'clave' => $item->clave,
                    'imagen_url' => $item->imagen_url,
                    'categoria_id' => $item->categoria_id,
                    'categoria' => $item->categoria ? $item->categoria->nombre : null,
                ];
            }
            return response()->json([
                'data'          => $data,
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

    // METODO 1: Descargar template del establecimiento activo
    public function downloadTemplate()
    {
        try {
            $establecimientoId = app('establishment_id');
    
            $nombreArchivo = 'plantilla_productos_' . date('Ymd_His') . '.xlsx';
    
            return Excel::download(
                new ProductsTemplateExport($establecimientoId),
                $nombreArchivo
            );
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al generar la plantilla',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // METODO 2: Preview de la importacion (lee, valida, guarda temporal)
    public function previewImport(Request $request)
    {
        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            ]);
    
            $establecimientoId = app('establishment_id');
            $resultado = $this->importService->preview(
                $request->file('archivo'),
                $establecimientoId
            );
    
            return $this->Success($resultado);
        } catch (ValidationException $e) {
            return $this->BadRequest([
                'error'    => 'Validation failed',
                'messages' => $e->errors(),
            ]);
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al procesar el archivo',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // METODO 3: Ejecuta la importacion definitiva
    // Si hay filas fallidas devuelve binario con archivo de errores
    // Si todo salio bien devuelve JSON con resumen
    public function executeImport(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
            ]);
    
            $establecimientoId = app('establishment_id');
    
            $resultado = $this->importService->ejecutar(
                $request->input('token'),
                $establecimientoId
            );
    
            // Si hubo filas fallidas devolvemos el archivo descargable
            // En este caso el frontend NO recibe JSON, recibe el binario directo
            // pero le pasamos los datos en headers para que pueda mostrar resumen
            if ($resultado['total_fallidas'] > 0) {
                $nombreArchivo = 'productos_fallidos_' . date('Ymd_His') . '.xlsx';
    
                return Excel::download(
                    new ProductsErrorsExport($resultado['fallidas']),
                    $nombreArchivo,
                    \Maatwebsite\Excel\Excel::XLSX,
                    [
                        'X-Insertados'     => $resultado['insertados'],
                        'X-Total-Fallidas' => $resultado['total_fallidas'],
                        'Access-Control-Expose-Headers' => 'X-Insertados, X-Total-Fallidas',
                    ]
                );
            }
    
            // Si todo se importo sin errores devolvemos JSON normal
            return $this->Success([
                'insertados'     => $resultado['insertados'],
                'total_fallidas' => 0,
                'mensaje'        => 'Todos los productos se importaron correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->BadRequest([
                'error'    => 'Validation failed',
                'messages' => $e->errors(),
            ]);
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al ejecutar la importacion',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
