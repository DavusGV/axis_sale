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
use App\Models\{ConfiguracionEstablecimiento, Establecimiento};
use Carbon\Carbon;

class VentasController extends Controller
{
    public function __construct()
    {
        // Configuramos la zona horaria mexicana para este controlador
        date_default_timezone_set('America/Mexico_City');

        // También configuramos Carbon para usar la misma zona horaria
        Carbon::setLocale('es');
        Carbon::now()->setTimezone('America/Mexico_City');
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
                throw ValidationException::withMessages(['caja_id' => 'La caja no existe o no esta abierta.']);
            }

            // buscamos el historial de la caja abierta
            $historialCaja = HistorialCajas::where('caja_id', $caja->id)
                ->where('estado', 'abierta')
                ->first();

            if (!$historialCaja) {
                throw ValidationException::withMessages(['caja_id' => 'No se encontro un historial de caja abierto.']);
            }

            // obtenemos la configuracion del establecimiento para saber el modo de iva
            $config  = ConfiguracionEstablecimiento::where('establecimiento_id', $establecimiento_id)->first();
            $modoIva = $config->modo_iva ?? 'sin_iva';

            // calculamos el iva total de todos los detalles antes de guardar la venta
            $ivaTotalVenta = 0;

            foreach ($request->detalles as $detalle) {
                $producto = Products::find($detalle['producto_id']);
                if (!$producto) continue;

                $subtotalNeto = ($detalle['precio'] * $detalle['cantidad']) - ($detalle['descuento_aplicado'] ?? 0);
                $ivaPorcentaje = $producto->iva ?? 0;

                if ($modoIva === 'sin_iva' || $ivaPorcentaje == 0) {
                    // sin iva no se suma nada
                    $ivaMonto = 0;
                } elseif ($modoIva === 'iva_incluido') {
                    // extraemos el iva que ya esta incluido en el precio
                    $ivaMonto = $subtotalNeto - ($subtotalNeto / (1 + $ivaPorcentaje / 100));
                } else {
                    // iva_adicional: el iva se suma encima del precio
                    $ivaMonto = $subtotalNeto * ($ivaPorcentaje / 100);
                }

                $ivaTotalVenta += $ivaMonto;
            }

            // el total final depende del modo de iva
            $totalFinal = $request->total_final;
            if ($modoIva === 'iva_adicional') {
                // sumamos el iva al total que viene del frontend
                $totalFinal = $request->total_final + $ivaTotalVenta;
            }

            $venta = new Ventas();
            $venta->establecimiento_id  = $establecimiento_id;
            $venta->historial_caja_id   = $historialCaja->id;
            $venta->usuario_id          = $request->usuario_id;
            $venta->folio               = $this->generarFolio($establecimiento_id);
            $venta->modo_iva            = $modoIva;
            $venta->iva_total           = round($ivaTotalVenta, 2);
            $venta->total               = round($totalFinal, 2);
            $venta->pago                = $request->pago;
            $venta->cambio              = $request->cambio;
            $venta->metodo_pago         = $request->metodo_pago;
            $venta->metodo_pago_id      = $request->metodo_pago_id ?? null;
            $venta->subtotal            = $request->total; // total sin descuentos ni iva
            $venta->created_at          = Carbon::now();

            $venta->save();

            // guardamos los detalles con el iva del producto al momento de vender
            foreach ($request->detalles as $detalle) {

                $producto = Products::lockForUpdate()->find($detalle['producto_id']);

                if (!$producto) {
                    DB::rollBack();
                    return $this->BadRequest("Producto no encontrado");
                }

                // validamos si hay stock disponible
                if (!$producto->es_servicio && $producto->stock < $detalle['cantidad']) {
                    DB::rollBack();
                    return $this->BadRequest([
                        'message'      => "Stock insuficiente del producto: {$producto->nombre}",
                        'stock_actual' => $producto->stock
                    ]);
                }

                // solo restamos stock si no es servicio
                if (!$producto->es_servicio) {
                    $producto->stock -= $detalle['cantidad'];
                    $producto->save();
                }

                $ventaDetalle = new VentasDetalles();
                $ventaDetalle->venta_id           = $venta->id;
                $ventaDetalle->producto_id        = $detalle['producto_id'];
                $ventaDetalle->cantidad           = $detalle['cantidad'];
                $ventaDetalle->precio             = $detalle['precio'];
                $ventaDetalle->precio_compra      = $detalle['precio_compra'];
                $ventaDetalle->subtotal           = $detalle['precio'] * $detalle['cantidad'];
                $ventaDetalle->tipo_descuento     = $detalle['tipo_descuento'] ?? null;
                $ventaDetalle->descuento          = $detalle['descuento'] ?? 0;
                $ventaDetalle->descuento_aplicado = $detalle['descuento_aplicado'] ?? 0;
                // guardamos el iva del producto en el momento de la venta
                $ventaDetalle->iva_porcentaje     = $producto->iva ?? null;
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

    // genera un folio unico por establecimiento con formato VTA-XX26001
    private function generarFolio(int $establecimiento_id): string
    {
        // obtenemos las iniciales del establecimiento (2 letras)
        $establecimiento = Establecimiento::find($establecimiento_id);
        $nombre          = $establecimiento->nombre ?? 'XX';
        $iniciales       = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nombre), 0, 2));
        $anio            = Carbon::now()->format('y'); // 2 digitos del año: 26

        // contamos las ventas del establecimiento en el año actual para el consecutivo
        $consecutivo = Ventas::where('establecimiento_id', $establecimiento_id)
            ->whereYear('created_at', Carbon::now()->year)
            ->count() + 1;

        return 'VTA-' . $iniciales . $anio . str_pad($consecutivo, 3, '0', STR_PAD_LEFT);
    }

    public function ticket(int $id)
    {
        try {
            $venta = Ventas::with([
                'detalles.producto',   // productos con su imagen y nombre
                'establecimiento',     // nombre y logo del negocio
                'planPago.cliente',    // plan de credito y cliente si aplica
            ])->findOrFail($id);

            // construimos el logo url solo si existe el archivo
            $logoUrl = null;
            if ($venta->establecimiento && $venta->establecimiento->logo) {
                $logoUrl = asset('storage/' . $venta->establecimiento->logo);
            }

            // armamos los productos con los datos que necesita el ticket
            $productos = $venta->detalles->map(function ($detalle) use ($venta) {
                $subtotalNeto  = $detalle->subtotal - $detalle->descuento_aplicado;
                $ivaPorcentaje = $detalle->iva_porcentaje ?? 0;
                $ivaMonto      = 0;

                if ($venta->modo_iva === 'iva_incluido' && $ivaPorcentaje > 0) {
                    $ivaMonto = $subtotalNeto - ($subtotalNeto / (1 + $ivaPorcentaje / 100));
                } elseif ($venta->modo_iva === 'iva_adicional' && $ivaPorcentaje > 0) {
                    $ivaMonto = $subtotalNeto * ($ivaPorcentaje / 100);
                }

                return [
                    'nombre'             => $detalle->producto->nombre ?? 'Producto eliminado',
                    'imagen_url'         => $detalle->producto->imagen_url ?? null,
                    'cantidad'           => $detalle->cantidad,
                    'precio_unitario'    => $detalle->precio,
                    'subtotal_bruto'     => $detalle->subtotal,
                    'tipo_descuento'     => $detalle->tipo_descuento,
                    'descuento'          => $detalle->descuento,
                    'descuento_aplicado' => $detalle->descuento_aplicado,
                    'subtotal_neto'      => $subtotalNeto,
                    'iva_porcentaje'     => $ivaPorcentaje,
                    'iva_monto'          => round($ivaMonto, 2),
                ];
            });

            // datos del plan de credito si existe
            $planPago = null;
            if ($venta->planPago) {
                $plan = $venta->planPago;
                $planPago = [
                    'cliente'          => optional($plan->cliente)->nombre . ' ' . optional($plan->cliente)->apellido_p,
                    'total_a_pagar'    => $plan->total_a_pagar,
                    'anticipo'         => $plan->anticipo,
                    'saldo_pendiente'  => $plan->saldo_pendiente,
                    'num_plazos'       => $plan->num_plazos,
                    'tipo_plazo'       => $plan->tipo_plazo,
                    'monto_cuota'      => $plan->monto_cuota,
                    'fecha_inicio'     => $plan->fecha_inicio,
                    'fecha_proximo_pago' => $plan->fecha_proximo_pago,
                    'interes_aplicado' => $plan->interes_aplicado,
                ];
            }

            return $this->Success([
                'ticket' => [
                    'id'               => $venta->id,
                    'folio'            => $venta->folio,
                    'modo_iva'         => $venta->modo_iva,
                    'iva_total'        => $venta->iva_total,
                    'fecha'            => $venta->created_at->format('d/m/Y H:i'),
                    'metodo_pago'      => $venta->metodo_pago,
                    'pago'             => $venta->pago,
                    'cambio'           => $venta->cambio,
                    'subtotal'         => $venta->subtotal,
                    'total'            => $venta->total,
                    'establecimiento'  => optional($venta->establecimiento)->nombre,
                    'logo_url'         => $logoUrl,
                    'productos'        => $productos,
                    'es_credito'       => $venta->planPago !== null,
                    'plan_pago'        => $planPago,
                ]
            ]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener el ticket.', 'details' => $e->getMessage()]);
        }
    }
}
