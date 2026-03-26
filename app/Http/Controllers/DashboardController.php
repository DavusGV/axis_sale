<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{
    Cotizacion,
    Ventas,
    Gastos,
    PagoPlan,
    PlanPago,
    Products,
    VentasDetalles
};
use Carbon\Carbon;
use App\Models\UserEstablecimiento;
use App\Exports\DashboardExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class DashboardController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('America/Mexico_City');
        Carbon::setLocale('es');
        Carbon::now()->setTimezone('America/Mexico_City');
    }

    public function index(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            // INGRESOS DEL DIA
            // Obtenemos todas las ventas de hoy con su plan de pago
            // para saber cuales son credito y cuales contado
            $ventasHoy = Ventas::where('establecimiento_id', $establecimiento_id)
                ->whereDate('created_at', Carbon::today())
                ->where(function ($q) {
                    $q->where('status', '!=', 'cancelada')
                      ->orWhereNull('status');
                })
                ->with('planPago') // cargamos la relacion para detectar creditos
                ->get();

            // Detectamos cuales ventas son a credito (tienen plan de pago)
            $ventasCreditoIds = $ventasHoy->filter(fn($v) => $v->planPago !== null)
                ->pluck('id')
                ->toArray();

            // Sumamos: si es credito usamos 'pago' (el anticipo que entro),
            // si es contado usamos 'total' (el valor completo)
            $ingresosVentas = $ventasHoy->sum(function ($v) use ($ventasCreditoIds) {
                if (in_array($v->id, $ventasCreditoIds)) {
                    return $v->pago; // anticipo del credito
                }
                return $v->total; // venta de contado
            });

            // Abonos a creditos cobrados hoy (pagos de planes activos)
            $ingresosAbonos = PagoPlan::whereHas('plan', function ($q) use ($establecimiento_id) {
                    $q->where('establecimiento_id', $establecimiento_id)
                      ->where('estado', '!=', 'cancelado');
                })
                ->whereDate('fecha_pago', Carbon::today())
                ->sum('monto_pagado');

            $ingresosDia = round($ingresosVentas + $ingresosAbonos, 2);

            // GASTOS DEL DIA
            $gastosDia = Gastos::where('establecimiento_id', $establecimiento_id)
                ->whereDate('fecha', Carbon::today())
                ->where('state', 1)
                ->sum('monto');

            $gastosDia = round($gastosDia, 2);

            // GANANCIAS DEL DIA
            // Reutilizamos las ventas de hoy, cargamos detalles
            $ventasHoy->load('detalles');

            $gananciasDia = $ventasHoy->sum(function ($venta) {
                return $venta->detalles->sum(function ($detalle) {
                    // ganancia = (precio venta - precio compra) * cantidad - descuento aplicado
                    return (($detalle->precio - $detalle->precio_compra) * $detalle->cantidad) - $detalle->descuento_aplicado;
                });
            });

            $gananciasDia = round($gananciasDia, 2);

            // DESCUENTOS DEL DIA
            $descuentosDia = VentasDetalles::whereHas('venta', function ($q) use ($establecimiento_id) {
                    $q->where('establecimiento_id', $establecimiento_id)
                    ->whereDate('created_at', Carbon::today())
                    ->where(function ($q2) {
                        $q2->where('status', '!=', 'cancelada')
                            ->orWhereNull('status');
                    });
                })
                ->sum('descuento_aplicado');

            $descuentosDia = round($descuentosDia, 2);

            // CREDITOS PENDIENTES (count)
            $creditosPendientes = PlanPago::where('establecimiento_id', $establecimiento_id)
                ->where('estado', 'activo')
                ->count();

            // COTIZACIONES PENDIENTES (count)
            $cotizacionesPendientes = Cotizacion::where('establecimiento_id', $establecimiento_id)
                ->where('status', 'pendiente')
                ->count();

            // TENDENCIA SEMANAL (domingo a sabado)
            // Calculamos el rango de la semana actual
            $inicioSemana = Carbon::now()->startOfWeek(Carbon::SUNDAY);
            $finSemana    = Carbon::now()->endOfWeek(Carbon::SATURDAY);

            // Agrupamos las ventas por fecha dentro de la semana
            $ventasSemana = Ventas::where('establecimiento_id', $establecimiento_id)
                ->whereBetween('created_at', [$inicioSemana, $finSemana])
                ->where(function ($q) {
                    $q->where('status', '!=', 'cancelada')
                      ->orWhereNull('status');
                })
                ->selectRaw('DATE(created_at) as fecha, SUM(total) as total')
                ->groupBy('fecha')
                ->pluck('total', 'fecha'); // resultado: ['2026-03-22' => 500, '2026-03-23' => 300]

            // Armamos un array con los 7 dias de la semana
            // Si un dia no tiene ventas, se pone 0
            $diasSemana = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
            $tendenciaSemanal = [];

            for ($i = 0; $i < 7; $i++) {
                $fecha = $inicioSemana->copy()->addDays($i)->format('Y-m-d');
                $tendenciaSemanal[] = [
                    'dia'   => $diasSemana[$i],
                    'fecha' => $fecha,
                    'total' => round($ventasSemana->get($fecha, 0), 2),
                ];
            }

            // STOCK DE PRODUCTOS (menos de 10 y en 0)
            $productosStockBajo = Products::where('establecimiento_id', $establecimiento_id)
                ->where('es_servicio', false)
                ->where('stock', '<', 10)
                ->orderBy('stock', 'asc') // los de 0 primero
                ->get(['id', 'nombre', 'stock']); // solo los campos que necesitamos

            // Separamos: los que tienen 0 y los que tienen entre 1 y 9
            $stockCero    = $productosStockBajo->where('stock', '<=', 0)->values();
            $stockBajo    = $productosStockBajo->where('stock', '>', 0)->values();

            // TOP 3 PRODUCTOS MAS VENDIDOS
            $topProductos = VentasDetalles::whereHas('venta', function ($q) use ($establecimiento_id) {
                    $q->where('establecimiento_id', $establecimiento_id)
                      ->where(function ($q2) {
                          $q2->where('status', '!=', 'cancelada')
                             ->orWhereNull('status');
                      });
                })
                ->selectRaw('producto_id, SUM(cantidad) as total_vendido')
                ->groupBy('producto_id')
                ->orderByDesc('total_vendido')
                ->take(3)
                ->get();

            // Cargamos el nombre del producto
            $topProductos->load('producto:id,nombre');

            $topProductosData = $topProductos->map(function ($item) {
                return [
                    'producto_id'  => $item->producto_id,
                    'nombre'       => $item->producto->nombre ?? 'Producto eliminado',
                    'total_vendido' => (int) $item->total_vendido,
                ];
            });

            // VENTAS DEL DIA - PRODUCTOS VENDIDOS HOY
            // Similar al top pero filtrado solo a hoy
            $ventasDiaProductos = VentasDetalles::whereHas('venta', function ($q) use ($establecimiento_id) {
                    $q->where('establecimiento_id', $establecimiento_id)
                      ->whereDate('created_at', Carbon::today())
                      ->where(function ($q2) {
                          $q2->where('status', '!=', 'cancelada')
                             ->orWhereNull('status');
                      });
                })
                ->selectRaw('producto_id, SUM(cantidad) as total_vendido')
                ->groupBy('producto_id')
                ->orderByDesc('total_vendido')
                ->get();

            $ventasDiaProductos->load('producto:id,nombre');

            $ventasDiaData = $ventasDiaProductos->map(function ($item) {
                return [
                    'producto_id'   => $item->producto_id,
                    'nombre'        => $item->producto->nombre ?? 'Producto eliminado',
                    'total_vendido' => (int) $item->total_vendido,
                ];
            });

            return $this->Success([
                // tarjetas superiores
                'ingresos_dia'              => $ingresosDia,
                'gastos_dia'                => $gastosDia,
                'creditos_pendientes'       => $creditosPendientes,
                'cotizaciones_pendientes'   => $cotizacionesPendientes,
                'descuentos'                => $descuentosDia,
                'ganancias'                 => $gananciasDia,

                // grafica de barras semanal
                'tendencia_semanal'         => $tendenciaSemanal,

                // stock
                'stock_cero'                => $stockCero,
                'stock_bajo'                => $stockBajo,

                // top 3 productos mas vendidos (historico)
                'top_productos'             => $topProductosData,

                // productos vendidos hoy con cantidades
                'ventas_dia_productos'      => $ventasDiaData,
            ]);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al obtener los datos del dashboard',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Exportar dashboard en formato Excel
     */
    public function exportExcel()
    {
        try {
            $datos = $this->obtenerDatosDashboard();

            $export = new DashboardExport(
                $datos['data'],
                $datos['establecimiento_nombre'],
                $datos['fecha']
            );

            $nombreArchivo = 'dashboard_' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download($export, $nombreArchivo);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al generar el reporte Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar dashboard en formato PDF
     */
    public function exportPdf()
    {
        try {
            $datos = $this->obtenerDatosDashboard();
            $logo  = obtenerLogoEstablecimiento();

            $pdf = Pdf::loadView('pdf.dashboard_reporte', [
                'data'            => $datos['data'],
                'establecimiento' => $datos['establecimiento_nombre'],
                'fecha'           => $datos['fecha'],
                'logo'            => $logo,
            ]);

            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'dashboard_' . now()->format('Y-m-d') . '.pdf';

            return $pdf->stream($nombreArchivo);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al generar el reporte PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todos los datos del dashboard para reutilizar en exports
     * Misma logica que index() pero retorna array en lugar de response
     */
    private function obtenerDatosDashboard(): array
    {
        $establecimiento_id = app('establishment_id');

        // INGRESOS DEL DIA
        $ventasHoy = Ventas::where('establecimiento_id', $establecimiento_id)
            ->whereDate('created_at', Carbon::today())
            ->where(function ($q) {
                $q->where('status', '!=', 'cancelada')
                  ->orWhereNull('status');
            })
            ->with('planPago')
            ->get();

        $ventasCreditoIds = $ventasHoy->filter(fn($v) => $v->planPago !== null)
            ->pluck('id')
            ->toArray();

        $ingresosVentas = $ventasHoy->sum(function ($v) use ($ventasCreditoIds) {
            if (in_array($v->id, $ventasCreditoIds)) {
                return $v->pago;
            }
            return $v->total;
        });

        $ingresosAbonos = PagoPlan::whereHas('plan', function ($q) use ($establecimiento_id) {
                $q->where('establecimiento_id', $establecimiento_id)
                  ->where('estado', '!=', 'cancelado');
            })
            ->whereDate('fecha_pago', Carbon::today())
            ->sum('monto_pagado');

        $ingresosDia = round($ingresosVentas + $ingresosAbonos, 2);

        // GASTOS DEL DIA
        $gastosDia = round(Gastos::where('establecimiento_id', $establecimiento_id)
            ->whereDate('fecha', Carbon::today())
            ->where('state', 1)
            ->sum('monto'), 2);

        // GANANCIAS DEL DIA
        $ventasHoy->load('detalles');
        $gananciasDia = round($ventasHoy->sum(function ($venta) {
            return $venta->detalles->sum(function ($detalle) {
                return (($detalle->precio - $detalle->precio_compra) * $detalle->cantidad) - $detalle->descuento_aplicado;
            });
        }), 2);

        // DESCUENTOS DEL DIA
        $descuentosDia = round(VentasDetalles::whereHas('venta', function ($q) use ($establecimiento_id) {
                $q->where('establecimiento_id', $establecimiento_id)
                ->whereDate('created_at', Carbon::today())
                ->where(function ($q2) {
                    $q2->where('status', '!=', 'cancelada')
                        ->orWhereNull('status');
                });
            })
            ->sum('descuento_aplicado'), 2);

        // CREDITOS Y COTIZACIONES PENDIENTES
        $creditosPendientes = PlanPago::where('establecimiento_id', $establecimiento_id)
            ->where('estado', 'activo')
            ->count();

        $cotizacionesPendientes = Cotizacion::where('establecimiento_id', $establecimiento_id)
            ->where('status', 'pendiente')
            ->count();

        // TENDENCIA SEMANAL
        $inicioSemana = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        $finSemana = Carbon::now()->endOfWeek(Carbon::SATURDAY);

        $ventasSemana = Ventas::where('establecimiento_id', $establecimiento_id)
            ->whereBetween('created_at', [$inicioSemana, $finSemana])
            ->where(function ($q) {
                $q->where('status', '!=', 'cancelada')
                  ->orWhereNull('status');
            })
            ->selectRaw('DATE(created_at) as fecha, SUM(total) as total')
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        $diasSemana = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
        $tendenciaSemanal = [];

        for ($i = 0; $i < 7; $i++) {
            $fecha = $inicioSemana->copy()->addDays($i)->format('Y-m-d');
            $tendenciaSemanal[] = [
                'dia'   => $diasSemana[$i],
                'fecha' => $fecha,
                'total' => round($ventasSemana->get($fecha, 0), 2),
            ];
        }

        // STOCK
        $productosStockBajo = Products::where('establecimiento_id', $establecimiento_id)
            ->where('es_servicio', false)
            ->where('stock', '<', 10)
            ->orderBy('stock', 'asc')
            ->get(['id', 'nombre', 'stock']);

        $stockCero = $productosStockBajo->where('stock', '<=', 0)->values()->toArray();
        $stockBajo = $productosStockBajo->where('stock', '>', 0)->values()->toArray();

        // TOP PRODUCTOS
        $topProductos = VentasDetalles::whereHas('venta', function ($q) use ($establecimiento_id) {
                $q->where('establecimiento_id', $establecimiento_id)
                  ->where(function ($q2) {
                      $q2->where('status', '!=', 'cancelada')
                         ->orWhereNull('status');
                  });
            })
            ->selectRaw('producto_id, SUM(cantidad) as total_vendido')
            ->groupBy('producto_id')
            ->orderByDesc('total_vendido')
            ->take(3)
            ->get();

        $topProductos->load('producto:id,nombre');

        $topProductosData = $topProductos->map(function ($item) {
            return [
                'producto_id'   => $item->producto_id,
                'nombre'        => $item->producto->nombre ?? 'Producto eliminado',
                'total_vendido' => (int) $item->total_vendido,
            ];
        })->toArray();

        // VENTAS DEL DIA PRODUCTOS
        $ventasDiaProductos = VentasDetalles::whereHas('venta', function ($q) use ($establecimiento_id) {
                $q->where('establecimiento_id', $establecimiento_id)
                  ->whereDate('created_at', Carbon::today())
                  ->where(function ($q2) {
                      $q2->where('status', '!=', 'cancelada')
                         ->orWhereNull('status');
                  });
            })
            ->selectRaw('producto_id, SUM(cantidad) as total_vendido')
            ->groupBy('producto_id')
            ->orderByDesc('total_vendido')
            ->get();

        $ventasDiaProductos->load('producto:id,nombre');

        $ventasDiaData = $ventasDiaProductos->map(function ($item) {
            return [
                'producto_id'   => $item->producto_id,
                'nombre'        => $item->producto->nombre ?? 'Producto eliminado',
                'total_vendido' => (int) $item->total_vendido,
            ];
        })->toArray();

        // Nombre del establecimiento
        $establecimientoNombre = '';
        $userEstablecimiento = UserEstablecimiento::with('establecimiento')
            ->where('establecimiento_id', $establecimiento_id)
            ->first();

        if ($userEstablecimiento && $userEstablecimiento->establecimiento) {
            $establecimientoNombre = $userEstablecimiento->establecimiento->nombre;
        }

        return [
            'data' => [
                'ingresos_dia'            => $ingresosDia,
                'gastos_dia'              => $gastosDia,
                'ganancias'               => $gananciasDia,
                'descuentos'              => $descuentosDia,
                'creditos_pendientes'     => $creditosPendientes,
                'cotizaciones_pendientes' => $cotizacionesPendientes,
                'tendencia_semanal'       => $tendenciaSemanal,
                'stock_cero'              => $stockCero,
                'stock_bajo'              => $stockBajo,
                'top_productos'           => $topProductosData,
                'ventas_dia_productos'    => $ventasDiaData,
            ],
            'establecimiento_nombre' => $establecimientoNombre,
            'fecha'                  => now()->format('d/m/Y H:i'),
        ];
    }
}