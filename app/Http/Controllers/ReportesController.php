<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use \Illuminate\Support\Carbon;
use App\Models\Cajas;
use App\Models\HistorialCajas;
use PhpParser\Node\Expr\FuncCall;
use App\Models\Ventas;
use App\Models\VentasDetalles;
use App\Models\PagoPlan;
use App\Models\PlanPago;
use App\Models\UserEstablecimiento;
use Exception;


class ReportesController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {

    }

    public function ventasReport(Request $request)
    {
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');

        $establecimiento_id = app('establishment_id');

        $query = VentasDetalles::query()
            ->with('producto:id,nombre')
            ->whereHas('venta', function ($q) use ($establecimiento_id) {
                $q->where('establecimiento_id', $establecimiento_id)
                ->where(function ($sq) {
                    $sq->where('status', '!=', 'cancelada')
                        ->orWhereNull('status');
                });
            })
            ->when($desde, fn($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('created_at', '<=', $hasta));

        $detalles = $query->get();

        // cargar ventas con plan de pago para detectar credito
        $ventaIds = $detalles->pluck('venta_id')->unique();
        $ventasConPlan = Ventas::whereIn('id', $ventaIds)
            ->where(function ($q) {
                $q->where('status', '!=', 'cancelada')
                ->orWhereNull('status');
            })
            ->with('planPago')
            ->get()
            ->keyBy('id');

        // agrupar productos con descuentos aplicados
        $productos = $detalles->groupBy('producto_id')->map(function ($group) {
            $producto = optional($group->first()->producto);
            $inversion = $group->sum(fn($i) => $i->precio_compra * $i->cantidad);
            $vendido = $group->sum(function ($i) {
                $sub = $i->precio * $i->cantidad;
                return $sub - ($i->descuento_aplicado ?? 0);
            });

            return [
                'producto_id' => $group->first()->producto_id,
                'producto'    => $producto->nombre ?? 'Desconocido',
                'inversion'   => round($inversion, 2),
                'vendido'     => round($vendido, 2),
                'ganancia'    => round($vendido - $inversion, 2),
            ];
        })->values();

        // inversion total de toda la mercancia vendida
        $totalInversion = $productos->sum('inversion');
        // valor total de lo vendido (con descuentos)
        $totalVendido = $productos->sum('vendido');

        // ventas de contado: usar total (lo que vale la venta)
        $ventasContadoIds = $ventasConPlan->filter(fn($v) => !$v->planPago)->pluck('id')->toArray();
        $cobradoContado = $ventasConPlan->filter(fn($v) => !$v->planPago)->sum('total');

        // ventas a credito: anticipos + abonos = lo que realmente ya pagaron
        $ventasCreditoConPlan = $ventasConPlan->filter(fn($v) => $v->planPago);
        $anticipos = $ventasCreditoConPlan->sum('pago');

        $planesIds = $ventasCreditoConPlan->pluck('planPago.id');
        $abonos = PagoPlan::whereIn('plan_pago_id', $planesIds)
            ->sum('monto_pagado');

        $cobradoCredito = round($anticipos + $abonos, 2);

        // saldo pendiente real desde los planes
        $pendienteCredito = round($ventasCreditoConPlan->sum(fn($v) => $v->planPago->saldo_pendiente), 2);

        // cobrado real = contado + lo cobrado de credito (sin intereses, solo valor mercancia)
        // pero el anticipo y abonos incluyen intereses, hay que limitar al valor de la mercancia
        $valorMercanciaCredito = $ventasCreditoConPlan->sum('total');
        // lo cobrado de mercancia credito es el minimo entre cobrado y valor mercancia
        $cobradoMercanciaCred = min($cobradoCredito, $valorMercanciaCredito);

        $cobradoReal = round($cobradoContado + $cobradoMercanciaCred, 2);
        $gananciaReal = round($cobradoReal - $totalInversion, 2);

        // pendiente es solo lo que falta de la mercancia (sin intereses)
        $pendienteMercancia = round(max($valorMercanciaCredito - $cobradoMercanciaCred, 0), 2);

        return $this->Success([
            'productos' => $productos,
            'totales'   => [
                'inversion'          => round($totalInversion, 2),
                'vendido'            => round($totalVendido, 2),
                'cobrado_real'       => $cobradoReal,
                'ganancia_real'      => $gananciaReal,
                'pendiente_credito'  => $pendienteMercancia,
                'cobrado_contado'    => round($cobradoContado, 2),
                'cobrado_credito'    => $cobradoMercanciaCred,
            ],
        ]);
    }

    /**
     * Reporte de creditos: detalle de planes de pago con comisiones y estado
     */
    public function creditosReport(Request $request)
    {
        try {
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');
            $estado = $request->input('estado'); // activo, liquidado, vencido, o null para todos

            $establecimiento_id = app('establishment_id');

            $query = PlanPago::with(['cliente', 'venta', 'pagos'])
                ->where('establecimiento_id', $establecimiento_id)
                ->where('estado', '!=', 'cancelado')
                ->when($desde, fn($q) => $q->whereDate('fecha_inicio', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('fecha_inicio', '<=', $hasta))
                ->when($estado, fn($q) => $q->where('estado', $estado))
                ->orderBy('created_at', 'desc');

            $planes = $query->get();

            // detalle por cada credito (la tabla)
            $detalle = $planes->map(function ($plan) {
                $totalAbonos = $plan->pagos->sum('monto_pagado');
                $anticipo = $plan->anticipo ?? 0;
                $totalCobrado = $anticipo + $totalAbonos;

                // lo que falta de mercancia = total_venta - anticipo - abonos aplicados a mercancia
                $faltaMercancia = max($plan->total_venta - min($totalCobrado, $plan->total_venta), 0);

                // comision cobrada = lo cobrado que excede el valor de la mercancia
                $comisionCobrada = max($totalCobrado - $plan->total_venta, 0);
                // comision pendiente = interes total - comision ya cobrada
                $comisionPendiente = max($plan->interes_aplicado - $comisionCobrada, 0);

                return [
                    'plan_id'            => $plan->id,
                    'cliente'            => ($plan->cliente->nombre ?? '') . ' ' . ($plan->cliente->apellido_p ?? ''),
                    'telefono'           => $plan->cliente->telefono1 ?? '',
                    'venta_id'           => $plan->venta_id,
                    'total_venta'        => round($plan->total_venta, 2),
                    'anticipo'           => round($anticipo, 2),
                    'falta_mercancia'    => round($faltaMercancia, 2),
                    'comision'           => round($plan->interes_aplicado, 2),
                    'comision_cobrada'   => round($comisionCobrada, 2),
                    'comision_pendiente' => round($comisionPendiente, 2),
                    'total_a_pagar'      => round($plan->total_a_pagar, 2),
                    'total_cobrado'      => round($totalCobrado, 2),
                    'saldo_pendiente'    => round($plan->saldo_pendiente, 2),
                    'num_plazos'         => $plan->num_plazos,
                    'tipo_plazo'         => $plan->tipo_plazo,
                    'estado'             => $plan->estado,
                    'fecha_inicio'       => $plan->fecha_inicio,
                    'fecha_proximo_pago' => $plan->fecha_proximo_pago,
                ];
            });

            // totales para las tarjetas
            $totalCreditos = $planes->count();
            $creditosActivos = $planes->where('estado', 'activo')->count();
            $creditosLiquidados = $planes->where('estado', 'liquidado')->count();

            $totalVentaCredito = $planes->sum('total_venta');
            $totalAnticipos = $planes->sum('anticipo');
            $totalIntereses = $planes->sum('interes_aplicado');
            $totalAPagar = $planes->sum('total_a_pagar');

            $totalAbonos = $planes->sum(fn($p) => $p->pagos->sum('monto_pagado'));
            $totalCobrado = $totalAnticipos + $totalAbonos;
            $totalPendiente = $planes->sum('saldo_pendiente');

            // comisiones cobradas vs pendientes
            $comisionCobradaTotal = $detalle->sum('comision_cobrada');
            $comisionPendienteTotal = $detalle->sum('comision_pendiente');

            // datos para la grafica por mes
            $porMes = $planes->groupBy(function ($plan) {
                return \Carbon\Carbon::parse($plan->fecha_inicio)->format('Y-m');
            })->map(function ($grupo, $mes) {
                $cobrado = $grupo->sum(function ($p) {
                    return $p->anticipo + $p->pagos->sum('monto_pagado');
                });
                $pendiente = $grupo->sum('saldo_pendiente');
                $comisiones = $grupo->sum('interes_aplicado');

                return [
                    'mes'        => $mes,
                    'label'      => Carbon::parse($mes . '-01')->translatedFormat('M Y'),
                    'cobrado'    => round($cobrado, 2),
                    'pendiente'  => round($pendiente, 2),
                    'comisiones' => round($comisiones, 2),
                ];
            })->values();

            return $this->Success([
                'detalle'  => $detalle,
                'totales'  => [
                    'total_creditos'      => $totalCreditos,
                    'activos'             => $creditosActivos,
                    'liquidados'          => $creditosLiquidados,
                    'total_venta'         => round($totalVentaCredito, 2),
                    'total_anticipos'     => round($totalAnticipos, 2),
                    'total_intereses'     => round($totalIntereses, 2),
                    'total_a_pagar'       => round($totalAPagar, 2),
                    'total_cobrado'       => round($totalCobrado, 2),
                    'total_pendiente'     => round($totalPendiente, 2),
                    'comision_cobrada'    => round($comisionCobradaTotal, 2),
                    'comision_pendiente'  => round($comisionPendienteTotal, 2),
                ],
                'por_mes' => $porMes,
            ]);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al generar reporte de creditos.', 'details' => $e->getMessage()]);
        }
    }

}
