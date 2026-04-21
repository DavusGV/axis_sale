<?php
namespace App\Http\Controllers;
use App\Services\CategoryService;
use App\Models\Category;
use App\Models\Products;
use App\Models\UserEstablecimiento;
use Illuminate\Http\Request;
use Exception;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $category)
    {
        $this->categoryService = $category;
    }

    public function index()
    {
        try {
            $categories = $this->categoryService->getAll();
            return $this->Success(['categories' => $categories]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error fetching categories', 'message' => $e->getMessage()]);
        }
    }

    // Obtiene el establecimiento_id del header validado por middleware
    private function getEstablecimientoId()
    {
        return app('establishment_id');
    }

    // Listado paginado para la vista de categorias
    public function list(Request $request)
    {
        try {
            $establecimiento_id = $this->getEstablecimientoId();

            $query = Category::where('establecimiento_id', $establecimiento_id);

            if ($request->filled('search')) {
                $query->where('nombre', 'like', '%' . $request->search . '%');
            }

            $paginator = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'data'         => $paginator->items(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ], 200);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error fetching categories', 'message' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:255',
            ]);

            $establecimiento_id = $this->getEstablecimientoId();

            // Validamos que no exista una categoria con el mismo nombre en este establecimiento
            $existe = Category::where('establecimiento_id', $establecimiento_id)
                ->where('nombre', $request->nombre)
                ->exists();

            if ($existe) {
                return $this->BadRequest(['message' => 'Ya existe una categoría con ese nombre']);
            }

            $category = Category::create([
                'establecimiento_id' => $establecimiento_id,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion ?? null,
            ]);

            return $this->Success(['category' => $category]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al crear la categoria', 'message' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:255',
            ]);

            $establecimiento_id = $this->getEstablecimientoId();

            // Validamos que no exista otra categoria con el mismo nombre (excluyendo la actual)
            $existe = Category::where('establecimiento_id', $establecimiento_id)
                ->where('nombre', $request->nombre)
                ->where('id', '!=', $id)
                ->exists();

            if ($existe) {
                return $this->BadRequest(['message' => 'Ya existe una categoría con ese nombre']);
            }

            $category = Category::findOrFail($id);

            $category->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion ?? null,
            ]);

            return $this->Success(['category' => $category->fresh()]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al actualizar la categoria', 'message' => $e->getMessage()]);
        }
    }

    public function eliminarCategoria($id)
    {
        try {
            $category = Category::findOrFail($id);

            // Validamos que no tenga productos asociados
            if (Products::where('categoria_id', $id)->exists()) {
                throw new Exception('No se puede eliminar la categoría porque tiene productos asociados');
            }

            $category->delete();

            return $this->Success(['message' => 'Categoría eliminada correctamente']);
        } catch (Exception $e) {
            return $this->BadRequest(['message' => $e->getMessage()]);
        }
    }
}
