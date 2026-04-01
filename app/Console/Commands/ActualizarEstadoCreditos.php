<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\PlanPago;
use App\Models\Ventas;
use Carbon\Carbon;

class ActualizarEstadoCreditos extends Command
{
    /**
     * Nombre del comando para el scheduler y la terminal.
     */
    protected $signature = 'creditos:actualizar-estados';

    /**
     * Descripcion visible en php artisan list
     */
    protected $description = 'Actualiza el estado de los planes de pago segun sus fechas y pagos realizados';

    /**
     * Punto de entrada del comando.
     * Solo procesa planes activos y atrasados, los liquidados
     * y cancelados no deben tocarse.
     */
    public function handle(): void
    {
        $this->info('Iniciando actualizacion de estados de creditos...');

        $hoy = Carbon::today();

        // obtenemos solo los planes que pueden cambiar de estado
        $planes = PlanPago::with(['pagos'])
            ->whereIn('estado', ['activo', 'atrasado'])
            ->where('fecha_proximo_pago', '<', $hoy)
            ->get();

        if ($planes->isEmpty()) {
            $this->info('No hay planes con fecha vencida.');
            return;
        }

        foreach ($planes as $plan) {
            $this->evaluarPlan($plan, $hoy);
        }

        $this->info('Actualizacion finalizada.');
    }

    /**
     * Evalua un plan individual y decide si cambia a atrasado o vencido.
     *
     * Logica:
     * - Si hay plazos vencidos sin pagar pero aun quedan plazos futuros -> atrasado
     * - Si ya vencieron TODOS los plazos y el plan no esta liquidado    -> vencido
     */
    private function evaluarPlan(PlanPago $plan, Carbon $hoy): void
    {
        try {
            $plazosVencidos  = $this->calcularPlazosVencidos($plan, $hoy);
            $pagosRealizados = $plan->pagos->count();

            if ($pagosRealizados >= $plazosVencidos) {
                return;
            }

            $plazosSinPagar = $plazosVencidos - $pagosRealizados;
            $todosVencidos  = $plazosVencidos >= $plan->num_plazos;

            $nuevoEstado = $todosVencidos ? 'vencido' : 'atrasado';

            if ($plan->estado !== $nuevoEstado) {
                $plan->estado = $nuevoEstado;
                $plan->save();

                // si el plan vencio completamente, marcamos la venta como no_pagado
                if ($nuevoEstado === 'vencido') {
                    Ventas::where('id', $plan->venta_id)
                        ->whereNotIn('status', ['cancelada', 'vendido'])
                        ->update(['status' => 'no_pagado']);
                }

                $this->info("Plan #{$plan->id} -> {$nuevoEstado} ({$plazosSinPagar} plazo(s) sin pagar).");
                Log::info("Plan #{$plan->id} cambiado a {$nuevoEstado}. Plazos sin pagar: {$plazosSinPagar}");
            }

        } catch (\Exception $e) {
            Log::error("Error al evaluar Plan #{$plan->id}: " . $e->getMessage());
            $this->error("Error en Plan #{$plan->id}: " . $e->getMessage());
        }
    }

    /**
     * Calcula cuantos plazos debieron haberse pagado desde la fecha de inicio
     * hasta hoy, segun el tipo de plazo del plan.
     *
     * No puede superar el num_plazos total del plan.
     */
    private function calcularPlazosVencidos(PlanPago $plan, Carbon $hoy): int
    {
        $fechaInicio = Carbon::parse($plan->fecha_inicio);
        $contador    = 0;
        $fechaActual = $fechaInicio->copy();

        // avanzamos plazo por plazo desde la fecha de inicio
        // hasta que la fecha supere hoy o lleguemos al total de plazos
        while ($contador < $plan->num_plazos) {
            // calculamos la fecha del siguiente plazo segun el tipo
            if ($plan->tipo_plazo === 'mensual') {
                $fechaActual->addMonth();
            } elseif ($plan->tipo_plazo === 'semanal') {
                $fechaActual->addWeek();
            } else {
                // dias: usa el intervalo configurado en el plan
                $fechaActual->addDays($plan->intervalo_dias);
            }

            // si la fecha del plazo ya paso, cuenta como vencido
            if ($fechaActual->lte($hoy)) {
                $contador++;
            } else {
                // los siguientes plazos aun no vencen
                break;
            }
        }

        return $contador;
    }
}