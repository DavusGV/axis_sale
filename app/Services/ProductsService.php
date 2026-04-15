<?php
// EdificioService.php
namespace App\Services;
use Illuminate\Http\Request;
use App\Models\Products;
use App\Models\UserEstablecimiento;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class ProductsService
{
    public function getAll(Request $request)
    {
        // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
        // enviado por el frontend y validado previamente por middleware.
        $establecimiento_id = app('establishment_id');

        $query = Products::with(['categoria', 'unidadMedida'])
                ->where('establecimiento_id', $establecimiento_id);

        // filtro por unidad de medida
        if ($request->filled('unidad_medida_id')) {
            $query->where('unidad_medida_id', $request->unidad_medida_id);
        }

        // creamos los filtros de busqueda
        if ($request->filled('search')) {
            $search = $request->search;
            // dividimos por espacios para buscar cada palabra
            $terminos = array_filter(explode(' ', $search));

            $query->where(function ($q) use ($terminos) {
                foreach ($terminos as $termino) {
                    $q->where(function ($sub) use ($termino) {
                        $sub->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('codigo', 'like', "%{$termino}%")
                            ->orWhere('descripcion', 'like', "%{$termino}%")
                            ->orWhereHas('unidadMedida', function ($rel) use ($termino) {
                                $rel->where('unidad', 'like', "%{$termino}%")
                                    ->orWhere('abreviatura', 'like', "%{$termino}%");
                            });
                    });
                }
            });
        }
        //Filtro específico
        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }
        return $query->orderBy('created_at', 'desc');
    }

    public function getById($id)
    {

        return Products::where('id', $id)
            ->where('establecimiento_id', app('establishment_id'))
            ->firstOrFail();
    }

    public function create(array $data)
    {
        // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
        // enviado por el frontend y validado previamente por middleware.
        $establecimiento_id = app('establishment_id');
        $data['establecimiento_id'] = $establecimiento_id;

        if (isset($data['imagen']) && $data['imagen'] instanceof \Illuminate\Http\UploadedFile)
            {
            //renombramos la imagen para tomar el nombre del producto
            $slug =str::slug($data['nombre']);
            $extension = $data['imagen']->getClientOriginalExtension();
            $filename = $slug . '_' . time() . '.' . $extension;

            //guardamos en el sistema
            Storage::disk('public')->putFileAs(
                'products/', $data['imagen'], $filename
            );

            // guardamos solo el nombre en la DB
            $data['imagen'] = $filename;
        }

        return Products::create($data);
    }

    public function update($id, array $data)
    {
        // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
        // enviado por el frontend y validado previamente por middleware.
        $establecimiento_id = app('establishment_id');

         $product = Products::where('id', $id)
            ->where('establecimiento_id', $establecimiento_id)
            ->firstOrFail();

         if (isset($data['imagen']) && $data['imagen'] instanceof \Illuminate\Http\UploadedFile) {

            // borramos la imagen anterior si existe en el sistema
            if ($product->imagen && Storage::disk('public')->exists("products/{$product->imagen}")) {
                Storage::disk('public')->delete("products/{$product->imagen}");
            }

            //renombramos la imagen para tomar el nombre del producto
            $slug =str::slug($data['nombre']);
            $extension = $data['imagen']->getClientOriginalExtension();
            $filename = $slug . '_' . time() . '.' . $extension;

            //guardamos en el sistema
            Storage::disk('public')->putFileAs(
                'products/', $data['imagen'], $filename
            );
            // guardamos solo el nombre en la DB
            $data['imagen'] = $filename;
        }

        $product->update($data);

        return $product;
    }

    public function delete($id)
    {
        // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
        // enviado por el frontend y validado previamente por middleware.
        $establecimiento_id = app('establishment_id');

        $product = Products::where('id', $id)
            ->where('establecimiento_id', $establecimiento_id)
            ->firstOrFail();

        $product->delete();
    }

    //Get project WITH its gallery
    public function getImageProduct(int $id)
    {
        $product = Products::with('imagen')->findOrFail($id);

        // Agregar URL completa de la  imagen
        $product->transform(function ($image) {
            $image->imagen = url('storage/' . $image->imagen);
            return $image;
        });

        return $product;
    }
}
