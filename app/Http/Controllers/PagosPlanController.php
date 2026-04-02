<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\PlanPago;
use App\Models\PagoPlan;
use App\Models\Cajas;
use App\Models\Ventas;
use App\Models\HistorialCajas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\ConfirmacionPagoPlan;
use App\Services\TicketService;

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
            // Guardamos el metodo de pago real (efectivo, transferencia, etc.)
            // para saber donde entro el dinero (caja o banco)
            $pago->metodo_pago       = $request->metodo_pago ?? 'Efectivo';
            $pago->metodo_pago_id    = $request->metodo_pago_id ?? null;
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
                // dias: usa el intervalo configurado en intervalo_dias
                // semanal: siempre avanza 7 dias
                // mensual: avanza al mismo dia del mes siguiente
                if ($plan->tipo_plazo === 'mensual') {
                    $plan->fecha_proximo_pago = Carbon::parse($plan->fecha_proximo_pago)->addMonth();
                } elseif ($plan->tipo_plazo === 'semanal') {
                    $plan->fecha_proximo_pago = Carbon::parse($plan->fecha_proximo_pago)->addWeek();
                } else {
                    // dias: usa intervalo_dias para avanzar la fecha
                    $plan->fecha_proximo_pago = Carbon::parse($plan->fecha_proximo_pago)->addDays($plan->intervalo_dias);
                }
            }

            // si el saldo llego a 0 marcamos como liquidado
            if ($saldoDespues <= 0) {
                $plan->estado = 'liquidado';

                // al liquidarse el credito la venta pasa a vendido
                Ventas::where('id', $plan->venta_id)
                    ->update(['status' => 'vendido']);

            } elseif (in_array($plan->estado, ['atrasado', 'vencido'])) {
                // si estaba atrasado o vencido y realizo un pago, regresa a activo
                $plan->estado = 'activo';
            }

            $plan->save();

            // recargamos el plan con relaciones para devolver datos completos
            $plan->load(['cliente', 'venta.establecimiento', 'pagos']);

            DB::commit();

            if (!empty($plan->cliente->email)) {
                try {
                    Mail::to($plan->cliente->email)
                        ->send(new ConfirmacionPagoPlan($plan, $pago));

                } catch (Exception $e) {
                    Log::error(
                        "Error al enviar confirmacion de pago Plan #{$plan->id}: " . $e->getMessage()
                    );
                }
            }

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

    public function ticketAbono($planId, $pagoId)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $plan = PlanPago::with(['cliente', 'venta.establecimiento'])
                ->where('id', $planId)
                ->where('establecimiento_id', $establecimiento_id)
                ->first();

            if (!$plan) {
                return $this->BadRequest('Plan de pago no encontrado.');
            }

            $pago = PagoPlan::where('id', $pagoId)
                ->where('plan_pago_id', $planId)
                ->first();

            if (!$pago) {
                return $this->BadRequest('Pago no encontrado.');
            }

            $ticketService = app(\App\Services\TicketService::class);
            $pdf = $ticketService->generarPdfAbono($plan, $pago);

            $nombreArchivo = 'abono-cuota' . $pago->numero_cuota . '-' . ($plan->venta->folio ?? $plan->venta_id) . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al generar ticket de abono.', 'details' => $e->getMessage()]);
        }
    }
}