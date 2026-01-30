<?php
namespace App\Http\Controllers;

use App\Http\Middleware\SetEstablishment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Models\Cajas;
use App\Models\HistorialCajas;
use App\Models\UserEstablecimiento;
use App\Models\Ventas;
use PhpParser\Node\Expr\FuncCall;

class CajasController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        // El establecimiento activo viene desde el fornt y se obtiene desde middleware 
        $establecimiento_id = app('establishment_id');
        $cajas = Cajas::where('establecimiento_id', $establecimiento_id)->get();

        return $this->Success($cajas);
    }

    public function showHistoryBox($boxId)
{
    $history = HistorialCajas::select([
            'id',
            'caja_id',
            'estado',
            'saldo_inicial',
            'saldo_final',
            'created_at',
            'updated_at'
        ])
        ->where('caja_id', $boxId)
        ->orderByDesc('created_at')
        ->paginate(10);

    return $this->Success($history);
}

    public function showHistorySale($historyId)
{
    $sale = Ventas::where('historial_caja_id', $historyId)
        ->with([
            'saleDetails:id,venta_id,producto_id,cantidad,precio,subtotal',
            'saleDetails.producto:id,nombre,precio_venta'
        ])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    return $this->Success($sale);
}


    public function store(Request $request)
    {
    }

    public function open(Request $request)
    {
        $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'saldo_inicial' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();
            // El establecimiento activo se obtiene desde el header (X-Establishment-ID),
            // enviado por el frontend y validado previamente por middleware.
            $establecimiento_id = app('establishment_id');

            $caja = Cajas::find($request->caja_id);
            if ($caja->abierta) {
                throw ValidationException::withMessages(['caja_id' => 'La caja ya está abierta.']);
            }

            //validar que no haya otra caja abierta del mismo establecimiento
            //vamos a obtener el id del usuario logeado
            $otraCajaAbierta = Cajas::where('abierta', true)
            ->where('establecimiento_id', $establecimiento_id)
            ->first();
            if ($otraCajaAbierta) {
                throw ValidationException::withMessages(['caja_id' => 'Ya hay otra caja abierta: ' . $otraCajaAbierta->nombre]);
            }

            $caja->abierta = true;
            $caja->save();

            //ahora creamos el historial de apertura
            $historial = new HistorialCajas();
            $historial->caja_id = $caja->id;
            $historial->usuario_id = auth()->user()->id;
            $historial->estado = 'abierta';
            $historial->saldo_inicial = $request->saldo_inicial;
            $historial->descripcion = $request->descripcion ?? 'Apertura de caja';
            $historial->fecha_apertura = now();
            $historial->save();

            DB::commit();
            return $this->Success($caja, 'Caja abierta exitosamente.');
        } catch (ValidationException $ve) {
            DB::rollBack();
            throw $ve;
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError('Error al abrir la caja: ' . $e->getMessage());
        }
    }

    public Function close(Request $request)
    {
        $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'saldo_final' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $caja = Cajas::find($request->caja_id);
            if (!$caja->abierta) {
                throw ValidationException::withMessages(['caja_id' => 'La caja ya está cerrada.']);
            }

            $caja->abierta = false;
            $caja->save();

            //actualizamos el historial de apertura
            $historial = HistorialCajas::where('caja_id', $caja->id)
                ->where('estado', 'abierta')
                ->orderBy('fecha_apertura', 'desc')
                ->first();

            if (!$historial) {
                throw ValidationException::withMessages(['caja_id' => 'No se encontró un historial de apertura para esta caja.']);
            }

            $historial->estado = 'cerrada';
            $historial->saldo_final = $request->saldo_final;
            $historial->fecha_cierre = now();
            $historial->save();

            DB::commit();
            return $this->Success($caja, 'Caja cerrada exitosamente.');
        } catch (ValidationException $ve) {
            DB::rollBack();
            throw $ve;
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError('Error al cerrar la caja: ' . $e->getMessage());
        }
    }
}
