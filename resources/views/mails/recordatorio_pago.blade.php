<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .contenedor {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .cabecera {
            background-color: #1a1a2e;
            color: #ffffff;
            text-align: center;
            padding: 24px 20px;
        }
        .cabecera h1 {
            margin: 0;
            font-size: 20px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .cabecera p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #aaaacc;
        }
        /* banner de aviso: cambia color segun si es hoy o manana */
        .banner-hoy {
            background-color: #c0392b;
            color: #fff;
            text-align: center;
            padding: 12px;
            font-size: 15px;
            font-weight: bold;
        }
        .banner-manana {
            background-color: #e67e22;
            color: #fff;
            text-align: center;
            padding: 12px;
            font-size: 15px;
            font-weight: bold;
        }
        .cuerpo {
            padding: 28px 32px;
        }
        .saludo {
            font-size: 16px;
            margin-bottom: 16px;
        }
        /* tabla de datos del plan */
        .tabla-datos {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        .tabla-datos tr {
            border-bottom: 1px solid #eeeeee;
        }
        .tabla-datos td {
            padding: 10px 8px;
            vertical-align: top;
        }
        .tabla-datos .etiqueta {
            font-weight: bold;
            color: #555;
            width: 45%;
        }
        .tabla-datos .valor {
            color: #222;
        }
        /* resaltado del monto a pagar */
        .monto-destacado {
            background-color: #f0f7ff;
            border-left: 4px solid #1a1a2e;
            padding: 14px 16px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .monto-destacado .label {
            font-size: 12px;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .monto-destacado .monto {
            font-size: 28px;
            font-weight: bold;
            color: #1a1a2e;
            margin-top: 4px;
        }
        .lugar-pago {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 14px 16px;
            margin: 20px 0;
            font-size: 14px;
        }
        .lugar-pago strong {
            display: block;
            margin-bottom: 4px;
            color: #444;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .adjunto-nota {
            font-size: 13px;
            color: #666;
            margin-top: 20px;
            font-style: italic;
        }
        .pie {
            background-color: #f4f4f4;
            text-align: center;
            padding: 16px;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
<div class="contenedor">

    {{-- CABECERA --}}
    <div class="cabecera">
        <h1>{{ $plan->venta->establecimiento->nombre ?? 'Axis Sale' }}</h1>
        <p>Recordatorio de pago de crédito</p>
    </div>

    {{-- BANNER DE AVISO segun tipo --}}
    @if($tipoAviso === 'hoy')
        <div class="banner-hoy">Hoy es tu fecha de pago</div>
    @else
        <div class="banner-manana">Tu próximo pago es mañana</div>
    @endif

    {{-- CUERPO --}}
    <div class="cuerpo">

        <p class="saludo">
            Hola, <strong>{{ $cliente->nombre }} {{ $cliente->apellido_p }}</strong>:
        </p>

        @if($tipoAviso === 'hoy')
            <p>Te recordamos que <strong>hoy</strong> corresponde realizar tu pago de crédito. A continuación los detalles:</p>
        @else
            <p>Te recordamos que <strong>mañana</strong> corresponde realizar tu pago de crédito. A continuación los detalles:</p>
        @endif

        {{-- MONTO DESTACADO --}}
        {{-- si tiene plazos atrasados mostramos el acumulado, si no solo la cuota --}}
        @if($plazosAtrasados > 0)
            <div class="monto-destacado" style="border-left-color: #c0392b; background-color: #fff5f5;">
                <div class="label">Total acumulado a pagar</div>
                <div class="monto" style="color: #c0392b;">${{ number_format($montoAcumulado, 2) }}</div>
                <div style="font-size: 13px; color: #555; margin-top: 8px;">
                    Tienes <strong>{{ $plazosAtrasados }} plazo(s) sin pagar</strong> más el pago de hoy.<br>
                    Cuota regular: ${{ number_format($plan->monto_cuota, 2) }} x {{ $plazosAtrasados + 1 }} pagos pendientes.
                </div>
            </div>
        @else
            <div class="monto-destacado">
                <div class="label">Monto a pagar esta cuota</div>
                <div class="monto">${{ number_format($montoCuota, 2) }}</div>
                @if($montoCuota < $plan->monto_cuota)
                    <div style="font-size: 12px; color: #555; margin-top: 6px;">
                        Este es tu saldo restante. Con este pago liquidarás tu crédito.
                    </div>
                @endif
            </div>
        @endif

        {{-- DATOS DEL PLAN --}}
        <table class="tabla-datos">
            <tr>
                <td class="etiqueta">Fecha de pago:</td>
                <td class="valor">{{ \Carbon\Carbon::parse($plan->fecha_proximo_pago)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Saldo pendiente total:</td>
                <td class="valor">${{ number_format($plan->saldo_pendiente, 2) }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Total del crédito:</td>
                <td class="valor">${{ number_format($plan->total_a_pagar, 2) }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Tipo de plazo:</td>
                <td class="valor">{{ ucfirst($plan->tipo_plazo) }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Número de plazos:</td>
                <td class="valor">{{ $plan->num_plazos }}</td>
            </tr>
            @if($plan->observaciones)
            <tr>
                <td class="etiqueta">Observaciones:</td>
                <td class="valor">{{ $plan->observaciones }}</td>
            </tr>
            @endif
        </table>

        {{-- LUGAR DE PAGO --}}
        <div class="lugar-pago">
            <strong>Dónde realizar tu pago</strong>
            {{ $plan->venta->establecimiento->nombre ?? 'Nuestro establecimiento' }}
            @if(!empty($plan->venta->establecimiento->direccion))
                <br>{{ $plan->venta->establecimiento->direccion }}
            @endif
        </div>

        <p class="adjunto-nota">
            Se adjunta a este correo el comprobante de tu crédito en formato PDF para tu referencia.
        </p>

    </div>

    {{-- PIE --}}
    <div class="pie">
        Este es un mensaje automático, por favor no respondas a este correo.<br>
        {{ $plan->venta->establecimiento->nombre ?? 'Axis Sale' }} &mdash; Sistema de créditos
    </div>

</div>
</body>
</html>