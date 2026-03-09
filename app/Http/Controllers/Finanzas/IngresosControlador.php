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

            // ventas del mes — cargamos la relacion planPago para detectar credito
            $income = Ventas::where('establecimiento_id', $idestablishment)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
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
                    $q->where('establecimiento_id', $idestablishment);
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
}