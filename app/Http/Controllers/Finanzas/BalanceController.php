<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use App\Models\Ventas;
use App\Models\Gastos;
use App\Models\PagoPlan;
use Carbon\Carbon;

class BalanceController extends Controller
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Retorna el balance mensual (ingresos vs gastos) de un mes especifico
     * Usado para la tarjeta principal con grafica de barras o dona
     */
    public function balanceMensual()
    {
        try {
            $establecimiento_id = app('establishment_id');

            $month = $this->request->get('month', now()->month);
            $year  = $this->request->get('year', now()->year);

            // ids de ventas que tienen plan de pago (son credito)
            $ventasMes = Ventas::where('establecimiento_id', $establecimiento_id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where(function ($q) {
                    $q->where('status', '!=', 'cancelada')
                    ->orWhereNull('status');
                })
                ->with('planPago')
                ->get();

            $ventasCreditoIds = $ventasMes->filter(fn($v) => $v->planPago !== null)
                ->pluck('id')
                ->toArray();

            // ventas de contado: usar total (lo que vale la venta)
            // ventas a credito: usar pago (el anticipo que realmente entro)
            $ingresosVentas = $ventasMes->sum(function ($v) use ($ventasCreditoIds) {
                if (in_array($v->id, $ventasCreditoIds)) {
                    return $v->pago;
                }
                return $v->total;
            });

            // abonos a credito cobrados en el mes
            $ingresosAbonos = PagoPlan::whereHas('plan', function ($q) use ($establecimiento_id) {
                    $q->where('establecimiento_id', $establecimiento_id)
                    ->where('estado', '!=', 'cancelado');
                })
                ->whereYear('fecha_pago', $year)
                ->whereMonth('fecha_pago', $month)
                ->sum('monto_pagado');

            $ingresos = $ingresosVentas + $ingresosAbonos;

            $gastos = Gastos::where('establecimiento_id', $establecimiento_id)
                ->whereYear('fecha', $year)
                ->whereMonth('fecha', $month)
                ->where('state', 1)
                ->sum('monto');

            $balance  = round($ingresos - $gastos, 2);
            $ingresos = round($ingresos, 2);
            $gastos   = round($gastos, 2);

            return $this->Success([
                'mes'           => (int) $month,
                'anio'          => (int) $year,
                'ingresos'      => $ingresos,
                'gastos'        => $gastos,
                'balance'       => $balance,       // negativo = perdida
                'es_ganancia'   => $balance >= 0,
            ]);

        } catch (Exception $e) {
            return $this->InternalError('Error al calcular el balance mensual: ' . $e->getMessage());
        }
    }

    /**
     * Retorna el historial de N meses atras para la grafica de lineas dobles
     * El frontend envia cuantos meses quiere ver (por defecto 6)
     */
    public function historial()
    {
        try {
            $establecimiento_id = app('establishment_id');

            // cantidad de meses hacia atras que el usuario selecciona
            $meses = (int) $this->request->get('meses', 6);
            // limitamos a maximo 24 para no sobrecargar
            $meses = min($meses, 24);

            $resultado = [];

            for ($i = $meses - 1; $i >= 0; $i--) {

                $fecha = Carbon::now()->subMonths($i);
                $month = $fecha->month;
                $year  = $fecha->year;

                $ventasMes = Ventas::where('establecimiento_id', $establecimiento_id)
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->where(function ($q) {
                        $q->where('status', '!=', 'cancelada')
                        ->orWhereNull('status');
                    })
                    ->with('planPago')
                    ->get();

                $ventasCreditoIds = $ventasMes->filter(fn($v) => $v->planPago !== null)
                    ->pluck('id')
                    ->toArray();

                $ingresosVentas = $ventasMes->sum(function ($v) use ($ventasCreditoIds) {
                    if (in_array($v->id, $ventasCreditoIds)) {
                        return $v->pago;
                    }
                    return $v->total;
                });

                $ingresosAbonos = PagoPlan::whereHas('plan', function ($q) use ($establecimiento_id) {
                        $q->where('establecimiento_id', $establecimiento_id)
                        ->where('estado', '!=', 'cancelado');
                    })
                    ->whereYear('fecha_pago', $year)
                    ->whereMonth('fecha_pago', $month)
                    ->sum('monto_pagado');

                $ingresos = $ingresosVentas + $ingresosAbonos;

                $gastos = Gastos::where('establecimiento_id', $establecimiento_id)
                    ->whereYear('fecha', $year)
                    ->whereMonth('fecha', $month)
                    ->where('state', 1)
                    ->sum('monto');

                $ingresos = round($ingresos, 2);
                $gastos   = round($gastos, 2);
                $balance  = round($ingresos - $gastos, 2);

                $resultado[] = [
                    'mes'         => $month,
                    'anio'        => $year,
                    'label'       => $fecha->translatedFormat('M Y'), // Ene 2025
                    'ingresos'    => $ingresos,
                    'gastos'      => $gastos,
                    'balance'     => $balance,
                    'es_ganancia' => $balance >= 0,
                ];
            }

            return $this->Success([
                'historial' => $resultado,
                'meses'     => $meses,
            ]);

        } catch (Exception $e) {
            return $this->InternalError('Error al obtener el historial de balance: ' . $e->getMessage());
        }
    }

    /**
     * Retorna el desglose diario del mes actual para grafica de lineas dia por dia
     * Util para ver la tendencia dentro del mes seleccionado
     */
    public function desgloseDiario()
    {
        try {
            $establecimiento_id = app('establishment_id');

            $month = $this->request->get('month', now()->month);
            $year  = $this->request->get('year', now()->year);

            $ventasMes = Ventas::where('establecimiento_id', $establecimiento_id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where(function ($q) {
                    $q->where('status', '!=', 'cancelada')
                    ->orWhereNull('status');
                })
                ->with('planPago')
                ->get();

            $ventasCreditoIds = $ventasMes->filter(fn($v) => $v->planPago !== null)
                ->pluck('id')
                ->toArray();

            // agrupar por dia sumando correctamente
            $ventasPorDia = $ventasMes->groupBy(fn($v) => $v->created_at->day)
                ->map(function ($ventas) use ($ventasCreditoIds) {
                    return $ventas->sum(function ($v) use ($ventasCreditoIds) {
                        if (in_array($v->id, $ventasCreditoIds)) {
                            return $v->pago;
                        }
                        return $v->total;
                    });
                });

            // abonos a credito agrupados por dia
            $abonosPorDia = PagoPlan::whereHas('plan', function ($q) use ($establecimiento_id) {
                    $q->where('establecimiento_id', $establecimiento_id)
                    ->where('estado', '!=', 'cancelado');
                })
                ->whereYear('fecha_pago', $year)
                ->whereMonth('fecha_pago', $month)
                ->selectRaw('DAY(fecha_pago) as dia, SUM(monto_pagado) as total')
                ->groupBy('dia')
                ->pluck('total', 'dia');

            // agrupamos ventas por dia del mes
            $ventasPorDia = Ventas::where('establecimiento_id', $establecimiento_id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where(function ($q) {
                    $q->where('status', '!=', 'cancelada')
                    ->orWhereNull('status');
                })
                ->selectRaw('DAY(created_at) as dia, SUM(total) as total')
                ->groupBy('dia')
                ->pluck('total', 'dia'); // [dia => total]

            // agrupamos gastos por dia del mes
            $gastosPorDia = Gastos::where('establecimiento_id', $establecimiento_id)
                ->whereYear('fecha', $year)
                ->whereMonth('fecha', $month)
                ->where('state', 1)
                ->selectRaw('DAY(fecha) as dia, SUM(monto) as total')
                ->groupBy('dia')
                ->pluck('total', 'dia'); // [dia => total]

            $daysInMonth = Carbon::create($year, $month)->daysInMonth;
            $resultado   = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $ingresos = round($ventasPorDia->get($day, 0) + $abonosPorDia->get($day, 0), 2);
                $gastos   = round($gastosPorDia->get($day, 0), 2);

                $resultado[] = [
                    'dia'      => $day,
                    'ingresos' => $ingresos,
                    'gastos'   => $gastos,
                    'balance'  => round($ingresos - $gastos, 2),
                ];
            }

            return $this->Success([
                'mes'      => (int) $month,
                'anio'     => (int) $year,
                'detalle'  => $resultado,
            ]);

        } catch (Exception $e) {
            return $this->InternalError('Error al obtener el desglose diario: ' . $e->getMessage());
        }
    }
}