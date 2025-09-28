<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Models\Cajas;
use App\Models\HistorialCajas;
use PhpParser\Node\Expr\FuncCall;
use App\Models\Ventas;
use App\Models\VentasDetalles;

class VentasController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {

    }

    public function store(Request $request)
    {
        try {
            $establecimiento_id = 1; // Reemplaza con el ID real del establecimiento vendra del frontend;
            DB::beginTransaction();

            $caja = Cajas::where('establecimiento_id', $establecimiento_id)
                ->where('abierta', true)
                ->first();

            if (!$caja) {
                throw ValidationException::withMessages(['caja_id' => 'La caja no existe o no está abierta.']);
            }
            //buscamos el historial de la caja abierta
            $historialCaja = HistorialCajas::where('caja_id', $caja->id)
                ->where('estado', 'abierta')
                ->first();

            if (!$historialCaja) {
                throw ValidationException::withMessages(['caja_id' => 'No se encontró un historial de caja abierto.']);
            }

            $venta = new Ventas();
            $venta->establecimiento_id = $establecimiento_id;
            $venta->historial_caja_id = $historialCaja->id;
            $venta->usuario_id = $request->usuario_id;
            $venta->total = $request->total;
            $venta->pago = $request->pago;
            $venta->cambio = $request->cambio;
            $venta->metodo_pago = $request->metodo_pago;
            $venta->save();

            //hay que guardar los detalles de la venta
            foreach ($request->detalles as $detalle) {
                $ventaDetalle = new VentasDetalles();
                $ventaDetalle->venta_id = $venta->id;
                $ventaDetalle->producto_id = $detalle['producto_id'];
                $ventaDetalle->cantidad = $detalle['cantidad'];
                $ventaDetalle->precio = $detalle['precio'];
                $ventaDetalle->subtotal =  $detalle['precio'] * $detalle['cantidad'];
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
}
