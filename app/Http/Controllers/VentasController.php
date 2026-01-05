<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Models\Cajas;
use App\Models\HistorialCajas;
use PhpParser\Node\Expr\FuncCall;
use App\Models\Products;
use App\Models\Ventas;
use App\Models\VentasDetalles;
use App\Models\UserEstablecimiento;

class VentasController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request)
    {
        try {

            // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
            // enviado por el frontend y validado previamente por middleware.
            $establecimiento_id = app('establishment_id');
            $query = Products::where('establecimiento_id', $establecimiento_id);

            // filtro de busqueda
            if ($request ->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search){
                    $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            if ($request->filled('categoria_id')) {
                $query->where('categoria_id', $request->categoria_id);
            }
            $query->orderBy('created_at', 'desc');

            // paginacion
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
        } 
        catch (Exception $e) {

            return response()->json([
                'error'   => 'Error fetching products',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
            // enviado por el frontend y validado previamente por middleware.
            $establecimiento_id = app('establishment_id');
        
            $caja = Cajas::where('establecimiento_id', $establecimiento_id)
                ->where('abierta', true)
                ->first();

            if (!$caja) {
                throw ValidationException::withMessages(['caja_id' => 'La caja no existe o no está abierta.']);
            }
            //buscamos el historial de la caja abierta
            $historialCaja = HistorialCajas::where('caja_id', $caja->id)
                ->where('estado', 'abierta')
                ->first();

            if (!$historialCaja) {
                throw ValidationException::withMessages(['caja_id' => 'No se encontró un historial de caja abierto.']);
            }

            $venta = new Ventas();
            $venta->establecimiento_id = $establecimiento_id;
            $venta->historial_caja_id = $historialCaja->id;
            $venta->usuario_id = $request->usuario_id;
            $venta->total = $request->total;
            $venta->pago = $request->pago;
            $venta->cambio = $request->cambio;
            $venta->metodo_pago = $request->metodo_pago;
            $venta->save();

            //hay que guardar los detalles de la venta
            foreach ($request->detalles as $detalle) {

                $producto = Products::lockForUpdate()->find($detalle['producto_id']);

                if (!$producto) {
                    DB::rollBack();
                    return $this->BadRequest("Producto no encontrado");
                }

                // Validamos si hay stok disponibles para la venta
                if ($producto->stock < $detalle['cantidad']) {
                    DB::rollBack();
                    return $this->BadRequest([
                        'message' => "Stock insuficiente del producto: {$producto->nombre}",
                        'stock_actual' => $producto->stock
                    ]);
                }

                // Restamos el stock disponible
                $producto->stock -= $detalle['cantidad'];
                $producto->save();

                $ventaDetalle = new VentasDetalles();
                $ventaDetalle->venta_id = $venta->id;
                $ventaDetalle->producto_id = $detalle['producto_id'];
                $ventaDetalle->cantidad = $detalle['cantidad'];
                $ventaDetalle->precio = $detalle['precio'];
                $ventaDetalle->precio_compra = $detalle['precio_compra'];
                $ventaDetalle->subtotal =  $detalle['precio'] * $detalle['cantidad'];
                $ventaDetalle->save();
            }

            DB::commit();

            return $this->Success(['message' => 'Venta registrada exitosamente.', 'venta' => $venta]);
        } catch (ValidationException $ve) {
            DB::rollBack();
            throw $ve;
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error al registrar la venta.', 'details' => $e->getMessage()], 500);
        }
    }


    // Método específico para el lector de código de barras en ventas
    public function leerCodigoBarras(Request $request)
    {
        try {
            $request->validate([
                'codigo' => 'required|string',
                'establecimiento_id' => 'sometimes|integer'
            ]);

            // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
            // enviado por el frontend y validado previamente por middleware.
            $establecimiento = app('establishment_id');
            
            if (!$establecimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no tiene establecimiento asignado'
                ], 400);
            }

            $establecimiento_id = $establecimiento->establecimiento_id;

            // Buscar producto por código de barra en este caso por codigo
            $producto = Products::where('codigo', $request->codigo)
                ->where('establecimiento_id', $establecimiento_id)
                ->first();

            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Verificar si hay stock
            if ($producto->stock < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto sin stock disponible',
                    'producto' => $producto
                ], 400);
            }

            // Retornar datos del producto para el carrito
            return response()->json([
                'success' => true,
                'message' => 'Producto encontrado',
                'producto' => [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'codigo' => $producto->codigo,
                    'precio_compra' => $producto->precio_compra,
                    'precio_venta' => $producto->precio_venta,
                    'stock' => $producto->stock,
                    'imagen_url' => $producto->imagen_url
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al leer código',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
