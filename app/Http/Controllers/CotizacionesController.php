<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\{ConfiguracionEstablecimiento, Establecimiento, Ventas, Products, Cajas, HistorialCajas, VentasDetalles};
use Carbon\Carbon;

class CotizacionesController extends Controller
{
    public function __construct()
    {
        // configuramos la zona horaria mexicana igual que en VentasController
        date_default_timezone_set('America/Mexico_City');
        Carbon::setLocale('es');
        Carbon::now()->setTimezone('America/Mexico_City');
    }

    public function index(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $query = Cotizacion::with(['cliente', 'detalles'])
                ->where('establecimiento_id', $establecimiento_id);

            // filtro por folio o folio de venta
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('folio', 'like', "%{$request->search}%")
                    ->orWhere('venta_folio', 'like', "%{$request->search}%");
                });
            }

            // filtro por status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // filtro por cliente nombre
            if ($request->filled('cliente')) {
                $query->whereHas('cliente', function ($q) use ($request) {
                    $q->where('nombre', 'like', "%{$request->cliente}%")
                    ->orWhere('apellido_p', 'like', "%{$request->cliente}%");
                });
            }

            // filtro por fecha de creacion
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            $query->orderBy('created_at', 'desc');

            $perPage   = $request->get('per_page', 10);
            $paginator = $query->paginate($perPage);

            // formateamos la fecha y agregamos venta_folio en los items
            $items = collect($paginator->items())->map(function ($cot) {
                return [
                    'id'          => $cot->id,
                    'folio'       => $cot->folio,
                    'venta_folio' => $cot->venta_folio,
                    'venta_id'    => $cot->venta_id,
                    'status'      => $cot->status,
                    'total'       => $cot->total,
                    'notas'       => $cot->notas,
                    'expires_at'  => $cot->expires_at
                        ? Carbon::parse($cot->expires_at)->format('d/m/Y')
                        : null,
                    'created_at'  => Carbon::parse($cot->created_at)->format('d/m/Y h:i A'),
                    'cliente'     => [
                        'nombre'    => optional($cot->cliente)->nombre,
                        'apellido_p'=> optional($cot->cliente)->apellido_p,
                    ],
                    'detalles'    => $cot->detalles,
                ];
            });

            return response()->json([
                'data'         => $items,
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ], 200);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al obtener las cotizaciones.',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            // buscamos la caja abierta
            $caja = Cajas::where('establecimiento_id', $establecimiento_id)
                ->where('abierta', true)
                ->first();

            if (!$caja) {
                return $this->BadRequest('No hay una caja abierta para registrar la cotización.');
            }

            // buscamos el historial de la caja abierta
            $historialCaja = HistorialCajas::where('caja_id', $caja->id)
                ->where('estado', 'abierta')
                ->first();

            if (!$historialCaja) {
                return $this->BadRequest('No se encontró un historial de caja abierto.');
            }

            // obtenemos la configuracion de iva del establecimiento
            $config  = ConfiguracionEstablecimiento::where('establecimiento_id', $establecimiento_id)->first();
            $modoIva = $config->modo_iva ?? 'sin_iva';

            // calculamos el iva total igual que en ventas
            $ivaTotalCotizacion = 0;

            foreach ($request->detalles as $detalle) {
                $producto = Products::find($detalle['producto_id']);
                if (!$producto) continue;

                $subtotalNeto  = ($detalle['precio'] * $detalle['cantidad']) - ($detalle['descuento_aplicado'] ?? 0);
                $ivaPorcentaje = $producto->iva ?? 0;

                if ($modoIva === 'sin_iva' || $ivaPorcentaje == 0) {
                    $ivaMonto = 0;
                } elseif ($modoIva === 'iva_incluido') {
                    $ivaMonto = $subtotalNeto - ($subtotalNeto / (1 + $ivaPorcentaje / 100));
                } else {
                    // iva_adicional
                    $ivaMonto = $subtotalNeto * ($ivaPorcentaje / 100);
                }

                $ivaTotalCotizacion += $ivaMonto;
            }

            $totalFinal = $request->total_final;
            if ($modoIva === 'iva_adicional') {
                $totalFinal = $request->total_final + $ivaTotalCotizacion;
            }

            // creamos la cotizacion
            $cotizacion                    = new Cotizacion();
            $cotizacion->establecimiento_id = $establecimiento_id;
            $cotizacion->usuario_id         = $request->usuario_id;
            $cotizacion->cliente_id         = $request->cliente_id;
            $cotizacion->historial_caja_id  = $historialCaja->id ?? null;
            $cotizacion->folio              = $this->generarFolioCotizacion($establecimiento_id);
            $cotizacion->status             = 'pendiente';
            $cotizacion->modo_iva           = $modoIva;
            $cotizacion->iva_total          = round($ivaTotalCotizacion, 2);
            $cotizacion->subtotal           = $request->total; // total sin descuentos ni iva
            $cotizacion->total              = round($totalFinal, 2);
            $cotizacion->notas              = $request->notas ?? null;
            $cotizacion->expires_at         = $request->expires_at ?? null;
            $cotizacion->created_at         = Carbon::now();
            $cotizacion->save();

            // guardamos los detalles sin tocar el stock
            foreach ($request->detalles as $detalle) {
                $producto = Products::find($detalle['producto_id']);

                $detalleCotizacion                    = new CotizacionDetalle();
                $detalleCotizacion->cotizacion_id      = $cotizacion->id;
                $detalleCotizacion->producto_id        = $detalle['producto_id'];
                $detalleCotizacion->nombre_producto    = $producto->nombre ?? $detalle['nombre'] ?? 'Producto';
                $detalleCotizacion->precio             = $detalle['precio'];
                $detalleCotizacion->precio_compra      = $detalle['precio_compra'];
                $detalleCotizacion->cantidad           = $detalle['cantidad'];
                $detalleCotizacion->subtotal           = $detalle['precio'] * $detalle['cantidad'];
                $detalleCotizacion->tipo_descuento     = $detalle['tipo_descuento'] ?? null;
                $detalleCotizacion->descuento          = $detalle['descuento'] ?? 0;
                $detalleCotizacion->descuento_aplicado = $detalle['descuento_aplicado'] ?? 0;
                $detalleCotizacion->iva_porcentaje     = $producto->iva ?? 0;
                $detalleCotizacion->save();
            }

            DB::commit();

            return $this->Success([
                'message'    => 'Cotizacion registrada exitosamente.',
                'cotizacion' => $cotizacion
            ]);

        } catch (ValidationException $ve) {
            DB::rollBack();
            throw $ve;
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([
                'error'   => 'Error al registrar la cotizacion.',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function actualizarDetalles(Request $request, int $id)
    {
        DB::beginTransaction();
        try {
            $cotizacion = Cotizacion::with('detalles')->findOrFail($id);

            if ($cotizacion->status !== 'pendiente') {
                return $this->BadRequest([
                    'message' => 'Solo se pueden editar cotizaciones en estado pendiente.'
                ]);
            }

            $establecimiento_id = app('establishment_id');
            $config  = ConfiguracionEstablecimiento::where('establecimiento_id', $establecimiento_id)->first();
            $modoIva = $config->modo_iva ?? 'sin_iva';

            // ids de detalles que vienen en la peticion (excluyendo nuevos con id 0)
            $idsRecibidos = collect($request->detalles)
                ->pluck('cotizacion_detalle_id')
                ->filter(fn($id) => $id > 0)
                ->toArray();

            // eliminamos los detalles que el operador quito
            $cotizacion->detalles()
                ->whereNotIn('id', $idsRecibidos)
                ->delete();

            $nuevoSubtotal = 0;
            $nuevoIvaTotal = 0;

            foreach ($request->detalles as $item) {

                // producto nuevo que se agrega a la cotizacion
                if (empty($item['cotizacion_detalle_id']) || $item['cotizacion_detalle_id'] == 0) {
                    $producto = Products::find($item['producto_id']);

                    if (!$producto) continue;

                    $detalle = new CotizacionDetalle();
                    $detalle->cotizacion_id      = $cotizacion->id;
                    $detalle->producto_id        = $item['producto_id'];
                    $detalle->nombre_producto    = $producto->nombre;
                    $detalle->precio             = $item['precio'];
                    $detalle->precio_compra      = $producto->precio_compra;
                    $detalle->cantidad           = $item['cantidad'];
                    $detalle->subtotal           = $item['precio'] * $item['cantidad'];
                    $detalle->tipo_descuento     = $item['tipo_descuento'] ?? null;
                    $detalle->descuento          = $item['descuento'] ?? 0;
                    $detalle->descuento_aplicado = $item['descuento_aplicado'] ?? 0;
                    $detalle->iva_porcentaje     = $producto->iva ?? 0;
                    $detalle->save();

                } else {
                    // detalle existente que se actualiza
                    $detalle = CotizacionDetalle::find($item['cotizacion_detalle_id']);
                    if (!$detalle || $detalle->cotizacion_id !== $cotizacion->id) continue;

                    $detalle->cantidad           = $item['cantidad'];
                    $detalle->precio             = $item['precio'];
                    $detalle->tipo_descuento     = $item['tipo_descuento'] ?? null;
                    $detalle->descuento          = $item['descuento'] ?? 0;
                    $detalle->descuento_aplicado = $item['descuento_aplicado'] ?? 0;
                    $detalle->subtotal           = $item['precio'] * $item['cantidad'];
                    $detalle->save();
                }

                // calculo de iva igual para ambos casos
                $subtotalNeto  = $detalle->subtotal - $detalle->descuento_aplicado;
                $ivaPorcentaje = $detalle->iva_porcentaje ?? 0;
                $ivaMonto      = 0;

                if ($modoIva === 'iva_incluido' && $ivaPorcentaje > 0) {
                    $ivaMonto = $subtotalNeto - ($subtotalNeto / (1 + $ivaPorcentaje / 100));
                } elseif ($modoIva === 'iva_adicional' && $ivaPorcentaje > 0) {
                    $ivaMonto = $subtotalNeto * ($ivaPorcentaje / 100);
                }

                $nuevoSubtotal += $detalle->subtotal;
                $nuevoIvaTotal += $ivaMonto;
            }

            // recalculamos el total de la cotizacion
            $totalFinal = $nuevoSubtotal - $cotizacion->detalles()->sum('descuento_aplicado');
            if ($modoIva === 'iva_adicional') {
                $totalFinal += $nuevoIvaTotal;
            }

            $cotizacion->subtotal  = round($nuevoSubtotal, 2);
            $cotizacion->iva_total = round($nuevoIvaTotal, 2);
            $cotizacion->total     = round($totalFinal, 2);
            $cotizacion->save();

            DB::commit();

            return $this->Success([
                'message'    => 'Cotizacion actualizada correctamente.',
                'cotizacion' => $cotizacion->fresh(['detalles']),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([
                'error'   => 'Error al actualizar la cotizacion.',
                'details' => $e->getMessage()
            ]);
        }
    }

    private function generarFolioCotizacion(int $establecimiento_id): string
    {
        $establecimiento = Establecimiento::find($establecimiento_id);
        $nombre          = $establecimiento->nombre ?? 'XX';
        $iniciales       = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nombre), 0, 2));
        $anio            = Carbon::now()->format('y');

        $consecutivo = Cotizacion::where('establecimiento_id', $establecimiento_id)
            ->whereYear('created_at', Carbon::now()->year)
            ->count() + 1;

        return 'COT-' . $iniciales . $anio . str_pad($consecutivo, 3, '0', STR_PAD_LEFT);
    }

    public function ticketCotizacion(int $id)
    {
        try {
            $cotizacion = Cotizacion::with([
                'detalles.producto',
                'establecimiento',
                'cliente',
            ])->findOrFail($id);

            $config       = ConfiguracionEstablecimiento::where('establecimiento_id', $cotizacion->establecimiento_id)->first();
            $formatoHora  = $config->formato_hora ?? '12h';
            $formatoFecha = $config->formato_fecha ?? 'd/m/Y';

            // convertimos el logo a base64 igual que en ventas
            $logoBase64 = null;
            if ($cotizacion->establecimiento && $cotizacion->establecimiento->logo) {
                $logoPath = storage_path('app/public/' . $cotizacion->establecimiento->logo);
                if (file_exists($logoPath)) {
                    $contenido  = file_get_contents($logoPath);
                    $mime       = mime_content_type($logoPath);
                    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($contenido);
                }
            }

            // armamos los productos con sus calculos de iva
            $productos = $cotizacion->detalles->map(function ($detalle) use ($cotizacion) {
                $subtotalNeto  = $detalle->subtotal - $detalle->descuento_aplicado;
                $ivaPorcentaje = $detalle->iva_porcentaje ?? 0;
                $ivaMonto      = 0;

                if ($cotizacion->modo_iva === 'iva_incluido' && $ivaPorcentaje > 0) {
                    $ivaMonto = $subtotalNeto - ($subtotalNeto / (1 + $ivaPorcentaje / 100));
                } elseif ($cotizacion->modo_iva === 'iva_adicional' && $ivaPorcentaje > 0) {
                    $ivaMonto = $subtotalNeto * ($ivaPorcentaje / 100);
                }

                return [
                    'nombre'             => $detalle->nombre_producto,
                    'imagen_url'         => optional($detalle->producto)->imagen_url ?? null,
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

            return $this->Success([
                'ticket' => [
                    'id'              => $cotizacion->id,
                    'folio'           => $cotizacion->folio,
                    'status'          => $cotizacion->status,
                    'modo_iva'        => $cotizacion->modo_iva,
                    'iva_total'       => $cotizacion->iva_total,
                    'fecha'           => $cotizacion->created_at->format($formatoFecha . ' ' . ($formatoHora === '12h' ? 'h:i A' : 'H:i')),
                    'expires_at'      => $cotizacion->expires_at
                        ? Carbon::parse($cotizacion->expires_at)->format($formatoFecha)
                        : null,
                    'subtotal'        => $cotizacion->subtotal,
                    'total'           => $cotizacion->total,
                    'notas'           => $cotizacion->notas,
                    'establecimiento' => optional($cotizacion->establecimiento)->nombre,
                    'logo_url'        => $logoBase64,
                    'formato_hora'    => $formatoHora,
                    'formato_fecha'   => $formatoFecha,
                    'num_cuenta'      => $config->num_cuenta ?? null,
                    'cliente'         => [
                        'nombre'    => optional($cotizacion->cliente)->nombre,
                        'apellido'  => optional($cotizacion->cliente)->apellido_p,
                        'telefono'  => optional($cotizacion->cliente)->telefono ?? null,
                    ],
                    'productos'       => $productos,
                    'venta_folio'     => $cotizacion->venta_folio,
                ]
            ]);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al obtener el ticket de cotizacion.',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function comprobarStock(int $id)
    {
        try {
            $cotizacion = Cotizacion::with('detalles.producto')->findOrFail($id);

            if ($cotizacion->status !== 'pendiente') {
                return $this->BadRequest([
                    'message' => 'Solo se pueden comprobar cotizaciones en estado pendiente.'
                ]);
            }

            $productos        = [];
            $hayProblemas     = false;

            foreach ($cotizacion->detalles as $detalle) {
                $producto        = $detalle->producto;
                $stockDisponible = 0;
                $problema        = false;
                $mensaje         = null;

                // si el producto fue eliminado
                if (!$producto) {
                    $problema        = true;
                    $hayProblemas    = true;
                    $mensaje         = 'El producto ya no existe en el sistema.';

                // si es servicio no se valida stock
                } elseif ($producto->es_servicio) {
                    $stockDisponible = null;

                // si el stock es menor a la cantidad cotizada
                } elseif ($producto->stock < $detalle->cantidad) {
                    $problema        = true;
                    $hayProblemas    = true;
                    $stockDisponible = $producto->stock;
                    $mensaje         = "Cotizado: {$detalle->cantidad}, disponible: {$producto->stock}.";

                } else {
                    $stockDisponible = $producto->stock;
                }

                $productos[] = [
                    'cotizacion_detalle_id' => $detalle->id,
                    'producto_id'           => $detalle->producto_id,
                    'nombre'                => $detalle->nombre_producto,
                    'cantidad_cotizada'     => $detalle->cantidad,
                    'stock_disponible'      => $stockDisponible,
                    'es_servicio'           => $producto->es_servicio ?? false,
                    'problema'              => $problema,
                    'mensaje'               => $mensaje,
                ];
            }

            return $this->Success([
                'hay_problemas' => $hayProblemas,
                'productos'     => $productos,
            ]);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al comprobar la cotizacion.',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function convertirVenta(Request $request, int $id)
    {
        try {
            $cotizacion = Cotizacion::with('detalles')->findOrFail($id);

            if ($cotizacion->status !== 'pendiente') {
                return $this->BadRequest([
                    'message' => 'Esta cotizacion ya no esta en estado pendiente.'
                ]);
            }

            // verificamos caja abierta antes de intentar la venta
            $establecimiento_id = app('establishment_id');
            $caja = Cajas::where('establecimiento_id', $establecimiento_id)
                ->where('abierta', true)
                ->first();

            if (!$caja) {
                return $this->BadRequest([
                    'message' => 'No hay una caja abierta para registrar la venta.'
                ]);
            }

            // delegamos al store de ventas, el maneja su propia transaccion
            $ventasController = new VentasController();
            $response         = $ventasController->store($request);
            $responseData     = json_decode($response->getContent(), true);

            // si la venta fallo devolvemos el error sin tocar la cotizacion
            if ($response->getStatusCode() !== 200) {
                return $response;
            }

            // la venta fue exitosa, ahora actualizamos la cotizacion
            // este save es simple, no necesita transaccion propia
            $cotizacion->status       = 'vendido';
            $cotizacion->venta_id     = $responseData['data']['venta']['id'];
            $cotizacion->venta_folio  = $responseData['data']['venta']['folio'];
            $cotizacion->converted_at = Carbon::now();
            $cotizacion->save();

            // devolvemos la respuesta de ventas tal cual para el ticket
            return $response;

        } catch (ValidationException $ve) {
            throw $ve;
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al convertir la cotizacion.',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function cancelar(int $id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);

            if ($cotizacion->status !== 'pendiente') {
                return $this->BadRequest([
                    'message' => 'Solo se pueden cancelar cotizaciones en estado pendiente.'
                ]);
            }

            // solo cambiamos el status, no se toca ningun producto ni stock
            $cotizacion->status = 'cancelado';
            $cotizacion->save();

            return $this->Success([
                'message' => 'Cotizacion cancelada correctamente.'
            ]);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al cancelar la cotizacion.',
                'details' => $e->getMessage()
            ]);
        }
    }
}