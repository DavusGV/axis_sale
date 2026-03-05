<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use App\Models\TipoGasto;
use App\Models\Gastos;
use App\Models\Establecimiento;
use App\Models\MetodoPago;
use Carbon\Carbon;

class GastosController extends Controller
{
    private $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function storeType()
    {
        try {
            $data = $this->request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string'
            ]);

             // enviado por el frontend y validado previamente por middleware.
            $establecimiento_id = app('establishment_id');

           //primero validamos que el tipo de gasto no exista ya para el establecimiento
            $existingTipoGasto = TipoGasto::where('name', $data['name'])
                ->where('establecimiento_id', $establecimiento_id)
                ->first();
            if ($existingTipoGasto) {
                return $this->BadRequest('El tipo de gasto ya existe para este establecimiento');
            }

            $tipoGasto = new TipoGasto();
            $tipoGasto->name = $data['name'];
            $tipoGasto->description = $data['description'] ?? null;
            $tipoGasto->state = $data['state'] ?? 'activo';
            $tipoGasto->establecimiento_id = $establecimiento_id;
            $tipoGasto->save();

            return $this->Success($tipoGasto);
        } catch (Exception $e) {
            return $this->InternalError( 'Error al registrar el tipo de gasto: ' .$e->getMessage());
        }
    }

    public function indexType(Request $request)
    {
        try {

            $establecimiento_id = app('establishment_id');

            $query = TipoGasto::where('establecimiento_id', $establecimiento_id);

            if ($request->filled('search')) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            if ($request->filled('status')) {
                $query->where('state', $request->status);
            }
            $query->orderBy('id', 'desc');
            $tipoGasto = $query->paginate(8);

            return $this->Success($tipoGasto);

        } catch (Exception $e) {
            return $this->InternalError('Error al obtener el tipo de gasto: ' . $e->getMessage());
        }
    }

    public function destroyType($id)
    {
        try {

            $establecimiento_id = app('establishment_id');

            $tipoGasto = TipoGasto::where('id', $id)
                ->where('establecimiento_id', $establecimiento_id)
                ->firstOrFail();

            $tipoGasto->delete();

            return $this->Success('Eliminado exitosamente');

        } catch (Exception $e) {
            return $this->InternalError('Error al eliminar: ' . $e->getMessage());
        }
    }

    public function updateType(){
         try {

            $data = $this->request;
             // enviado por el frontend y validado previamente por middleware.
            $establecimiento_id = app('establishment_id');

           //primero validamos que el tipo de gasto no exista ya para el establecimiento
            $existingTipoGasto = TipoGasto::where('name', $data->name)
                ->where('id', '!=' , $data->id)
                ->where('establecimiento_id', $establecimiento_id)
                ->first();
            if ($existingTipoGasto) {
                return $this->BadRequest('El tipo de gasto ya existe para este establecimiento');
            }

            $tipoGasto = TipoGasto::where('id', $data->id)
            ->where('establecimiento_id', $establecimiento_id)
            ->firstOrFail();

            $tipoGasto->name = $data->name;
            $tipoGasto->description = $data->description ?? null;
            $tipoGasto->state = $data->state ?? 'activo';
            $tipoGasto->save();

            return $this->Success($tipoGasto);
        } catch (Exception $e) {
            return $this->InternalError( 'Error al Actualizar el tipo de gasto: ' .$e->getMessage());
        }
    }

    public function index(Request $request)
    {
        try {

            $establecimiento_id = app('establishment_id');
            $establishment = Establecimiento::find($establecimiento_id);
            $created_at = Carbon::parse($establishment->created_at)->format('Y-m-d');

            $month = $request->month;
            $year = $request->year;

            $query = Gastos::with('metodoPago')
            ->where('establecimiento_id', $establecimiento_id);

            if ($request->filled('search')) {
                $query->where('concepto', 'LIKE', '%' . $request->search . '%');
            }

            if ($request->filled('status')) {
                $query->where('state', $request->status);
            }

            // 🔎 filtro por año
            if ($request->filled('year')) {
                $query->whereYear('fecha', $year);
            }

            // 🔎 filtro por mes
            if ($request->filled('month')) {
                $query->whereMonth('fecha', $month);
            }

            $query->orderBy('id', 'desc');

            $gasto = $query->paginate(8);
            $data["gastos"] = $gasto;
            $data["created_at"] = $created_at;


            return $this->Success($data);

        } catch (Exception $e) {
            return $this->InternalError('Error al obtener el tipo de gasto: ' . $e->getMessage());
        }
    }


    public function getType()
    {
        try {

            $establecimiento_id = app('establishment_id');

            $query = TipoGasto::where('establecimiento_id', $establecimiento_id)
            ->where('state', 'activo')
            ->get();

            return $this->Success($query);

        } catch (Exception $e) {
            return $this->InternalError('Error al obtener el tipo de gasto: ' . $e->getMessage());
        }
    }


    public function getmethodpay()
    {
        try {

            $establecimiento_id = app('establishment_id');

            $query = MetodoPago::where('establecimiento_id', $establecimiento_id)
            ->where('estado', 1)
            ->get();

            return $this->Success($query);

        } catch (Exception $e) {
            return $this->InternalError('Error al obtener el tipo de gasto: ' . $e->getMessage());
        }
    }


}
