<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use App\Models\Ventas;
use App\Models\Establecimiento;
use Carbon\Carbon;

class IngresosControlador extends Controller
{
    private $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
   //method for get sales for moth, year, establishment
    public function getIncome()
    {
        try {

            $idestablishment = $this->request->get('idestablishment');
            $month           = $this->request->get('month');
            $year            = $this->request->get('year');

            $establishment   = Establecimiento::find($idestablishment);
            $created_at = Carbon::parse($establishment->created_at)->format('Y-m-d');

            $income = Ventas::where('establecimiento_id', $idestablishment)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->get();


            $ventasPorDia = $income->groupBy(function ($venta) {
                return $venta->created_at->day; // 1, 2, 3...
            });

            $daysInMonth = Carbon::create($year, $month)->daysInMonth;
            $resultado = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {

                $fecha = Carbon::create($year, $month, $day);

                $ventasDia = $ventasPorDia->get($day, collect());


                $metodosPago = $ventasDia
                    ->groupBy('metodo_pago') // campo en tu tabla
                    ->map(function ($items) {
                        return $items->sum('total');
                });

                $resultado[] = [
                    'name'   => $fecha->translatedFormat('l'),
                    'date'       => $day,
                    'paymentMethod'  => $metodosPago,
                    'total'       => $metodosPago->sum(),
                ];
            }

            $paymentMethod         = ["efectivo", "tarjeta", "transferencia"];

            $data["income"]        = $resultado;
            $data["paymentMethod"] = $paymentMethod;
            $data["created_at"] = $created_at;

            return $this->Success($data);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al cargar la información de ingresos', 'message' => $e->getMessage()]);
        }
    }
}
