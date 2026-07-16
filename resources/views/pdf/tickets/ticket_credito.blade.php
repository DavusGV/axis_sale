<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 10px;
            color: #000;
            background: #fff;
            width: 100%;
            padding: 0.5mm;
            margin: 0;
            word-spacing: -1px;
            line-height: 1.1;
        }

        .contenedor {
            width: {{ $ticket['ancho_seguro'] ?? 100 }}%;
            margin: 0 0 0 {{ $ticket['margen_izquierdo'] ?? 0 }}%;
        }

        .header {
            text-align: center;
            padding-bottom: 0.8mm;
            border-bottom: 2px solid #000;
            margin-bottom: 0.8mm;
        }
        .header img {
            max-height: 12mm;
            display: block;
            margin: 0 auto 0.5mm;
        }
        .header .nombre {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0px;
        }
        .tipo-doc {
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 0.5mm 0;
            border-bottom: 1px solid #000;
            margin-bottom: 0.5mm;
            letter-spacing: 0px;
        }

        .sep { border: none; border-top: 1px dashed #000; margin: 0.6mm 0; }

        .fila {
            width: 100%;
            border: none;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 0;
        }
        .fila td {
            padding: 0;
            font-size: 8px;
            vertical-align: top;
        }
        .fila .l {
            text-align: left;
            width: 40%;
            white-space: nowrap;
            font-weight: bold;
        }
        .fila .v {
            text-align: right;
            width: 60%;
            word-wrap: break-word;
        }

        .fila-total {
            width: 100%;
            border: none;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0.3mm 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        .fila-total td {
            padding: 0.4mm 0;
            font-size: 9px;
            font-weight: bold;
            vertical-align: middle;
        }
        .fila-total .l { text-align: left; width: 40%; }
        .fila-total .v { text-align: right; width: 60%; }

        .sec-titulo {
            font-weight: bold;
            font-size: 7px;
            text-transform: uppercase;
            padding: 0.3mm 0;
            margin-bottom: 0.2mm;
            border-bottom: 1px solid #000;
            letter-spacing: 0px;
        }

        .pie {
            text-align: center;
            font-size: 7px;
            color: #000;
            margin-top: 0.6mm;
            font-weight: bold;
        }
        .pie-sub {
            text-align: center;
            font-size: 7px;
            color: #333;
            margin-top: 0.3mm;
        }
    </style>
</head>
<body>
<div class="contenedor">

    {{-- CABECERA --}}
    <div class="header">
        @if($ticket['logo_url'])
            <img src="{{ $ticket['logo_url'] }}" alt="Logo" />
        @endif
        <div class="nombre">{{ $ticket['establecimiento'] }}</div>
    </div>

    {{-- TIPO DE DOCUMENTO --}}
    <div class="tipo-doc">COMPROBANTE DE CREDITO</div>

    {{-- DATOS DEL CREDITO --}}
    <table class="fila"><tr>
        <td class="l">FOLIO VENTA:</td>
        <td class="v">{{ $ticket['folio_venta'] }}</td>
    </tr></table>

    <table class="fila"><tr>
        <td class="l">FECHA INICIO:</td>
        <td class="v">{{ $ticket['fecha_inicio'] }}</td>
    </tr></table>

    <table class="fila"><tr>
        <td class="l">PROX. PAGO:</td>
        <td class="v">{{ $ticket['fecha_proximo_pago'] }}</td>
    </tr></table>

    {{-- DATOS DEL CLIENTE --}}
    <hr class="sep" />
    <div class="sec-titulo">CLIENTE</div>

    <table class="fila"><tr>
        <td class="l">NOMBRE:</td>
        <td class="v">{{ strtoupper($ticket['cliente']['nombre'] . ' ' . $ticket['cliente']['apellido']) }}</td>
    </tr></table>

    @if(!empty($ticket['cliente']['telefono']))
        <table class="fila"><tr>
            <td class="l">TEL:</td>
            <td class="v">{{ $ticket['cliente']['telefono'] }}</td>
        </tr></table>
    @endif

    @if(!empty($ticket['cliente']['direccion']))
        <table class="fila"><tr>
            <td class="l">DIRECCION:</td>
            <td class="v">{{ $ticket['cliente']['direccion'] }}</td>
        </tr></table>
    @endif

    {{-- RESUMEN FINANCIERO --}}
    <hr class="sep" />
    <div class="sec-titulo">RESUMEN DEL CREDITO</div>

    <table class="fila"><tr>
        <td class="l">TOTAL VENTA:</td>
        <td class="v">${{ number_format($ticket['total_venta'], 2) }}</td>
    </tr></table>

    @if($ticket['interes_aplicado'] > 0)
        <table class="fila"><tr>
            <td class="l">INTERES:</td>
            <td class="v">
                @if($ticket['interes_tipo'] === 'porcentaje')
                    {{ $ticket['interes_valor'] }}%
                @else
                    ${{ number_format($ticket['interes_valor'], 2) }}
                @endif
                (+${{ number_format($ticket['interes_aplicado'], 2) }})
            </td>
        </tr></table>
    @endif

    <table class="fila"><tr>
        <td class="l">TOTAL A PAGAR:</td>
        <td class="v">${{ number_format($ticket['total_a_pagar'], 2) }}</td>
    </tr></table>

    @if($ticket['anticipo'] > 0)
        <table class="fila"><tr>
            <td class="l">ANTICIPO:</td>
            <td class="v">${{ number_format($ticket['anticipo'], 2) }}</td>
        </tr></table>
    @endif

    {{-- SALDO PENDIENTE RESALTADO --}}
    <table class="fila-total"><tr>
        <td class="l">SALDO PEND.:</td>
        <td class="v">${{ number_format($ticket['saldo_pendiente'], 2) }}</td>
    </tr></table>

    {{-- PLAN DE PAGOS --}}
    <div class="sec-titulo">PLAN DE PAGOS</div>

    <table class="fila"><tr>
        <td class="l">NUM. PLAZOS:</td>
        <td class="v">{{ $ticket['num_plazos'] }}</td>
    </tr></table>

    <table class="fila"><tr>
        <td class="l">TIPO PLAZO:</td>
        <td class="v">
            @if($ticket['tipo_plazo'] === 'dias')
                CADA {{ $ticket['intervalo_dias'] }} DIAS
            @else
                {{ strtoupper($ticket['tipo_plazo']) }}
            @endif
        </td>
    </tr></table>

    {{-- CUOTA RESALTADA --}}
    <table class="fila-total"><tr>
        <td class="l">CUOTA:</td>
        <td class="v">${{ number_format($ticket['monto_cuota'], 2) }}</td>
    </tr></table>

    @if(!empty($ticket['observaciones']))
        <hr class="sep" />
        <div class="sec-titulo">OBSERVACIONES</div>
        <div style="font-size: 8px; padding: 0.3mm 0;">{{ $ticket['observaciones'] }}</div>
    @endif

    {{-- PIE --}}
    <div class="pie">CONSERVE ESTE COMPROBANTE</div>
    <div class="pie-sub">{{ $ticket['establecimiento'] }} &mdash; CREDITO ACTIVO</div>

</div>
</body>
</html>