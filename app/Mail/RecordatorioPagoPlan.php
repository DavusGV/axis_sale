<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\PlanPago;
use App\Services\TicketService;
use Carbon\Carbon;

class RecordatorioPagoPlan extends Mailable
{
    use Queueable, SerializesModels;

    public PlanPago $plan;
    public string $tipoAviso;
    public float $montoCuota;
    public int $plazosAtrasados;
    public float $montoAcumulado;
    protected TicketService $ticketService;

    /**
     * Recibe el plan y el tipo de aviso
     * Calcula el monto real de la cuota y los plazos acumulados sin pagar
     */
    public function __construct(PlanPago $plan, string $tipoAviso)
    {
        $this->plan      = $plan;
        $this->tipoAviso = $tipoAviso;

        // pagos realizados hasta ahora
        $pagosRealizados = $plan->pagos->count();

        // plazos que debieron pagarse hasta hoy segun la fecha de inicio
        $hoy             = Carbon::today();
        $plazosVencidos  = $this->calcularPlazosVencidos($plan, $hoy);

        // plazos que no se han pagado (minimo 0)
        $this->plazosAtrasados = max(0, $plazosVencidos - $pagosRealizados);

        // el monto acumulado incluye los plazos atrasados mas el actual
        // pero no puede superar el saldo pendiente real
        $montoCalculado      = $plan->monto_cuota * ($this->plazosAtrasados + 1);
        $this->montoAcumulado = min($montoCalculado, $plan->saldo_pendiente);

        // monto de la cuota actual considerando si el saldo es menor
        $this->montoCuota = min($plan->monto_cuota, $plan->saldo_pendiente);

        $this->ticketService = app(TicketService::class);
    }

    /**
     * Calcula cuantos plazos debieron pagarse desde la fecha de inicio hasta hoy
     */
    private function calcularPlazosVencidos(PlanPago $plan, Carbon $hoy): int
    {
        $fechaActual = Carbon::parse($plan->fecha_inicio)->copy();
        $contador    = 0;

        while ($contador < $plan->num_plazos) {
            if ($plan->tipo_plazo === 'mensual') {
                $fechaActual->addMonth();
            } elseif ($plan->tipo_plazo === 'semanal') {
                $fechaActual->addWeek();
            } else {
                $fechaActual->addDays($plan->intervalo_dias);
            }

            if ($fechaActual->lte($hoy)) {
                $contador++;
            } else {
                break;
            }
        }

        return $contador;
    }

    /**
     * Define el asunto del correo segun si es aviso del dia anterior o del dia de pago.
     */
    public function envelope(): Envelope
    {
        $asunto = $this->tipoAviso === 'hoy'
            ? 'Recordatorio: Hoy es tu fecha de pago'
            : 'Recordatorio: Tu pago es mañana';

        return new Envelope(subject: $asunto);
    }

    /**
     * Define la vista Blade que se usara como cuerpo del correo
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.recordatorio_pago',
            with: [
                'plan'            => $this->plan,
                'tipoAviso'       => $this->tipoAviso,
                'cliente'         => $this->plan->cliente,
                'montoCuota'      => $this->montoCuota,
                'plazosAtrasados' => $this->plazosAtrasados,  // plazos acumulados
                'montoAcumulado'  => $this->montoAcumulado,   // total a pagar
            ]
        );
    }

    /**
     * Genera el PDF del comprobante de credito usando el TicketService centralizado.
     * No guarda ningun archivo en disco.
     */
    public function attachments(): array
    {
        // el plan necesita venta y establecimiento cargados para el service
        $this->plan->loadMissing(['venta.establecimiento', 'cliente']);

        $pdf = $this->ticketService->generarPdfCredito($this->plan);

        $nombreArchivo = 'credito-' . ($this->plan->venta->folio ?? 'plan-' . $this->plan->id) . '.pdf';

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $nombreArchivo
            )->withMime('application/pdf'),
        ];
    }
}