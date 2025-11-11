<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Cajas;
use App\Models\HistorialCajas;
use PhpParser\Node\Expr\FuncCall;
use App\Models\Ventas;
use App\Models\VentasDetalles;
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

        $user = auth()->user();
                //vamoas a obtener de pronto el primer establecimiento asignado al usuario
        $establecimiento = UserEstablecimiento::where('user_id', $user->id)->first();
        $establecimiento_id = $establecimiento->establecimiento_id ?? 0;

         $query = VentasDetalles::query()
            ->with('producto:id,nombre')
            ->whereHas('venta', function ($q) use ($establecimiento_id) {
                $q->where('establecimiento_id', $establecimiento_id);
            })
            ->when($desde, fn($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('created_at', '<=', $hasta));

        $detalles = $query->get();

        // Agrupar por producto
        $agrupado = $detalles->groupBy('producto_id')->map(function ($items, $producto_id) {
            $producto = optional($items->first()->producto);
            $inversion = $items->sum(fn($item) => $item->precio_compra * $item->cantidad);
            $vendido = $items->sum(fn($item) => $item->precio * $item->cantidad);
            $ganancia = $items->sum(fn($item) => ($item->precio - $item->precio_compra) * $item->cantidad);

            return [
                'producto_id' => $producto_id,
                'producto' => $producto->nombre ?? 'Desconocido',
                'inversion' => $inversion,
                'vendido' => $vendido,
                'ganancia' => $ganancia,
            ];
        })->values();

        // Totales generales
        $total_inversion = $agrupado->sum('inversion');
        $total_vendido = $agrupado->sum('vendido');
        $total_ganancia = $agrupado->sum('ganancia');

        $response = [
                'productos' => $agrupado,
                'totales' => [
                    'inversion' => $total_inversion,
                    'vendido' => $total_vendido,
                    'ganancia' => $total_ganancia,
                ],
        ];

        return $this->Success($response);
    }

}
