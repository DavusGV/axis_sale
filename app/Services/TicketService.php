<?php
namespace App\Services;

use App\Models\Ventas;
use App\Models\Cotizacion;
use App\Models\PlanPago;
use App\Models\PagoPlan;
use App\Models\ConfiguracionEstablecimiento;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class TicketService
{
    /**
     * Genera el ticket de una venta por su id
     */
    public function generarTicketVenta(int $id): array
    {
        $venta = Ventas::with([
            'detalles.producto',
            'establecimiento',
            'planPago.cliente',
            'cliente',
            'usuario',
        ])->findOrFail($id);

        $ticket = $this->construirTicket($venta, 'venta');

        // campos exclusivos de venta
        $ticket['metodo_pago'] = $venta->metodo_pago;
        $ticket['pago']        = $venta->pago;
        $ticket['cambio']      = $venta->cambio;
        $ticket['es_credito']  = $venta->planPago !== null;

        // plan de pago si es credito
        $ticket['plan_pago'] = null;
        // cliente directo de la venta (contado con cliente registrado)
        // si es credito, el cliente ya viene dentro de plan_pago
        $ticket['cliente'] = null;
        if (!$venta->planPago && $venta->cliente) {
            $ticket['cliente'] = [
                'nombre'   => $venta->cliente->nombre,
                'apellido' => $venta->cliente->apellido_p,
                'telefono' => $venta->cliente->telefono1 ?? null,
            ];
        }
        if ($venta->planPago) {
            $plan = $venta->planPago;
            $config = $this->obtenerConfiguracion($venta->establecimiento_id);

            $ticket['plan_pago'] = [
                'cliente'            => optional($plan->cliente)->nombre . ' ' . optional($plan->cliente)->apellido_p,
                'total_a_pagar'      => $plan->total_a_pagar,
                'anticipo'           => $plan->anticipo,
                'saldo_pendiente'    => $plan->saldo_pendiente,
                'num_plazos'         => $plan->num_plazos,
                'tipo_plazo'         => $plan->tipo_plazo,
                'intervalo_dias'     => $plan->intervalo_dias,
                'monto_cuota'        => $plan->monto_cuota,
                'fecha_inicio'       => Carbon::parse($plan->fecha_inicio)->format($config['formato_fecha']),
                'fecha_proximo_pago' => Carbon::parse($plan->fecha_proximo_pago)->format($config['formato_fecha']),
                'interes_aplicado'   => $plan->interes_aplicado,
            ];
        }

        return $ticket;
    }

    /**
     * Genera el ticket de una cotizacion por su id
     */
    public function generarTicketCotizacion(int $id): array
    {
        $cotizacion = Cotizacion::with([
            'detalles.producto',
            'establecimiento',
            'cliente',
            'usuario',
        ])->findOrFail($id);

        $ticket = $this->construirTicket($cotizacion, 'cotizacion');

        $config = $this->obtenerConfiguracion($cotizacion->establecimiento_id);

        // campos exclusivos de cotizacion
        $ticket['status']      = $cotizacion->status;
        $ticket['expires_at']  = $cotizacion->expires_at
            ? Carbon::parse($cotizacion->expires_at)->format($config['formato_fecha'])
            : null;
        $ticket['cliente'] = [
            'nombre'   => optional($cotizacion->cliente)->nombre,
            'apellido' => optional($cotizacion->cliente)->apellido_p,
            'telefono' => optional($cotizacion->cliente)->telefono ?? null,
        ];
        $ticket['venta_folio'] = $cotizacion->venta_folio;

        return $ticket;
    }

    /**
     * Genera el array de datos para el ticket de credito
     * Recibe el plan con sus relaciones venta, establecimiento y cliente
     */
    public function generarTicketCredito(PlanPago $plan): array
    {
        // obtenemos la config usando el establecimiento_id de la venta
        $config = $this->obtenerConfiguracion($plan->venta->establecimiento_id);

        // logo usando el helper igual que en los otros tickets
        $logoBase64 = obtenerLogoBase64($plan->venta->establecimiento);

        return [
            'logo_url'          => $logoBase64,
            'establecimiento'   => $plan->venta->establecimiento->nombre ?? 'MI NEGOCIO',
            'formato_fecha'     => $config['formato_fecha'],
            'folio_venta'       => $plan->venta->folio ?? '#' . $plan->venta_id,
            'fecha_inicio'      => Carbon::parse($plan->fecha_inicio)->format($config['formato_fecha']),
            'fecha_proximo_pago'=> Carbon::parse($plan->fecha_proximo_pago)->format($config['formato_fecha']),
            'cliente'           => [
                'nombre'    => $plan->cliente->nombre,
                'apellido'  => $plan->cliente->apellido_p,
                'telefono'  => $plan->cliente->telefono1 ?? null,
                'direccion' => $plan->cliente->direccion ?? null,
            ],
            'total_venta'       => $plan->total_venta,
            'interes_aplicado'  => $plan->interes_aplicado,
            'interes_tipo'      => $plan->interes_tipo,
            'interes_valor'     => $plan->interes_valor,
            'total_a_pagar'     => $plan->total_a_pagar,
            'anticipo'          => $plan->anticipo,
            'saldo_pendiente'   => $plan->total_financiado,
            'num_plazos'        => $plan->num_plazos,
            'tipo_plazo'        => $plan->tipo_plazo,
            'intervalo_dias'    => $plan->intervalo_dias,
            'monto_cuota'       => $plan->monto_cuota,
            'observaciones'     => $plan->observaciones ?? null,
        ];
    }

    /**
     * Genera el array de datos para el ticket de abono
     * Recibe el plan y el pago registrado
     */
    public function generarTicketAbono(PlanPago $plan, PagoPlan $pago): array
    {
        $config = $this->obtenerConfiguracion($plan->venta->establecimiento_id);

        $logoBase64 = obtenerLogoBase64($plan->venta->establecimiento);

        return [
            'logo_url'          => $logoBase64,
            'establecimiento'   => $plan->venta->establecimiento->nombre ?? 'MI NEGOCIO',
            'formato_fecha'     => $config['formato_fecha'],
            'folio_venta'       => $plan->venta->folio ?? '#' . $plan->venta_id,
            'fecha_pago'        => Carbon::parse($pago->fecha_pago)->format($config['formato_fecha']),
            'numero_cuota'      => $pago->numero_cuota,
            'num_plazos'        => $plan->num_plazos,
            'metodo_pago'       => $pago->metodo_pago,
            'cliente'           => [
                'nombre'    => $plan->cliente->nombre,
                'apellido'  => $plan->cliente->apellido_p,
                'telefono'  => $plan->cliente->telefono1 ?? null,
            ],
            'saldo_anterior'    => $pago->saldo_anterior,
            'monto_pagado'      => $pago->monto_pagado,
            'saldo_despues'     => $pago->saldo_despues,
            'saldo_pendiente'   => $pago->saldo_despues,
            'estado'            => $plan->estado,
            'fecha_proximo_pago'=> Carbon::parse($plan->fecha_proximo_pago)->format($config['formato_fecha']),
            'monto_cuota'       => $plan->monto_cuota,
            'notas'             => $pago->notas ?? null,
        ];
    }

    /**
     * Construye el array del ticket con los datos comunes a ventas y cotizaciones
     * Usa las funciones del helper para logo y calculo de iva
     */
    private function construirTicket($entidad, string $tipo): array
    {
        $config = $this->obtenerConfiguracion($entidad->establecimiento_id);
        $formatoHora  = $config['formato_hora'];
        $formatoFecha = $config['formato_fecha'];

        // logo a base64 usando el helper
        $logoBase64 = obtenerLogoBase64($entidad->establecimiento);

        // mapeo de productos con calculo de iva usando el helper
        $detalles = $tipo === 'venta' ? $entidad->detalles : $entidad->detalles;

        $productos = $detalles->map(function ($detalle) use ($entidad) {
            $subtotalNeto  = $detalle->subtotal - ($detalle->descuento_aplicado ?? 0);
            $ivaPorcentaje = $detalle->iva_porcentaje ?? 0;

            // calculo de iva centralizado en el helper
            $ivaMonto = calcularIvaProducto($subtotalNeto, $ivaPorcentaje, $entidad->modo_iva);

            return [
                'detalle_id'         => $detalle->id,
                'producto_id'        => $detalle->producto_id,
                'es_servicio'        => optional($detalle->producto)->es_servicio ?? false,
                'stock_disponible'   => optional($detalle->producto)->stock ?? 0,
                'precio_compra'      => $detalle->precio_compra,
                'nombre'             => $detalle->nombre_producto ?? optional($detalle->producto)->nombre ?? 'Producto eliminado',
                'imagen_url'         => optional($detalle->producto)->imagen_url ?? null,
                'cantidad'           => $detalle->cantidad,
                'precio_unitario'    => $detalle->precio,
                'subtotal_bruto'     => $detalle->subtotal,
                'tipo_descuento'     => $detalle->tipo_descuento,
                'descuento'          => $detalle->descuento,
                'descuento_aplicado' => $detalle->descuento_aplicado ?? 0,
                'subtotal_neto'      => $subtotalNeto,
                'iva_porcentaje'     => $ivaPorcentaje,
                'iva_monto'          => round($ivaMonto, 2),
            ];
        });

        // nombre del vendedor que atendio
        $vendedor = null;
        if ($entidad->usuario) {
            $vendedor = trim($entidad->usuario->name ?? '');
        }

        return [
            'id'              => $entidad->id,
            'folio'           => $entidad->folio,
            'tipo'            => $tipo,
            'modo_iva'        => $entidad->modo_iva,
            'iva_total'       => $entidad->iva_total,
            'fecha'           => $entidad->created_at->format(
                $formatoFecha . ' ' . ($formatoHora === '12h' ? 'h:i A' : 'H:i')
            ),
            'subtotal'        => $entidad->subtotal,
            'total'           => $entidad->total,
            'notas'           => $entidad->notas ?? null,
            'establecimiento' => optional($entidad->establecimiento)->nombre,
            'logo_url'        => $logoBase64,
            'formato_hora'    => $formatoHora,
            'formato_fecha'   => $formatoFecha,
            'num_cuenta'      => $config['num_cuenta'],
            'vendedor'        => $vendedor,
            'productos'       => $productos,
        ];
    }

    /**
     * Obtiene la configuracion del establecimiento como array
     */
    private function obtenerConfiguracion(int $establecimientoId): array
    {
        $config = ConfiguracionEstablecimiento::where('establecimiento_id', $establecimientoId)->first();

        return [
            'formato_hora'  => $config->formato_hora ?? '12h',
            'formato_fecha' => $config->formato_fecha ?? 'd/m/Y',
            'num_cuenta'    => $config->num_cuenta ?? null,
        ];
    }

    /**
     * Genera el PDF del ticket de una venta
     */
    public function generarPdfVenta(int $id)
    {
        $ticket = $this->generarTicketVenta($id);
        return $this->crearPdfTicket($ticket);
    }

    /**
     * Genera el PDF del ticket de una cotizacion
     */
    public function generarPdfCotizacion(int $id)
    {
        $ticket = $this->generarTicketCotizacion($id);
        return $this->crearPdfTicket($ticket);
    }

    /**
     * Genera el PDF del ticket de credito
     */
    public function generarPdfCredito(PlanPago $plan)
    {
        $ticket = $this->generarTicketCredito($plan);
        return $this->crearPdfTicket($ticket, 'pdf.tickets.ticket_credito');
    }

    /**
     * Genera el PDF del ticket de abono
     */
    public function generarPdfAbono(PlanPago $plan, PagoPlan $pago)
    {
        $ticket = $this->generarTicketAbono($plan, $pago);
        return $this->crearPdfTicket($ticket, 'pdf.tickets.ticket_abono');
    }


    /**
     * Crea el PDF del ticket usando la vista blade
     * Renderiza dos veces: primero calcula la altura real del contenido
     * y luego genera el PDF final con esa altura exacta
     */
    private function crearPdfTicket(array $ticket, string $vista = 'pdf.tickets.ticket')
    {
        $anchoPt = 226.77; // 80mm en puntos

        // primera pasada: renderizamos para calcular la altura real del contenido
        $pdfPrevio = Pdf::loadView($vista, ['ticket' => $ticket]);
        $pdfPrevio->setPaper([0, 0, $anchoPt, 1000]);

        $GLOBALS['bodyHeight'] = 0;

        $pdfPrevio->setCallbacks([
            'calcAltura' => [
                'event' => 'end_frame',
                'f' => function ($frame) {
                    $node = $frame->get_node();
                    if (strtolower($node->nodeName) === 'body') {
                        $paddingBox = $frame->get_padding_box();
                        $GLOBALS['bodyHeight'] += $paddingBox['h'];
                    }
                }
            ]
        ]);

        $pdfPrevio->render();
        $alturaReal = $GLOBALS['bodyHeight'] + 30;
        unset($pdfPrevio);

        // segunda pasada: generamos el PDF final con la altura calculada
        $pdfFinal = Pdf::loadView($vista, ['ticket' => $ticket]);
        $pdfFinal->setPaper([0, 0, $anchoPt, $alturaReal]);

        return $pdfFinal;
    }

}