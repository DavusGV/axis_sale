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
        /* banner verde para confirmacion de pago */
        .banner-confirmacion {
            background-color: #27ae60;
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
        /* bloque verde destacado del monto pagado */
        .monto-destacado {
            background-color: #f0fff4;
            border-left: 4px solid #27ae60;
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
            color: #27ae60;
            margin-top: 4px;
        }
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
        /* saldo pendiente resaltado en naranja si queda algo */
        .saldo-pendiente {
            background-color: #fff8f0;
            border-left: 4px solid #e67e22;
            padding: 14px 16px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .saldo-pendiente .label {
            font-size: 12px;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .saldo-pendiente .monto {
            font-size: 22px;
            font-weight: bold;
            color: #e67e22;
            margin-top: 4px;
        }
        /* bloque verde si ya liquido */
        .liquidado {
            background-color: #f0fff4;
            border: 1px solid #27ae60;
            border-radius: 6px;
            padding: 14px 16px;
            margin: 20px 0;
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            color: #27ae60;
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
        <p>Comprobante de abono</p>
    </div>

    {{-- BANNER --}}
    <div class="banner-confirmacion">Pago registrado correctamente</div>

    {{-- CUERPO --}}
    <div class="cuerpo">

        <p class="saludo">
            Hola, <strong>{{ $cliente->nombre }} {{ $cliente->apellido_p }}</strong>:
        </p>

        <p>Tu abono de la <strong>cuota #{{ $pago->numero_cuota }}</strong> ha sido registrado exitosamente. Aquí el resumen:</p>

        {{-- MONTO PAGADO DESTACADO --}}
        <div class="monto-destacado">
            <div class="label">Monto pagado</div>
            <div class="monto">${{ number_format($pago->monto_pagado, 2) }}</div>
        </div>

        {{-- DATOS DEL PAGO --}}
        <table class="tabla-datos">
            <tr>
                <td class="etiqueta">Fecha de pago:</td>
                <td class="valor">{{ \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Método de pago:</td>
                <td class="valor">{{ $pago->metodo_pago }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Cuota número:</td>
                <td class="valor">#{{ $pago->numero_cuota }} de {{ $plan->num_plazos }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Folio de venta:</td>
                <td class="valor">{{ $plan->venta->folio ?? '#' . $plan->venta_id }}</td>
            </tr>
            @if($pago->notas)
            <tr>
                <td class="etiqueta">Notas:</td>
                <td class="valor">{{ $pago->notas }}</td>
            </tr>
            @endif
        </table>

        {{-- SALDO RESTANTE O LIQUIDADO --}}
        @if($plan->estado === 'liquidado')
            <div class="liquidado">
                Tu crédito ha sido liquidado en su totalidad. ¡Gracias!
            </div>
        @else
            <div class="saldo-pendiente">
                <div class="label">Saldo pendiente restante</div>
                <div class="monto">${{ number_format($plan->saldo_pendiente, 2) }}</div>
            </div>

            <table class="tabla-datos">
                <tr>
                    <td class="etiqueta">Próximo pago:</td>
                    <td class="valor">
                        {{ \Carbon\Carbon::parse($plan->fecha_proximo_pago)->format('d/m/Y') }}
                    </td>
                </tr>
                <tr>
                    <td class="etiqueta">Monto próxima cuota:</td>
                    <td class="valor">
                        ${{ number_format(min($plan->monto_cuota, $plan->saldo_pendiente), 2) }}
                    </td>
                </tr>
            </table>
        @endif

        <p class="adjunto-nota">
            Se adjunta a este correo el comprobante de tu abono en formato PDF para tu referencia.
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