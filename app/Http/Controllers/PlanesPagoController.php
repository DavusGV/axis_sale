<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Models\PlanPago;
use App\Models\PagoPlan;
use App\Models\Cajas;
use App\Models\HistorialCajas;
use Carbon\Carbon;

class PlanesPagoController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('America/Mexico_City');
        Carbon::setLocale('es');
    }

    public function index(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $query = PlanPago::with(['cliente', 'venta'])
                ->where('establecimiento_id', $establecimiento_id);

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            // busqueda por nombre o telefono del cliente
            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('cliente', function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido_p', 'like', "%{$search}%")
                    ->orWhere('telefono1', 'like', "%{$search}%");
                });
            }

            // filtro por rango de fechas de inicio del plan
            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha_inicio', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha_inicio', '<=', $request->fecha_hasta);
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 10);
            $paginator = $query->paginate($perPage);

            return response()->json([
                'data'         => $paginator->items(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ], 200);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener planes de pago.', 'details' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            $request->validate([
                'cliente_id'    => 'required|integer|exists:clientes,id',
                'venta_id'      => 'required|integer|exists:ventas,id',
                'total_venta'   => 'required|numeric|min:0.01',
                'interes_tipo'  => 'nullable|in:porcentaje,monto',
                'interes_valor' => 'nullable|numeric|min:0',
                'anticipo'      => 'nullable|numeric|min:0',
                'intervalo_dias'=> 'nullable|integer|min:1|required_if:tipo_plazo,dias',
                'num_plazos'    => 'required|integer|min:1',
                'tipo_plazo'    => 'required|in:semanal,mensual,dias',
                'fecha_inicio'  => 'required|date',
                'observaciones' => 'nullable|string',
                'usuario_id'    => 'required|integer|exists:users,id',
            ]);

            $caja = Cajas::where('establecimiento_id', $establecimiento_id)
                ->where('abierta', true)
                ->first();

            if (!$caja) {
                throw ValidationException::withMessages(['caja_id' => 'La caja no esta abierta.']);
            }

            $historialCaja = HistorialCajas::where('caja_id', $caja->id)
                ->where('estado', 'abierta')
                ->first();

            if (!$historialCaja) {
                throw ValidationException::withMessages(['caja_id' => 'No se encontro historial de caja abierto.']);
            }

            // calculamos el interes
            $totalVenta      = $request->total_venta;
            $interesValor    = $request->interes_valor ?? 0;
            $interesTipo     = $request->interes_tipo;
            $interesAplicado = 0;

            if ($interesTipo === 'porcentaje') {
                $interesAplicado = $totalVenta * ($interesValor / 100);
            } elseif ($interesTipo === 'monto') {
                $interesAplicado = $interesValor;
            }

            $totalAPagar = $totalVenta + $interesAplicado;
            $anticipo    = $request->anticipo ?? 0;

            // validar que el anticipo no supere el total a pagar
            if ($anticipo >= $totalAPagar) {
                return $this->BadRequest('El anticipo no puede ser mayor o igual al total a pagar. Si cubre todo, no es credito.');
            }

            // lo que realmente se financia a plazos
            $totalFinanciado = $totalAPagar - $anticipo;

            // cuota redondeada, el sistema ajusta centavos en el ultimo pago
            $montoCuota = floor($totalFinanciado / $request->num_plazos);
            // si el total es menor al numero de plazos, la cuota minima es 1
            if ($montoCuota < 1 && $totalFinanciado > 0) {
                $montoCuota = 1;
            }
            // lo que pagaria el ultimo plazo (puede ser menor o mayor que la cuota regular)
            $montoUltimaCuota = $totalFinanciado - ($montoCuota * ($request->num_plazos - 1));

            $fechaInicio = Carbon::parse($request->fecha_inicio);

            // calculo de la primera fecha de proximo pago segun tipo de plazo
            // dias: el num_plazos define el intervalo en dias (ej: 4 plazos = cada 4 dias)
            // semanal: siempre avanza 7 dias por cuota
            // mensual: avanza al mismo dia del mes siguiente
            if ($request->tipo_plazo === 'mensual') {
                $fechaProximoPago = $fechaInicio->copy()->addMonth();
            } elseif ($request->tipo_plazo === 'semanal') {
                $fechaProximoPago = $fechaInicio->copy()->addWeek();
            } else {
                // dias: el intervalo lo define intervalo_dias, num_plazos es la cantidad de cuotas
                $fechaProximoPago = $fechaInicio->copy()->addDays($request->intervalo_dias);
            }

            $plan = new PlanPago();
            $plan->establecimiento_id = $establecimiento_id;
            $plan->cliente_id         = $request->cliente_id;
            $plan->venta_id           = $request->venta_id;
            $plan->historial_caja_id  = $historialCaja->id;
            $plan->usuario_id         = $request->usuario_id;
            $plan->total_venta        = $totalVenta;
            $plan->interes_tipo       = $interesTipo;
            $plan->interes_valor      = $interesValor;
            $plan->interes_aplicado   = $interesAplicado;
            $plan->total_a_pagar      = $totalAPagar;
            $plan->anticipo           = $anticipo;
            $plan->total_financiado   = $totalFinanciado;
            $plan->num_plazos         = $request->num_plazos;
            $plan->tipo_plazo         = $request->tipo_plazo;
            $plan->intervalo_dias     = $request->tipo_plazo === 'dias' ? $request->intervalo_dias : null;
            $plan->monto_cuota        = $montoCuota;
            $plan->fecha_inicio       = $fechaInicio;
            $plan->fecha_proximo_pago = $fechaProximoPago;
            // saldo pendiente arranca desde el total financiado
            $plan->saldo_pendiente    = $totalFinanciado;
            $plan->estado             = 'activo';
            $plan->observaciones      = $request->observaciones;
            $plan->save();

            DB::commit();

            return $this->Success([
                'message'             => 'Plan de pago creado exitosamente.',
                'plan'                => $plan->load(['cliente', 'venta']),
                'monto_cuota'         => $montoCuota,
                'monto_ultima_cuota'  => $montoUltimaCuota,
                'total_real'          => ($montoCuota * ($request->num_plazos - 1)) + $montoUltimaCuota,
            ]);

        } catch (ValidationException $ve) {
            DB::rollBack();
            throw $ve;
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error al crear plan de pago.', 'details' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $plan = PlanPago::with(['cliente', 'venta', 'pagos'])
                ->where('id', $id)
                ->where('establecimiento_id', $establecimiento_id)
                ->first();

            if (!$plan) {
                return $this->BadRequest('Plan de pago no encontrado.');
            }

            $montoUltimaCuota = $plan->total_financiado - ($plan->monto_cuota * ($plan->num_plazos - 1));

            return $this->Success([
                'plan'               => $plan,
                'monto_ultima_cuota' => $montoUltimaCuota,
            ]);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener plan de pago.', 'details' => $e->getMessage()]);
        }
    }
}