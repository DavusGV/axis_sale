<?php
// EdificioService.php
namespace App\Services;
use Illuminate\Http\Request;
use App\Models\Products;
use App\Models\UserEstablecimiento;
use App\Models\Category;
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

        // si viene la bandera autogenerar en true, generamos codigo y clave
        // ignorando lo que mande el frontend en esos campos
        if (!empty($data['autogenerar'])) {
            $codigoGenerado = $this->generarCodigoProducto(
                $data['categoria_id'],
                $data['nombre'],
                $establecimiento_id
            );
            $data['codigo'] = $codigoGenerado;
            $data['clave']  = $codigoGenerado;
        }

        // la bandera no es columna de la tabla productos, la quitamos
        unset($data['autogenerar']);

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

    /**
     * Genera un codigo unico para producto con formato XX-YYY-ZZZZZ
     * XX    -> 2 primeras letras de la categoria
     * YYY   -> 3 primeras letras del nombre del producto
     * ZZZZZ -> 5 caracteres aleatorios alfanumericos
     * La unicidad se valida por establecimiento.
     */
    private function generarCodigoProducto(int $categoriaId, string $nombreProducto, int $establecimientoId): string
    {
        // obtenemos la categoria para extraer su nombre, validando que pertenezca al establecimiento
        $categoria = Category::where('id', $categoriaId)
            ->where('establecimiento_id', $establecimientoId)
            ->firstOrFail();

        // limpiamos acentos y caracteres especiales para los prefijos
        $prefijoCategoria = $this->limpiarTexto($categoria->nombre);
        $prefijoNombre    = $this->limpiarTexto($nombreProducto);

        // tomamos las letras disponibles (hasta 2 y hasta 3)
        $parteCategoria = strtoupper(substr($prefijoCategoria, 0, 2));
        $parteNombre    = strtoupper(substr($prefijoNombre, 0, 3));

        $prefijo = $parteCategoria . '-' . $parteNombre . '-';

        // consultamos los codigos ya existentes con ese prefijo para ese establecimiento
        // asi evitamos colisiones consultando una sola vez la base de datos
        $codigosExistentes = Products::where('establecimiento_id', $establecimientoId)
            ->where('codigo', 'like', $prefijo . '%')
            ->pluck('codigo')
            ->toArray();

        $intentos = 0;
        $maxIntentos = 10;

        do {
            $randomPart = $this->generarRandomAlfanumerico(5);
            $codigoCandidato = $prefijo . $randomPart;
            $intentos++;

            if (!in_array($codigoCandidato, $codigosExistentes)) {
                return $codigoCandidato;
            }
        } while ($intentos < $maxIntentos);

        // si tras 10 intentos no encontramos uno libre, abortamos con mensaje claro
        throw new Exception('No fue posible generar un codigo unico en este momento. Intenta nuevamente o ingresa el codigo manualmente.');
    }

    /**
     * Limpia un texto removiendo acentos y caracteres no alfabeticos
     * para usarlo en los prefijos del codigo generado
     */
    private function limpiarTexto(string $texto): string
    {
        // removemos acentos
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        // dejamos solo letras
        $texto = preg_replace('/[^A-Za-z]/', '', $texto);
        return $texto;
    }

    /**
     * Genera una cadena aleatoria alfanumerica en mayusculas
     * usando A-Z y 0-9 (caracteres seguros para Code 128)
     */
    private function generarRandomAlfanumerico(int $longitud): string
    {
        $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $resultado = '';
        $max = strlen($caracteres) - 1;

        for ($i = 0; $i < $longitud; $i++) {
            $resultado .= $caracteres[random_int(0, $max)];
        }

        return $resultado;
    }
}
