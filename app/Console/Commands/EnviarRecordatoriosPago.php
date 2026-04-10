<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\PlanPago;
use App\Mail\RecordatorioPagoPlan;
use Carbon\Carbon;

class EnviarRecordatoriosPago extends Command
{
    /**
     * Nombre con el que se llama el comando desde la terminal o el scheduler.
     * Ejemplo: php artisan recordatorios:pago
     */
    protected $signature = 'recordatorios:pago';

    /**
     * Descripcion que aparece al hacer php artisan list
     */
    protected $description = 'Envia recordatorios de pago por email a clientes con cuota proxima';

    /**
     * Punto de entrada del comando.
     * Obtiene la fecha de hoy y de mañana, luego llama a los dos metodos
     * de envio por separado para mantener la logica clara.
     */
    public function handle(): void
    {
        $hoy    = Carbon::today();
        $manana = Carbon::tomorrow();

        $this->info('Iniciando envio de recordatorios de pago...');

        $this->enviarRecordatorios($hoy, 'hoy');
        $this->enviarRecordatorios($manana, 'mañana');

        $this->info('Proceso finalizado.');
    }

    /**
     * Busca los planes de pago activos cuya fecha_proximo_pago coincide
     * con la fecha recibida y envia el correo a cada cliente que tenga email.
     *
     * Solo procesa planes en estado 'activo' para no mandar correos
     * de planes ya liquidados o cancelados.
     */
    private function enviarRecordatorios(Carbon $fecha, string $tipoAviso): void
    {
        // cargamos todas las relaciones que necesitan el Mailable y los blades
        $planes = PlanPago::with([
                'cliente',
                'venta.establecimiento',
                'pagos',
            ])
            ->whereIn('estado', ['activo', 'atrasado'])
            ->whereDate('fecha_proximo_pago', $fecha)
            ->get();

        if ($planes->isEmpty()) {
            $this->line("Sin recordatorios para enviar ({$tipoAviso}).");
            return;
        }

        foreach ($planes as $plan) {
            $this->procesarPlan($plan, $tipoAviso);
        }
    }

    /**
     * Valida que el cliente tenga email y dispara el Mailable.
     * Si el envio falla, registra el error en el log sin detener
     * el proceso para que los demas correos si se envien.
     */
    private function procesarPlan(PlanPago $plan, string $tipoAviso): void
    {
        $cliente = $plan->cliente;

        // si el cliente no tiene email registrado no hay a donde enviar
        if (empty($cliente->email)) {
            $this->warn("Plan #{$plan->id} - cliente sin email, se omite.");
            return;
        }

        try {
            Mail::to($cliente->email)
                ->send(new RecordatorioPagoPlan($plan, $tipoAviso));

            $this->info("Correo enviado a {$cliente->email} (Plan #{$plan->id} - {$tipoAviso}).");

        } catch (\Exception $e) {
            // registramos el error pero continuamos con el siguiente plan
            Log::error("Error al enviar recordatorio Plan #{$plan->id}: " . $e->getMessage());
            $this->error("Fallo el envio a {$cliente->email} (Plan #{$plan->id}).");
        }
    }
}