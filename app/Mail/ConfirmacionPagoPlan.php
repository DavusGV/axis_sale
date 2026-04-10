<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\PlanPago;
use App\Models\PagoPlan;
use App\Services\TicketService;
use Barryvdh\DomPDF\Facade\Pdf;

class ConfirmacionPagoPlan extends Mailable
{
    use Queueable, SerializesModels;

    public PlanPago $plan;
    public PagoPlan $pago;
    protected TicketService $ticketService;

    /**
     * Recibe el plan actualizado y el pago recien registrado.
     */
    public function __construct(PlanPago $plan, PagoPlan $pago)
    {
        $this->plan         = $plan;
        $this->pago         = $pago;
        $this->ticketService = app(TicketService::class);
    }

    /**
     * Asunto del correo con el numero de cuota pagada.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación de pago - Cuota #' . $this->pago->numero_cuota
        );
    }

    /**
     * Vista y variables que recibe el blade del correo.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.confirmacion_pago',
            with: [
                'plan'    => $this->plan,
                'pago'    => $this->pago,
                'cliente' => $this->plan->cliente,
            ]
        );
    }

    /**
     * Genera el PDF del comprobante de abono en memoria y lo adjunta.
     * No guarda ningun archivo en disco.
     */
    public function attachments(): array
    {
        $pdf = $this->ticketService->generarPdfAbono($this->plan, $this->pago);

        $nombreArchivo = 'abono-cuota-' . $this->pago->numero_cuota
            . '-' . ($this->plan->venta->folio ?? 'plan-' . $this->plan->id)
            . '.pdf';

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $nombreArchivo
            )->withMime('application/pdf'),
        ];
    }
}