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

    public function updateType()
    {
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

            // incluimos las relaciones para mostrar nombres
            $query = Gastos::with(['metodoPago', 'tipoGasto', 'user'])
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
            return $this->InternalError('Error al obtener gastos: ' . $e->getMessage());
        }
    }

    public function store()
    {
        try {
            $data = $this->request->validate([
                'tipo_gasto_id'  => 'required|integer|exists:tipos_gastos,id',
                'metodo_pago_id' => 'required|integer|exists:metodos_pago,id',
                'concepto'       => 'required|string|max:255',
                'descripcion'    => 'nullable|string',
                'monto'          => 'required|numeric|min:0.01',
                'fecha'          => 'required|date',
                'state'          => 'nullable|boolean',
            ]);

            $establecimiento_id = app('establishment_id');

            $gasto = new Gastos();
            $gasto->establecimiento_id = $establecimiento_id;
            $gasto->tipo_gasto_id      = $data['tipo_gasto_id'];
            $gasto->metodo_pago_id     = $data['metodo_pago_id'];
            $gasto->concepto           = $data['concepto'];
            $gasto->descripcion        = $data['descripcion'] ?? null;
            $gasto->monto              = $data['monto'];
            $gasto->fecha              = $data['fecha'];
            $gasto->state              = $data['state'] ?? 1;
            $gasto->user_id            = auth()->id() ?? $this->request->user_id;
            $gasto->save();

            // cargamos las relaciones para devolver el objeto completo
            $gasto->load(['tipoGasto', 'metodoPago']);

            return $this->Success($gasto);

        } catch (Exception $e) {
            return $this->InternalError('Error al registrar el gasto: ' . $e->getMessage());
        }
    }

    public function update()
    {
        try {
            $data = $this->request->validate([
                'id'             => 'required|integer|exists:gastos,id',
                'tipo_gasto_id'  => 'required|integer|exists:tipos_gastos,id',
                'metodo_pago_id' => 'required|integer|exists:metodos_pago,id',
                'concepto'       => 'required|string|max:255',
                'descripcion'    => 'nullable|string',
                'monto'          => 'required|numeric|min:0.01',
                'fecha'          => 'required|date',
                'state'          => 'nullable|boolean',
            ]);

            $establecimiento_id = app('establishment_id');

            $gasto = Gastos::where('id', $data['id'])
                ->where('establecimiento_id', $establecimiento_id)
                ->firstOrFail();

            $gasto->tipo_gasto_id  = $data['tipo_gasto_id'];
            $gasto->metodo_pago_id = $data['metodo_pago_id'];
            $gasto->concepto       = $data['concepto'];
            $gasto->descripcion    = $data['descripcion'] ?? null;
            $gasto->monto          = $data['monto'];
            $gasto->fecha          = $data['fecha'];
            $gasto->state          = $data['state'] ?? 1;
            $gasto->save();

            $gasto->load(['tipoGasto', 'metodoPago']);

            return $this->Success($gasto);

        } catch (Exception $e) {
            return $this->InternalError('Error al actualizar el gasto: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $gasto = Gastos::where('id', $id)
                ->where('establecimiento_id', $establecimiento_id)
                ->firstOrFail();

            $gasto->delete();

            return $this->Success('Gasto eliminado exitosamente');

        } catch (Exception $e) {
            return $this->InternalError('Error al eliminar el gasto: ' . $e->getMessage());
        }
    }

    // devuelve el total de gastos del mes y por tipo de gasto
    public function resumen(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $month = $request->month ?? now()->month;
            $year  = $request->year ?? now()->year;

            // total general del mes
            $totalMes = Gastos::where('establecimiento_id', $establecimiento_id)
                ->whereYear('fecha', $year)
                ->whereMonth('fecha', $month)
                ->where('state', 1)
                ->sum('monto');

            // desglose por tipo de gasto
            $porTipo = Gastos::where('gastos.establecimiento_id', $establecimiento_id)
                ->whereYear('gastos.fecha', $year)
                ->whereMonth('gastos.fecha', $month)
                ->where('gastos.state', 1)
                ->join('tipos_gastos', 'gastos.tipo_gasto_id', '=', 'tipos_gastos.id')
                ->selectRaw('tipos_gastos.name as tipo, SUM(gastos.monto) as total')
                ->groupBy('tipos_gastos.id', 'tipos_gastos.name')
                ->orderByDesc('total')
                ->get();

            // cantidad de gastos del mes
            $cantidadGastos = Gastos::where('establecimiento_id', $establecimiento_id)
                ->whereYear('fecha', $year)
                ->whereMonth('fecha', $month)
                ->where('state', 1)
                ->count();

            return $this->Success([
                'total_mes'        => round($totalMes, 2),
                'cantidad_gastos'  => $cantidadGastos,
                'por_tipo'         => $porTipo,
                'mes'              => $month,
                'anio'             => $year,
            ]);

        } catch (Exception $e) {
            return $this->InternalError('Error al obtener resumen de gastos: ' . $e->getMessage());
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
            return $this->InternalError('Error al obtener metodos de pago: ' . $e->getMessage());
        }
    }


}
