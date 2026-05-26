<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Ventas;
use App\Models\PlanPago;
use Carbon\Carbon;

/**
 * Correccion de datos puntual:
 *
 * Las ventas VTA-GR26018 y VTA-GR26019 se registraron como credito
 * pero el plan de pago no se creo por un bug en la conversion desde
 * cotizacion (el frontend no reenviaba el objeto credito al backend).
 *
 * Esta migracion genera el plan de pago faltante para esas dos ventas
 * usando como anticipo lo que el cliente ya pago al momento de la venta
 * y establece 2 plazos mensuales sin interes a partir de la fecha de venta.
 */
return new class extends Migration {

    public function up(): void
    {
        DB::transaction(function () {

            // ventas afectadas con su anticipo real (lo que ya pago el cliente)
            $ventasAfectadas = [
                ['folio' => 'VTA-GR26018', 'anticipo' => 5000.00],
                ['folio' => 'VTA-GR26019', 'anticipo' => 40000.00],
            ];

            foreach ($ventasAfectadas as $info) {

                $venta = Ventas::where('folio', $info['folio'])->first();

                if (!$venta) {
                    continue;
                }

                // no duplicamos plan si por alguna razon ya existe
                $planExistente = PlanPago::where('venta_id', $venta->id)->first();
                if ($planExistente) {
                    continue;
                }

                if (!$venta->cliente_id) {
                    continue;
                }

                $totalVenta      = (float) $venta->total;
                $anticipo        = (float) $info['anticipo'];
                $totalAPagar     = $totalVenta;
                $totalFinanciado = $totalAPagar - $anticipo;
                $numPlazos       = 2;

                // misma logica de redondeo que PlanesPagoController@store
                $montoCuota = floor($totalFinanciado / $numPlazos);
                if ($montoCuota < 1 && $totalFinanciado > 0) {
                    $montoCuota = 1;
                }

                $fechaInicio      = Carbon::parse($venta->created_at)->startOfDay();
                $fechaProximoPago = $fechaInicio->copy()->addMonth();

                $plan = new PlanPago();
                $plan->establecimiento_id = $venta->establecimiento_id;
                $plan->cliente_id         = $venta->cliente_id;
                $plan->venta_id           = $venta->id;
                $plan->historial_caja_id  = $venta->historial_caja_id;
                $plan->usuario_id         = $venta->usuario_id;
                $plan->total_venta        = $totalVenta;
                $plan->interes_tipo       = null;
                $plan->interes_valor      = 0;
                $plan->interes_aplicado   = 0;
                $plan->total_a_pagar      = $totalAPagar;
                $plan->anticipo           = $anticipo;
                $plan->total_financiado   = $totalFinanciado;
                $plan->num_plazos         = $numPlazos;
                $plan->tipo_plazo         = 'mensual';
                $plan->intervalo_dias     = null;
                $plan->monto_cuota        = $montoCuota;
                $plan->fecha_inicio       = $fechaInicio;
                $plan->fecha_proximo_pago = $fechaProximoPago;
                $plan->saldo_pendiente    = $totalFinanciado;
                $plan->estado             = 'activo';
                $plan->observaciones      = 'Anticipo equivalente al pago original de la venta.';
                $plan->save();

                // la venta pasa a pendiente porque tiene saldo por cobrar
                $venta->status = 'pendiente';
                $venta->save();
            }

        });
    }

    public function down(): void
    {
        //
    }
};