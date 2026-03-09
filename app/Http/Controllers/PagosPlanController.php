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

class PagosPlanController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('America/Mexico_City');
        Carbon::setLocale('es');
    }

    // listado de pagos de un plan especifico
    public function index($planId)
    {
        try {
            $establecimiento_id = app('establishment_id');

            // verificamos que el plan pertenezca al establecimiento
            $plan = PlanPago::where('id', $planId)
                ->where('establecimiento_id', $establecimiento_id)
                ->first();

            if (!$plan) {
                return $this->BadRequest('Plan de pago no encontrado.');
            }

            $pagos = PagoPlan::with(['usuario', 'historialCaja'])
                ->where('plan_pago_id', $planId)
                ->orderBy('numero_cuota', 'asc')
                ->get();

            return $this->Success(['pagos' => $pagos, 'plan' => $plan]);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener pagos.', 'details' => $e->getMessage()]);
        }
    }

    public function store(Request $request, $planId)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            $plan = PlanPago::where('id', $planId)
                ->where('establecimiento_id', $establecimiento_id)
                ->first();

            if (!$plan) {
                return $this->BadRequest('Plan de pago no encontrado.');
            }

            if ($plan->estado === 'liquidado') {
                return $this->BadRequest('Este plan ya fue liquidado.');
            }

            if ($plan->estado === 'cancelado') {
                return $this->BadRequest('Este plan esta cancelado.');
            }

            // validacion con monto maximo = saldo pendiente
            $request->validate([
                'monto_pagado' => 'required|numeric|min:0.01|max:' . $plan->saldo_pendiente,
                'notas'        => 'nullable|string',
                'usuario_id'   => 'required|integer|exists:users,id',
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

            // calculamos el numero de cuota siguiente
            $numeroCuota   = $plan->pagos()->count() + 1;
            $saldoAnterior = $plan->saldo_pendiente;
            $montoPagado   = $request->monto_pagado;

            // si es el ultimo pago y queda un saldo muy pequeno por redondeo,
            // ajustamos para que liquide exacto
            $diferencia = abs($saldoAnterior - $montoPagado);
            if ($diferencia > 0 && $diferencia <= 0.05) {
                $montoPagado = $saldoAnterior;
            }

            $saldoDespues = max($saldoAnterior - $montoPagado, 0);

            $pago = new PagoPlan();
            $pago->plan_pago_id      = $plan->id;
            $pago->historial_caja_id = $historialCaja->id;
            $pago->usuario_id        = $request->usuario_id;
            $pago->numero_cuota      = $numeroCuota;
            $pago->monto_pagado      = $montoPagado;
            $pago->saldo_anterior    = $saldoAnterior;
            $pago->saldo_despues     = $saldoDespues;
            $pago->fecha_pago        = Carbon::now()->toDateString();
            $pago->metodo_pago       = 'credito'; // siempre credito para identificarlo en ingresos
            $pago->metodo_pago_id    = null;      // no aplica FK de metodos_pago
            $pago->notas             = $request->notas;
            $pago->save();

            // actualizamos el saldo del plan
            $plan->saldo_pendiente = $saldoDespues;

            // la fecha de proximo pago solo avanza si el abono cubre
            // al menos el monto de la cuota O si el saldo llego a 0
            $totalAbonado = $plan->pagos()->sum('monto_pagado');
            $cuotasEsperadas = $numeroCuota;
            $montoEsperado   = $plan->monto_cuota * $cuotasEsperadas;

            // si lo abonado acumulado cubre lo que corresponde hasta esta cuota, avanza la fecha
            if ($totalAbonado >= $montoEsperado || $saldoDespues <= 0) {
                // dias: el intervalo es igual al num_plazos (ej: 4 plazos = cada 4 dias)
                // semanal: siempre avanza 7 dias
                // mensual: avanza al mismo dia del mes siguiente
                if ($plan->tipo_plazo === 'mensual') {
                    $plan->fecha_proximo_pago = Carbon::parse($plan->fecha_proximo_pago)->addMonth();
                } elseif ($plan->tipo_plazo === 'semanal') {
                    $plan->fecha_proximo_pago = Carbon::parse($plan->fecha_proximo_pago)->addWeek();
                } else {
                    $plan->fecha_proximo_pago = Carbon::parse($plan->fecha_proximo_pago)->addDays($plan->num_plazos);
                }
            }

            // si el saldo llego a 0 marcamos como liquidado
            if ($saldoDespues <= 0) {
                $plan->estado = 'liquidado';
            }

            $plan->save();

            // recargamos el plan con relaciones para devolver datos completos
            $plan->load(['cliente', 'venta', 'pagos']);

            DB::commit();

            return $this->Success([
                'message'            => 'Pago registrado exitosamente.',
                'pago'               => $pago,
                'plan'               => $plan,
                'saldo_pendiente'    => $saldoDespues,
                'estado_plan'        => $plan->estado,
                'fecha_proximo_pago' => $plan->fecha_proximo_pago,
            ]);

        } catch (ValidationException $ve) {
            DB::rollBack();
            throw $ve;
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error al registrar pago.', 'details' => $e->getMessage()]);
        }
    }
}