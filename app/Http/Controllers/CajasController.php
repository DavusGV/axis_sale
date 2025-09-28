<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Models\Cajas;
use App\Models\HistorialCajas;
use PhpParser\Node\Expr\FuncCall;

class CajasController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $cajas = Cajas::all();
        return $this->Success($cajas);
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

            $caja = Cajas::find($request->caja_id);
            if ($caja->abierta) {
                throw ValidationException::withMessages(['caja_id' => 'La caja ya está abierta.']);
            }

            //validar que no haya otra caja abierta
            $otraCajaAbierta = Cajas::where('abierta', true)->first();
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
