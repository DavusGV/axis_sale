<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use App\Models\Ventas;
use App\Models\MetodoPago;
use App\Models\PagoPlan;
use App\Models\PlanPago;
use App\Models\Establecimiento;
use Carbon\Carbon;

class IngresosControlador extends Controller
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getIncome()
    {
        try {
            $idestablishment = $this->request->get('idestablishment');
            $month           = $this->request->get('month');
            $year            = $this->request->get('year');

            $establishment = Establecimiento::find($idestablishment);
            $created_at    = Carbon::parse($establishment->created_at)->format('Y-m-d');

            // metodos de pago activos del establecimiento
            $metodosPagoDb = MetodoPago::where('establecimiento_id', $idestablishment)
                ->where('estado', 1)
                ->whereRaw("LOWER(TRIM(nombre)) != 'credito'")
                ->get(['id', 'nombre']);

            $paymentMethod = $metodosPagoDb->pluck('nombre')->toArray();
            $paymentMethod[] = 'credito';// debera elimarse cuando las ventas y los establecimientos tengas registrado el metodo de pago

            $mapaIdNombre = $metodosPagoDb->pluck('nombre', 'id');
            $mapaNombreId = $metodosPagoDb->mapWithKeys(fn($m) => [
                strtolower(trim($m->nombre)) => $m->id
            ]);

            // ventas del mes — excluimos canceladas y cargamos planPago para detectar credito
            $income = Ventas::where('establecimiento_id', $idestablishment)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where(function ($q) {
                    $q->where('status', '!=', 'cancelada')
                    ->orWhereNull('status');
                })
                ->with('planPago')
                ->get();

            // detectar metodos huerfanos de ventas viejas (texto sin metodo_pago_id) se debera eliminar luego de que ya esten todos los metodos de pago
            // usar un sql en ticker o bd directamente mediante los metodos de pago que esten en la bade de datos de los establecimientos
            $metodosHuerfanos = $income
                ->filter(fn($v) => !$v->metodo_pago_id && $v->metodo_pago)
                ->pluck('metodo_pago')
                ->map(fn($m) => trim($m))
                ->unique()
                ->filter(function ($nombre) use ($mapaNombreId) {
                    return !$mapaNombreId->has(strtolower($nombre))
                        && strtolower($nombre) !== 'credito';
                });

            foreach ($metodosHuerfanos as $metodo) {
                if (!in_array($metodo, $paymentMethod)) {
                    $paymentMethod[] = $metodo;
                }
            }

            // ids de ventas que tienen plan de pago (son credito)
            $ventasCredito = $income->filter(fn($v) => $v->planPago !== null)
                ->pluck('id')
                ->toArray();

            // abonos cobrados en el mes
            $abonosPorDia = PagoPlan::whereHas('plan', function ($q) use ($idestablishment) {
                    $q->where('establecimiento_id', $idestablishment)
                    ->where('estado', '!=', 'cancelado');
                })
                ->whereYear('fecha_pago', $year)
                ->whereMonth('fecha_pago', $month)
                ->selectRaw('DAY(fecha_pago) as dia, SUM(monto_pagado) as total')
                ->groupBy('dia')
                ->pluck('total', 'dia');

            $ventasPorDia = $income->groupBy(fn($v) => $v->created_at->day);

            $daysInMonth = Carbon::create($year, $month)->daysInMonth;
            $resultado   = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {

                $fecha     = Carbon::create($year, $month, $day);
                $ventasDia = $ventasPorDia->get($day, collect());

                // agrupar TODAS las ventas del dia por metodo de pago
                $metodosTotales = $ventasDia
                    ->groupBy(function ($venta) use ($mapaIdNombre, $mapaNombreId, $ventasCredito) {
                        // ventas a credito: el anticipo se agrupa en "Efectivo" por defecto
                        // ya que el anticipo se cobra en caja como efectivo
                        if (in_array($venta->id, $ventasCredito)) {
                            // si tiene metodo_pago_id valido y no es credito, usarlo
                            if ($venta->metodo_pago_id && $mapaIdNombre->has($venta->metodo_pago_id)) {
                                $nombre = $mapaIdNombre->get($venta->metodo_pago_id);
                                if (strtolower(trim($nombre)) !== 'credito') {
                                    return $nombre;
                                }
                            }
                            // si no tiene metodo valido o es "credito", asignar a Efectivo
                            return 'Efectivo';
                        }

                        // ventas normales
                        if ($venta->metodo_pago_id && $mapaIdNombre->has($venta->metodo_pago_id)) {
                            return $mapaIdNombre->get($venta->metodo_pago_id);
                        }
                        $nombreNormalizado = strtolower(trim($venta->metodo_pago));
                        if ($mapaNombreId->has($nombreNormalizado)) {
                            return $mapaIdNombre->get($mapaNombreId->get($nombreNormalizado));
                        }
                        return $venta->metodo_pago;
                    })
                    ->map(function ($items) use ($ventasCredito) {
                        return $items->sum(function ($v) use ($ventasCredito) {
                            // si es credito, solo cuenta el anticipo (pago)
                            if (in_array($v->id, $ventasCredito)) {
                                return $v->pago;
                            }
                            // contado: usar total (sin incluir cambio)
                            return $v->total;
                        });
                    });

                // construir fila con todos los metodos + credito
                $metodosCompletos = collect($paymentMethod)
                    ->mapWithKeys(function ($nombre) use ($metodosTotales, $abonosPorDia, $day) {
                        if ($nombre === 'credito') {
                            return ['credito' => round($abonosPorDia->get($day, 0), 2)];
                        }
                        return [$nombre => $metodosTotales->get($nombre, 0)];
                    });

                $resultado[] = [
                    'name'          => $fecha->translatedFormat('l'),
                    'date'          => $day,
                    'paymentMethod' => $metodosCompletos,
                    'total'         => round($metodosCompletos->sum(), 2),
                ];
            }

            $data['income']        = $resultado;
            $data['paymentMethod'] = $paymentMethod;
            $data['created_at']    = $created_at;

            return $this->Success($data);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al cargar la informacion de ingresos',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getMovimientos()
    {
        try {
            $idestablishment = $this->request->get('idestablishment');
            $month           = $this->request->get('month');
            $year            = $this->request->get('year');
            $perPage         = (int) $this->request->get('per_page', 20);

            $movimientos = collect();

            // ventas del mes no canceladas con planPago para detectar tipo
            $ventas = Ventas::where('establecimiento_id', $idestablishment)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where(function ($q) {
                    $q->where('status', '!=', 'cancelada')
                    ->orWhereNull('status');
                })
                ->with('planPago')
                ->get();

            // separar ids de creditos con plan ligado
            $ventasCreditoIds = $ventas->filter(fn($v) => $v->planPago !== null)
                ->pluck('id')
                ->toArray();

            // ventas huerfanas (metodo credito sin plan): se excluyen
            $ventasHuerfanas = $ventas->filter(function ($v) use ($ventasCreditoIds) {
                return !in_array($v->id, $ventasCreditoIds)
                    && strtolower(trim($v->metodo_pago ?? '')) === 'credito';
            })->pluck('id')->toArray();

            foreach ($ventas as $venta) {
                // excluir huerfanas
                if (in_array($venta->id, $ventasHuerfanas)) {
                    continue;
                }

                if (in_array($venta->id, $ventasCreditoIds)) {
                    // no registrar anticipos sin monto, no hubo entrada de dinero
                    if ($venta->pago <= 0) {
                        continue;
                    }
                    $movimientos->push([
                        'tipo'        => 'Anticipo',
                        'folio'       => $venta->folio,
                        'metodo_pago' => 'Efectivo',
                        'monto'       => round($venta->pago, 2),
                        'fecha'       => $venta->created_at->format('d/m/Y'),
                        'fecha_sort'  => $venta->created_at,
                    ]);
                } else {
                    // es contado
                    $movimientos->push([
                        'tipo'        => 'Venta contado',
                        'folio'       => $venta->folio,
                        'metodo_pago' => $venta->metodo_pago ?? 'Sin metodo',
                        'monto'       => round($venta->total, 2),
                        'fecha'       => $venta->created_at->format('d/m/Y'),
                        'fecha_sort'  => $venta->created_at,
                    ]);
                }
            }

            // abonos del mes con plan no cancelado
            $abonos = PagoPlan::whereHas('plan', function ($q) use ($idestablishment) {
                    $q->where('establecimiento_id', $idestablishment)
                    ->where('estado', '!=', 'cancelado');
                })
                ->whereYear('fecha_pago', $year)
                ->whereMonth('fecha_pago', $month)
                ->with('plan.venta')
                ->get();

            foreach ($abonos as $abono) {
                $folio = $abono->plan && $abono->plan->venta
                    ? $abono->plan->venta->folio
                    : 'Sin folio';

                $movimientos->push([
                    'tipo'        => 'Abono credito',
                    'folio'       => $folio,
                    'metodo_pago' => $abono->metodo_pago ?? 'Sin metodo',
                    'monto'       => round($abono->monto_pagado, 2),
                    'fecha'       => $abono->fecha_pago->format('d/m/Y'),
                    'fecha_sort'  => $abono->fecha_pago,
                ]);
            }

            // ordenar por fecha y paginar manualmente
            $movimientos = $movimientos->sortBy('fecha_sort')->values();

            $total    = $movimientos->count();
            $page     = (int) $this->request->get('page', 1);
            $offset   = ($page - 1) * $perPage;
            $items    = $movimientos->slice($offset, $perPage)->values();

            // quitar fecha_sort del resultado final
            $items = $items->map(fn($m) => collect($m)->except('fecha_sort')->all());

            return $this->Success([
                'movimientos'  => $items,
                'total'        => $total,
                'por_pagina'   => $perPage,
                'pagina_actual' => $page,
                'ultima_pagina' => (int) ceil($total / $perPage),
                'mes'          => (int) $month,
                'anio'         => (int) $year,
            ]);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al cargar los movimientos del mes',
                'message' => $e->getMessage(),
            ]);
        }
    }
}