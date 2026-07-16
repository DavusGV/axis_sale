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

        /* CABECERA */
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

        /* SEPARADORES */
        .sep { border: none; border-top: 1px dashed #000; margin: 0.6mm 0; }
        .sep-doble {
            border: none;
            border-top: 2px solid #000;
            margin: 0.6mm 0;
        }

        /* FILAS DE DATOS (tabla) */
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
            width: 32%;
            white-space: nowrap;
            font-weight: bold;
        }
        .fila .v {
            text-align: right;
            width: 68%;
            word-wrap: break-word;
            overflow: hidden;
        }

        /* FILA TOTAL (resaltada) */
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
        .fila-total .l { text-align: left; width: 32%; }
        .fila-total .v { text-align: right; width: 68%; }

        /* SECCION TITULO */
        .sec-titulo {
            font-weight: bold;
            font-size: 7px;
            text-transform: uppercase;
            padding: 0.3mm 0;
            margin-bottom: 0.2mm;
            border-bottom: 1px solid #000;
            letter-spacing: 0px;
        }

        /* PRODUCTOS */
        .prod { margin-bottom: 0; }
        .prod-t {
            width: 100%;
            border: none;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .prod-t td {
            padding: 0;
            vertical-align: top;
            font-size: 7px;
        }
        .prod-nombre {
            font-size: 7px;
            text-transform: uppercase;
        }
        .prod-desc { font-size: 7%px; font-style: italic; }
        .tachado { text-decoration: line-through; color: #666; font-size: 7px; }
        .tachado { text-decoration: line-through; color: #666; font-size: 7px; }
        .prod-precio { font-size: 7px; }

        /* BLOQUE (num cuenta) */
        .bloque { margin-bottom: 0.2mm; font-size: 6.5px; }
        .bloque .etiqueta { font-size: 6.5px; font-weight: bold; }
        .bloque .dato { font-size: 7px; font-weight: bold; letter-spacing: 0px; }

        /* PIE */
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

        /* HELPERS */
        .uppercase { text-transform: uppercase; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
<div class="contenedor">

    {{-- ==================== CABECERA ==================== --}}
    <div class="header">
        @if($ticket['logo_url'])
            <img src="{{ $ticket['logo_url'] }}" alt="Logo" />
        @endif
        <div class="nombre">{{ $ticket['establecimiento'] ?? 'MI NEGOCIO' }}</div>
    </div>

    {{-- ==================== TIPO DE DOCUMENTO ==================== --}}
    <div class="tipo-doc">
        {{ $ticket['tipo'] === 'cotizacion' ? 'TICKET DE COTIZACION' : 'TICKET DE VENTA' }}
    </div>

    {{-- ==================== DATOS GENERALES ==================== --}}
    <table class="fila"><tr>
        <td class="l">FOLIO:</td>
        <td class="v">{{ $ticket['folio'] ?? '#' . $ticket['id'] }}</td>
    </tr></table>

    <table class="fila"><tr>
        <td class="l">FECHA:</td>
        <td class="v">{{ $ticket['fecha'] }}</td>
    </tr></table>

    @if(!empty($ticket['vendedor']))
        <table class="fila"><tr>
            <td class="l">ATENDIO:</td>
            <td class="v uppercase">{{ $ticket['vendedor'] }}</td>
        </tr></table>
    @endif

    @if($ticket['tipo'] === 'venta' && !empty($ticket['metodo_pago']))
        <table class="fila"><tr>
            <td class="l">METODO DE PAGO:</td>
            <td class="v uppercase">{{ $ticket['metodo_pago'] }}</td>
        </tr></table>
    @endif

    {{-- Numero de cuenta --}}
    @if(!empty($ticket['num_cuenta']))
        <table class="fila"><tr>
            <td class="l">NUM. CUENTA:</td>
            <td class="v">{{ $ticket['num_cuenta'] }}</td>
        </tr></table>
    @endif

    {{-- ==================== CLIENTE ==================== --}}
    @if(!empty($ticket['cliente']))
        <hr class="sep" />
        @php
            $nombreCliente = trim(($ticket['cliente']['nombre'] ?? '') . ' ' . ($ticket['cliente']['apellido'] ?? ''));
        @endphp
        @if($nombreCliente)
            <table class="fila"><tr>
                <td class="l">CLIENTE:</td>
                <td class="v uppercase">{{ $nombreCliente }}</td>
            </tr></table>
        @endif
        @if(!empty($ticket['cliente']['telefono']))
            <table class="fila"><tr>
                <td class="l">TEL:</td>
                <td class="v">{{ $ticket['cliente']['telefono'] }}</td>
            </tr></table>
        @endif
    @endif

    @if($ticket['tipo'] === 'cotizacion' && !empty($ticket['status']))
        <table class="fila"><tr>
            <td class="l">STATUS:</td>
            <td class="v uppercase bold">{{ $ticket['status'] }}</td>
        </tr></table>
    @endif

    @if($ticket['tipo'] === 'cotizacion' && !empty($ticket['expires_at']))
        <table class="fila"><tr>
            <td class="l">VIGENCIA:</td>
            <td class="v">{{ $ticket['expires_at'] }}</td>
        </tr></table>
    @endif

    {{-- Folio de venta asociado a la cotizacion --}}
    @if($ticket['tipo'] === 'cotizacion' && !empty($ticket['venta_folio']))
        <table class="fila"><tr>
            <td class="l">VTA FOLIO:</td>
            <td class="v bold">{{ $ticket['venta_folio'] }}</td>
        </tr></table>
    @endif

    {{-- ==================== PRODUCTOS ==================== --}}
    <hr class="sep" />
    <div class="sec-titulo">PRODUCTOS</div>

    @foreach($ticket['productos'] as $p)
        <div class="prod">
            <table class="prod-t">
                <tr>
                    <td style="width: 65%;">
                        <div class="prod-nombre">{{ strtoupper($p['nombre']) }} (x{{ $p['cantidad'] }})</div>
                        @if(($p['descuento_aplicado'] ?? 0) > 0)
                            <div class="prod-desc">
                                DESC:
                                @if($p['tipo_descuento'] === 'porcentaje')
                                    {{ $p['descuento'] }}%
                                @else
                                    ${{ number_format($p['descuento'], 2) }}
                                @endif
                                (-${{ number_format($p['descuento_aplicado'], 2) }})
                            </div>
                        @endif
                    </td>
                    <td style="width: 35%; text-align: right;">
                        @if(($p['descuento_aplicado'] ?? 0) > 0)
                            <div class="tachado">${{ number_format($p['subtotal_bruto'], 2) }}</div>
                        @endif
                        <div class="prod-precio">${{ number_format($p['subtotal_neto'], 2) }}</div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    {{-- ==================== TOTALES ==================== --}}
    <hr class="sep" />

    <table class="fila"><tr>
        <td class="l">SUBTOTAL:</td>
        <td class="v">${{ number_format($ticket['subtotal'], 2) }}</td>
    </tr></table>

    @php $descuentoTotal = collect($ticket['productos'])->sum('descuento_aplicado'); @endphp
    @if($descuentoTotal > 0)
        <table class="fila"><tr>
            <td class="l">DESC:</td>
            <td class="v">-${{ number_format($descuentoTotal, 2) }}</td>
        </tr></table>
    @endif

    @if($ticket['modo_iva'] === 'iva_incluido' && $ticket['iva_total'] > 0)
        <table class="fila"><tr>
            <td class="l">BASE:</td>
            <td class="v">${{ number_format($ticket['total'] - $ticket['iva_total'], 2) }}</td>
        </tr></table>
        <table class="fila"><tr>
            <td class="l">IVA (INCL):</td>
            <td class="v">${{ number_format($ticket['iva_total'], 2) }}</td>
        </tr></table>
    @endif

    @if($ticket['modo_iva'] === 'iva_adicional' && $ticket['iva_total'] > 0)
        <table class="fila"><tr>
            <td class="l">IVA:</td>
            <td class="v">+${{ number_format($ticket['iva_total'], 2) }}</td>
        </tr></table>
    @endif

    {{-- TOTAL resaltado con borde doble --}}
    <table class="fila-total"><tr>
        <td class="l">TOTAL:</td>
        <td class="v">${{ number_format($ticket['total'], 2) }}</td>
    </tr></table>

    {{-- ==================== PAGO CONTADO ==================== --}}
    @if($ticket['tipo'] === 'venta' && !($ticket['es_credito'] ?? false))
        <table class="fila"><tr>
            <td class="l">PAGO:</td>
            <td class="v">${{ number_format($ticket['pago'], 2) }}</td>
        </tr></table>
        <table class="fila"><tr>
            <td class="l">CAMBIO:</td>
            <td class="v">${{ number_format($ticket['cambio'], 2) }}</td>
        </tr></table>
    @endif

    {{-- ==================== PLAN DE CREDITO ==================== --}}
    @if($ticket['tipo'] === 'venta' && ($ticket['es_credito'] ?? false) && $ticket['plan_pago'])
        @php $plan = $ticket['plan_pago']; @endphp
        <div class="sec-titulo">PLAN DE CREDITO</div>

        <table class="fila"><tr>
            <td class="l">CLIENTE:</td>
            <td class="v uppercase">{{ $plan['cliente'] }}</td>
        </tr></table>
        <table class="fila"><tr>
            <td class="l">ANTICIPO:</td>
            <td class="v">${{ number_format($plan['anticipo'], 2) }}</td>
        </tr></table>

        @if(($plan['interes_aplicado'] ?? 0) > 0)
            <table class="fila"><tr>
                <td class="l">INTERES:</td>
                <td class="v">+${{ number_format($plan['interes_aplicado'], 2) }}</td>
            </tr></table>
        @endif

        <table class="fila"><tr>
            <td class="l">SALDO:</td>
            <td class="v bold">${{ number_format($plan['saldo_pendiente'], 2) }}</td>
        </tr></table>
        <table class="fila"><tr>
            <td class="l">PLAZOS:</td>
            <td class="v">
                @if($plan['tipo_plazo'] === 'dias')
                    {{ $plan['num_plazos'] }} (C/{{ $plan['intervalo_dias'] }}d)
                @else
                    {{ $plan['num_plazos'] }} {{ strtoupper($plan['tipo_plazo']) }}
                @endif
            </td>
        </tr></table>
        <table class="fila"><tr>
            <td class="l">CUOTA:</td>
            <td class="v">${{ number_format($plan['monto_cuota'], 2) }}</td>
        </tr></table>
        <table class="fila"><tr>
            <td class="l">PROX PAGO:</td>
            <td class="v">{{ $plan['fecha_proximo_pago'] }}</td>
        </tr></table>
    @endif

    {{-- ==================== NOTAS ==================== --}}
    @if(!empty($ticket['notas']))
        <hr class="sep" />
        <div class="sec-titulo">NOTAS</div>
        <div style="font-size: 9px; color: #000; padding: 0.3mm 0;">{{ $ticket['notas'] }}</div>
    @endif

    {{-- ==================== PIE ==================== --}}

    @if($ticket['tipo'] === 'cotizacion')
        <div class="pie">COTIZACION SUJETA A DISPONIBILIDAD</div>
        <div class="pie-sub">LOS PRECIOS ESTAN SUJETOS A CAMBIOS SIN PREVIO AVISO</div>
    @else
        <div class="pie">GRACIAS POR SU COMPRA</div>
        @if($ticket['es_credito'] ?? false)
            <div class="pie-sub">LOS PRECIOS ESTAN SUJETOS A CAMBIOS SIN PREVIO AVISO</div>
        @endif
    @endif

</div>
</body>
</html>