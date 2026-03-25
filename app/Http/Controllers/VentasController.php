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
use App\Exports\VentasHistorialExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
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

            // obtenemos la configuracion del establecimiento para formatos del ticket
            $config = ConfiguracionEstablecimiento::where('establecimiento_id', $venta->establecimiento_id)->first();
            $formatoHora  = $config->formato_hora ?? '12h';
            $formatoFecha = $config->formato_fecha ?? 'd/m/Y';

            // convertimos el logo a base64 para evitar problemas de CORS en el frontend
            $logoBase64 = null;
            if ($venta->establecimiento && $venta->establecimiento->logo) {
                $logoPath = storage_path('app/public/' . $venta->establecimiento->logo);
                if (file_exists($logoPath)) {
                    $contenido = file_get_contents($logoPath);
                    $mime      = mime_content_type($logoPath);
                    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($contenido);
                }
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
                    'detalle_id'         => $detalle->id,
                    'producto_id'        => $detalle->producto_id,
                    'es_servicio'        => $detalle->producto->es_servicio ?? false,
                    'stock_disponible'   => $detalle->producto ? $detalle->producto->stock : 0,
                    'precio_compra'      => $detalle->precio_compra,
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
                    'intervalo_dias'   => $plan->intervalo_dias,
                    'monto_cuota'      => $plan->monto_cuota,
                    'fecha_inicio'       => Carbon::parse($plan->fecha_inicio)->format($formatoFecha),
                    'fecha_proximo_pago' => Carbon::parse($plan->fecha_proximo_pago)->format($formatoFecha),
                    'interes_aplicado' => $plan->interes_aplicado,
                ];
            }

            return $this->Success([
                'ticket' => [
                    'id'               => $venta->id,
                    'folio'            => $venta->folio,
                    'modo_iva'         => $venta->modo_iva,
                    'iva_total'        => $venta->iva_total,
                    'fecha'            => $venta->created_at->format($formatoFecha . ' ' . ($formatoHora === '12h' ? 'h:i A' : 'H:i')),
                    'metodo_pago'      => $venta->metodo_pago,
                    'pago'             => $venta->pago,
                    'cambio'           => $venta->cambio,
                    'subtotal'         => $venta->subtotal,
                    'total'            => $venta->total,
                    'establecimiento'  => optional($venta->establecimiento)->nombre,
                    'logo_url'         => $logoBase64,
                    'formato_hora'     => $formatoHora,
                    'formato_fecha'    => $formatoFecha,
                    'num_cuenta'       => $config->num_cuenta ?? null,
                    'productos'        => $productos,
                    'es_credito'       => $venta->planPago !== null,
                    'plan_pago'        => $planPago,
                ]
            ]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener el ticket.', 'details' => $e->getMessage()]);
        }
    }

    // Historial de ventas con filtros
    public function historial(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $query = Ventas::with(['detalles.producto', 'planPago.cliente', 'establecimiento'])
                ->where('establecimiento_id', $establecimiento_id);

            // filtro por rango de fechas
            if ($request->filled('desde')) {
                $query->whereDate('created_at', '>=', $request->desde);
            }
            if ($request->filled('hasta')) {
                $query->whereDate('created_at', '<=', $request->hasta);
            }

            // filtro por folio o metodo de pago
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'like', "%{$search}%")
                    ->orWhere('metodo_pago', 'like', "%{$search}%");
                });
            }

            // filtro por status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $query->orderBy('created_at', 'desc');

            $perPage   = $request->get('per_page', 10);
            $paginator = $query->paginate($perPage);

            $data = [];
            foreach ($paginator->items() as $venta) {
                $data[] = [
                    'id'            => $venta->id,
                    'folio'         => $venta->folio,
                    'status'        => $venta->status ?? 'vendida',
                    'fecha'         => $venta->created_at->format('d/m/Y H:i'),
                    'metodo_pago'   => $venta->metodo_pago,
                    'subtotal'      => $venta->subtotal,
                    'iva_total'     => $venta->iva_total,
                    'total'         => $venta->total,
                    'pago'          => $venta->pago,
                    'cambio'        => $venta->cambio,
                    'es_credito'    => $venta->planPago !== null,
                    'cliente'       => $venta->planPago?->cliente
                        ? $venta->planPago->cliente->nombre . ' ' . $venta->planPago->cliente->apellido_p
                        : null,
                    'num_productos' => $venta->detalles->sum('cantidad'),
                ];
            }

            return response()->json([
                'data'         => $data,
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ], 200);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener el historial', 'message' => $e->getMessage()]);
        }
    }

    public function actualizarMetodoPago(Request $request, int $id)
    {
        try {
            $venta = Ventas::findOrFail($id);

            if (($venta->status ?? 'vendida') === 'cancelada') {
                return $this->BadRequest([
                    'message' => 'No se puede editar una venta cancelada.'
                ]);
            }

            $venta->metodo_pago    = $request->metodo_pago;
            $venta->metodo_pago_id = $request->metodo_pago_id ?? null;
            $venta->save();

            return $this->Success([
                'message' => 'Metodo de pago actualizado correctamente.',
                'venta'   => $venta,
            ]);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al actualizar el metodo de pago.',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function actualizarDetalles(Request $request, int $id)
    {
        DB::beginTransaction();
        try {
            $venta = Ventas::with('detalles')->findOrFail($id);

            if (($venta->status ?? 'vendida') === 'cancelada') {
                return $this->BadRequest([
                    'message' => 'No se puede editar una venta cancelada.'
                ]);
            }

            $establecimiento_id = app('establishment_id');
            $config  = ConfiguracionEstablecimiento::where('establecimiento_id', $establecimiento_id)->first();
            $modoIva = $config->modo_iva ?? 'sin_iva';

            // ids de detalles que vienen en la peticion
            $idsRecibidos = collect($request->detalles)->pluck('detalle_id')->filter()->toArray();

            // detalles que se eliminaron: devolver stock
            $detallesToDelete = $venta->detalles()->whereNotIn('id', $idsRecibidos)->get();
            foreach ($detallesToDelete as $det) {
                $producto = $det->producto;
                if ($producto && !$producto->es_servicio) {
                    $producto->stock += $det->cantidad;
                    $producto->save();
                }
                $det->delete();
            }

            $nuevoSubtotal = 0;
            $nuevoIvaTotal = 0;
            $nuevoDescuentoTotal = 0;

            foreach ($request->detalles as $item) {
                $detalle = VentasDetalles::find($item['detalle_id']);
                if (!$detalle || $detalle->venta_id !== $venta->id) continue;

                $producto = $detalle->producto;

                // ajustamos stock por la diferencia de cantidad
                if ($producto && !$producto->es_servicio) {
                    $diferencia = $item['cantidad'] - $detalle->cantidad;
                    if ($diferencia > 0 && $producto->stock < $diferencia) {
                        DB::rollBack();
                        return $this->BadRequest([
                            'message' => "Stock insuficiente de: {$producto->nombre}. Disponible: {$producto->stock}"
                        ]);
                    }
                    $producto->stock -= $diferencia;
                    $producto->save();
                }

                $detalle->cantidad           = $item['cantidad'];
                $detalle->precio             = $item['precio'];
                $detalle->tipo_descuento     = $item['tipo_descuento'] ?? null;
                $detalle->descuento          = $item['descuento'] ?? 0;
                $detalle->descuento_aplicado = $item['descuento_aplicado'] ?? 0;
                $detalle->subtotal           = $item['precio'] * $item['cantidad'];
                $detalle->save();

                $subtotalNeto  = $detalle->subtotal - $detalle->descuento_aplicado;
                $ivaPorcentaje = $detalle->iva_porcentaje ?? 0;
                $ivaMonto      = 0;

                if ($modoIva === 'iva_incluido' && $ivaPorcentaje > 0) {
                    $ivaMonto = $subtotalNeto - ($subtotalNeto / (1 + $ivaPorcentaje / 100));
                } elseif ($modoIva === 'iva_adicional' && $ivaPorcentaje > 0) {
                    $ivaMonto = $subtotalNeto * ($ivaPorcentaje / 100);
                }

                $nuevoSubtotal       += $detalle->subtotal;
                $nuevoIvaTotal       += $ivaMonto;
                $nuevoDescuentoTotal += $detalle->descuento_aplicado;
            }

            // recalculamos totales de la venta
            $totalFinal = $nuevoSubtotal - $nuevoDescuentoTotal;
            if ($modoIva === 'iva_adicional') {
                $totalFinal += $nuevoIvaTotal;
            }

            $venta->subtotal  = round($nuevoSubtotal, 2);
            $venta->iva_total = round($nuevoIvaTotal, 2);
            $venta->total     = round($totalFinal, 2);
            $venta->save();

            DB::commit();

            return $this->Success([
                'message' => 'Venta actualizada correctamente.',
                'venta'   => $venta->fresh(['detalles']),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([
                'error'   => 'Error al actualizar la venta.',
                'details' => $e->getMessage()
            ]);
        }
    }

    // Cancelacion de venta devuelve los productos
    public function cancelarVenta(int $id)
    {
        DB::beginTransaction();
        try {
            $venta = Ventas::with(['detalles.producto', 'planPago'])->findOrFail($id);

            if (($venta->status ?? 'vendido') === 'cancelada') {
                return $this->BadRequest([
                    'message' => 'Esta venta ya esta cancelada.'
                ]);
            }

            // devolvemos el stock de cada producto que no sea servicio
            foreach ($venta->detalles as $detalle) {
                $producto = $detalle->producto;
                if ($producto && !$producto->es_servicio) {
                    $producto->stock += $detalle->cantidad;
                    $producto->save();
                }
            }

            // si la venta tiene plan de pago (credito), lo cancelamos tambien
            if ($venta->planPago) {
                $plan = $venta->planPago;
                $plan->estado = 'cancelado';
                $plan->save();
            }

            $venta->status = 'cancelada';
            $venta->save();

            DB::commit();

            return $this->Success([
                'message' => 'Venta cancelada y stock devuelto correctamente.',
                'plan_cancelado' => $venta->planPago ? true : false,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([
                'error'   => 'Error al cancelar la venta.',
                'details' => $e->getMessage()
            ]);
        }
    }

    /**
     * Exportar historial de ventas en formato Excel (.xlsx)
     * Recibe fecha_inicio y fecha_fin como parametros
     */
    public function exportHistorialExcel(Request $request)
    {
        try {
            $request->validate([
                'desde' => 'nullable|date',
                'hasta' => 'nullable|date',
            ]);
 
            $datos = $this->obtenerDatosHistorial($request);
 
            $export = new VentasHistorialExport(
                $datos['ventas'],
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
                $datos['establecimiento_nombre']
            );
 
            $nombreArchivo = 'historial_ventas_' . $datos['fecha_inicio'] . '_al_' . $datos['fecha_fin'] . '.xlsx';
 
            return Excel::download($export, $nombreArchivo);
 
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al generar el reporte Excel: ' . $e->getMessage()
            ], 500);
        }
    }
 
    /**
     * Exportar historial de ventas en formato PDF
     * Recibe fecha_inicio y fecha_fin como parametros
     */
    public function exportHistorialPdf(Request $request)
    {
        try {
            $request->validate([
                'desde' => 'nullable|date',
                'hasta' => 'nullable|date',
            ]);
 
            $datos = $this->obtenerDatosHistorial($request);
            $resumen = $this->calcularResumenHistorial($datos['ventas']);
            $logo = $this->obtenerLogoEstablecimiento();
 
            $pdf = Pdf::loadView('pdf.ventas_historial', [
                'ventas'          => $datos['ventas'],
                'resumen'         => $resumen,
                'fechaInicio'     => $datos['fecha_inicio'],
                'fechaFin'        => $datos['fecha_fin'],
                'establecimiento' => $datos['establecimiento_nombre'],
                'logo'            => $logo,
            ]);
 
            $pdf->setPaper('letter', 'landscape');
 
            $nombreArchivo = 'historial_ventas_' . $datos['fecha_inicio'] . '_al_' . $datos['fecha_fin'] . '.pdf';
 
            return $pdf->stream($nombreArchivo);
 
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al generar el reporte PDF: ' . $e->getMessage()
            ], 500);
        }
    }
 
    /**
     * Obtiene las ventas con sus detalles y relaciones para el periodo dado
     * Reutilizando los filtros de la vista
     */
    private function obtenerDatosHistorial(Request $request): array
    {
        $establecimiento_id = app('establishment_id');

        $query = Ventas::where('establecimiento_id', $establecimiento_id)
            ->with([
                'detalles.producto',
                'planPago.cliente',
            ]);

        // Mismos filtros que el metodo historial()
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                ->orWhere('metodo_pago', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $ventas = $query->orderBy('created_at', 'asc')->get();

        // Nombre del establecimiento
        $establecimientoNombre = '';
        $userEstablecimiento = UserEstablecimiento::with('establecimiento')
            ->where('establecimiento_id', $establecimiento_id)
            ->first();

        if ($userEstablecimiento && $userEstablecimiento->establecimiento) {
            $establecimientoNombre = $userEstablecimiento->establecimiento->nombre;
        }

        return [
            'ventas'                 => $ventas,
            'fecha_inicio'           => $request->desde ?? 'Inicio',
            'fecha_fin'              => $request->hasta ?? 'Actual',
            'establecimiento_nombre' => $establecimientoNombre,
        ];
    }
 
    /**
     * Calcula el resumen completo del historial de ventas
     * Totales, ganancias, descuentos, cancelaciones, etc.
     */
    private function calcularResumenHistorial($ventas): array
    {
        $totalVendido = 0;
        $totalCancelado = 0;
        $totalDescuentos = 0;
        $totalIva = 0;
        $totalCostoCompra = 0;
        $totalGanancia = 0;
        $cantProductos = 0;
        $ventasActivas = 0;
        $ventasCanceladas = 0;
 
        foreach ($ventas as $venta) {
            $esCancelada = ($venta->status ?? 'vendido') === 'cancelada';
 
            if ($esCancelada) {
                $totalCancelado += $venta->total;
                $ventasCanceladas++;
                continue;
            }
 
            $ventasActivas++;
            $totalVendido += $venta->total;
            $totalIva += ($venta->iva_total ?? 0);
 
            foreach ($venta->detalles as $detalle) {
                $precioVenta = $detalle->precio;
                $precioCompra = $detalle->precio_compra ?? 0;
                $cantidad = $detalle->cantidad;
                $descuento = $detalle->descuento_aplicado ?? 0;
                $subtotal = ($precioVenta * $cantidad) - $descuento;
                $costoTotal = $precioCompra * $cantidad;
 
                $totalDescuentos += $descuento;
                $totalCostoCompra += $costoTotal;
                $totalGanancia += ($subtotal - $costoTotal);
                $cantProductos += $cantidad;
            }
        }
 
        return [
            'total_ventas'      => $ventas->count(),
            'ventas_activas'    => $ventasActivas,
            'ventas_canceladas' => $ventasCanceladas,
            'total_productos'   => $cantProductos,
            'total_descuentos'  => round($totalDescuentos, 2),
            'total_iva'         => round($totalIva, 2),
            'total_vendido'     => round($totalVendido, 2),
            'total_cancelado'   => round($totalCancelado, 2),
            'total_costo_compra' => round($totalCostoCompra, 2),
            'ganancia_neta'     => round($totalGanancia, 2),
        ];
    }
 
    /**
     * Obtiene el logo del establecimiento en base64
     * Sin logo por defecto para reportes
     */
    private function obtenerLogoEstablecimiento(): ?string
    {
        $establecimiento_id = app('establishment_id');
        $logo = null;
 
        $userEstablecimiento = UserEstablecimiento::with('establecimiento')
            ->where('establecimiento_id', $establecimiento_id)
            ->first();
 
        if ($userEstablecimiento && $userEstablecimiento->establecimiento) {
            $establecimiento = $userEstablecimiento->establecimiento;
 
            if (!empty($establecimiento->logo)) {
                $logoPath = public_path('storage/' . $establecimiento->logo);
                if (file_exists($logoPath)) {
                    $logo = base64_encode(file_get_contents($logoPath));
                }
            }
        }
 
        return $logo;
    }

}
