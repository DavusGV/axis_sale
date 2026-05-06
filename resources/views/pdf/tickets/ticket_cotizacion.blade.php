<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            size: letter portrait;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #1f2937;
            background: #fff;
            padding: 25mm 25mm;
        }

        .contenedor {
            width: 100%;
        }

        /* ==================== ENCABEZADO ==================== */
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
        }
        .header td {
            vertical-align: top;
        }
        .header .logo-cell {
            width: 30%;
            text-align: left;
        }
        .header .logo-cell img {
            max-width: 90px;
            max-height: 60px;
        }
        .header .info-cell {
            width: 70%;
            text-align: right;
            font-size: 9px;
            line-height: 1.5;
            word-wrap: break-word;
        }
        .header .info-cell .nombre-est {
            font-size: 11px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 1.5mm;
            text-transform: uppercase;
        }
        .header .info-cell .dato {
            font-size: 8.5px;
            color: #4b5563;
        }

        /* ==================== TITULO COTIZACION ==================== */
        .titulo-doc {
            text-align: center;
            margin: 3mm 0 2mm 0;
            padding: 2mm 0;
            border-top: 2px solid #1e40af;
            border-bottom: 2px solid #1e40af;
        }
        .titulo-doc h1 {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        /* ==================== INFO COTIZACION ==================== */
        .info-cot {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
        }
        .info-cot td {
            padding: 0.8mm 0;
            font-size: 8.5px;
            vertical-align: top;
        }
        .info-cot .col-izq { width: 50%; }
        .info-cot .col-der { width: 50%; text-align: right; }
        .info-cot .label {
            font-weight: bold;
            color: #1f2937;
        }
        .info-cot .valor {
            color: #4b5563;
        }

        /* ==================== CLIENTE ==================== */
        .cliente-box {
            background: #f9fafb;
            border-left: 3px solid #1e40af;
            padding: 2mm 3mm;
            margin-bottom: 3mm;
        }
        .cliente-box .titulo-bloque {
            font-size: 7.5px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.8mm;
        }
        .cliente-box .dato-cliente {
            font-size: 9.5px;
            color: #1f2937;
            font-weight: bold;
        }
        .cliente-box .telefono-cliente {
            font-size: 8.5px;
            color: #4b5563;
        }

        /* ==================== TABLA PRODUCTOS ==================== */
        .tabla-productos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
        }
        .tabla-productos thead th {
            background: #1e40af;
            color: #fff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2mm 1.5mm;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .tabla-productos tbody td {
            padding: 2mm 1.5mm;
            font-size: 8.5px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .tabla-productos tbody tr:nth-child(even) td {
            background: #f9fafb;
        }
        .col-producto { text-align: left; width: 35%; }
        .col-cantidad { text-align: center; width: 10%; }
        .col-precio { text-align: right; width: 15%; }
        .col-iva { text-align: right; width: 12%; }
        .col-subtotal { text-align: right; width: 14%; }
        .col-total { text-align: right; width: 14%; font-weight: bold; }

        .nombre-producto {
            font-weight: bold;
            color: #1f2937;
        }
        .desc-producto {
            font-size: 7.5px;
            color: #6b7280;
            font-style: italic;
            margin-top: 0.3mm;
        }

        /* ==================== TOTALES ==================== */
        .totales-wrap {
            width: 100%;
            margin-bottom: 4mm;
        }
        .totales-tabla {
            width: 100%;
            border-collapse: collapse;
        }
        .totales-tabla td {
            padding: 1.2mm 3mm;
            font-size: 8.5px;
        }
        .totales-tabla .espacio { width: 55%; }
        .totales-tabla .label-tot {
            text-align: right;
            color: #4b5563;
            width: 25%;
        }
        .totales-tabla .valor-tot {
            text-align: right;
            font-weight: bold;
            color: #1f2937;
            width: 20%;
        }
        .totales-tabla .fila-total td {
            border-top: 2px solid #1e40af;
            padding-top: 2mm;
            font-size: 10.5px;
            font-weight: bold;
            color: #1e40af;
        }

        /* ==================== INFO NOTAS ==================== */
        .bloque-info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 2mm 3mm;
            border-radius: 2px;
            margin-bottom: 3mm;
        }
        .bloque-info .titulo-bloque {
            font-size: 7.5px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1mm;
        }
        .bloque-info .contenido-bloque {
            font-size: 8.5px;
            color: #1f2937;
            line-height: 1.5;
        }

        /* ==================== PIE ==================== */
        .pie {
            margin-top: 6mm;
            padding-top: 3mm;
            border-top: 1px solid #d1d5db;
            text-align: center;
            font-size: 7.5px;
            color: #6b7280;
            line-height: 1.6;
        }
        .pie .leyenda-principal {
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 0.8mm;
        }
    </style>
</head>
<body>
<div class="contenedor">

    {{-- ==================== TITULO ==================== --}}
    <div class="titulo-doc">
        <h1>Cotización</h1>
    </div>

    {{-- ==================== ENCABEZADO ==================== --}}
    <table class="header">
        <tr>
            <td class="logo-cell">
                @if(!empty($ticket['logo_url']))
                    <img src="{{ $ticket['logo_url'] }}" alt="Logo" />
                @endif
            </td>
            <td class="info-cell">
                <div class="nombre-est">{{ $ticket['establecimiento'] ?? 'Mi Negocio' }}</div>
                @if(!empty($ticket['establecimiento_direccion']))
                    <div class="dato">{{ $ticket['establecimiento_direccion'] }}</div>
                @endif
                @if(!empty($ticket['establecimiento_telefono']))
                    <div class="dato">Tel: {{ $ticket['establecimiento_telefono'] }}</div>
                @endif
                @if(!empty($ticket['establecimiento_email']))
                    <div class="dato">{{ $ticket['establecimiento_email'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ==================== INFO COTIZACION ==================== --}}
    <table class="info-cot">
        <tr>
            <td class="col-izq">
                <span class="label">Folio:</span>
                <span class="valor">{{ $ticket['folio'] ?? '#' . $ticket['id'] }}</span>
            </td>
            <td class="col-der">
                <span class="label">Fecha de emisión:</span>
                <span class="valor">{{ $ticket['fecha'] }}</span>
            </td>
        </tr>
        @if(!empty($ticket['expires_at']) || !empty($ticket['vendedor']))
            <tr>
                <td class="col-izq">
                    @if(!empty($ticket['vendedor']))
                        <span class="label">Atendió:</span>
                        <span class="valor">{{ $ticket['vendedor'] }}</span>
                    @endif
                </td>
                <td class="col-der">
                    @if(!empty($ticket['expires_at']))
                        <span class="label">Válida hasta:</span>
                        <span class="valor">{{ $ticket['expires_at'] }}</span>
                    @endif
                </td>
            </tr>
        @endif
        @if(!empty($ticket['num_cuenta']))
            <tr>
                <td class="col-izq">
                    <span class="label">Cuenta para depósito:</span>
                    <span class="valor">{{ $ticket['num_cuenta'] }}</span>
                </td>
                <td class="col-der"></td>
            </tr>
        @endif
    </table>

    {{-- ==================== CLIENTE ==================== --}}
    @php
        $nombreCliente = trim(($ticket['cliente']['nombre'] ?? '') . ' ' . ($ticket['cliente']['apellido'] ?? ''));
    @endphp
    @if($nombreCliente)
        <div class="cliente-box">
            <div class="titulo-bloque">Cliente</div>
            <div class="dato-cliente">{{ $nombreCliente }}</div>
            @if(!empty($ticket['cliente']['telefono']))
                <div class="telefono-cliente">Tel: {{ $ticket['cliente']['telefono'] }}</div>
            @endif
        </div>
    @endif

    {{-- ==================== TABLA PRODUCTOS ==================== --}}
    @php
        // mostramos columna de iva solo si el modo lo permite y al menos un producto tiene iva
        $mostrarIva = $ticket['modo_iva'] !== 'sin_iva'
            && collect($ticket['productos'])->sum('iva_monto') > 0;
    @endphp

    <table class="tabla-productos">
        <thead>
            <tr>
                <th class="col-producto">Producto</th>
                <th class="col-cantidad">Cant.</th>
                <th class="col-precio">Precio</th>
                @if($mostrarIva)
                    <th class="col-iva">IVA</th>
                @endif
                <th class="col-subtotal">Subtotal</th>
                <th class="col-total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ticket['productos'] as $p)
                <tr>
                    <td class="col-producto">
                        <div class="nombre-producto">{{ $p['nombre'] }}</div>
                        @if(($p['descuento_aplicado'] ?? 0) > 0)
                            <div class="desc-producto">
                                Descuento:
                                @if($p['tipo_descuento'] === 'porcentaje')
                                    {{ $p['descuento'] }}% (-${{ number_format($p['descuento_aplicado'], 2) }})
                                @else
                                    -${{ number_format($p['descuento_aplicado'], 2) }}
                                @endif
                            </div>
                        @endif
                    </td>
                    <td class="col-cantidad">{{ $p['cantidad'] }}</td>
                    <td class="col-precio">${{ number_format($p['precio_unitario'], 2) }}</td>
                    @if($mostrarIva)
                        <td class="col-iva">
                            @if(($p['iva_monto'] ?? 0) > 0)
                                ${{ number_format($p['iva_monto'], 2) }}
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    <td class="col-subtotal">${{ number_format($p['subtotal_bruto'], 2) }}</td>
                    <td class="col-total">${{ number_format($p['subtotal_neto'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ==================== TOTALES ==================== --}}
    @php
        $descuentoTotal = collect($ticket['productos'])->sum('descuento_aplicado');
    @endphp

    <div class="totales-wrap">
        <table class="totales-tabla">
            <tr>
                <td class="espacio"></td>
                <td class="label-tot">Subtotal:</td>
                <td class="valor-tot">${{ number_format($ticket['subtotal'], 2) }}</td>
            </tr>
            @if($descuentoTotal > 0)
                <tr>
                    <td class="espacio"></td>
                    <td class="label-tot">Descuento:</td>
                    <td class="valor-tot">-${{ number_format($descuentoTotal, 2) }}</td>
                </tr>
            @endif

            @if($ticket['modo_iva'] === 'iva_incluido' && $ticket['iva_total'] > 0)
                <tr>
                    <td class="espacio"></td>
                    <td class="label-tot">Base imponible:</td>
                    <td class="valor-tot">${{ number_format($ticket['total'] - $ticket['iva_total'], 2) }}</td>
                </tr>
                <tr>
                    <td class="espacio"></td>
                    <td class="label-tot">IVA (incluido):</td>
                    <td class="valor-tot">${{ number_format($ticket['iva_total'], 2) }}</td>
                </tr>
            @endif

            @if($ticket['modo_iva'] === 'iva_adicional' && $ticket['iva_total'] > 0)
                <tr>
                    <td class="espacio"></td>
                    <td class="label-tot">IVA:</td>
                    <td class="valor-tot">+${{ number_format($ticket['iva_total'], 2) }}</td>
                </tr>
            @endif

            <tr class="fila-total">
                <td class="espacio"></td>
                <td class="label-tot">TOTAL:</td>
                <td class="valor-tot">${{ number_format($ticket['total'], 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- ==================== NOTAS ==================== --}}
    @if(!empty($ticket['notas']))
        <div class="bloque-info">
            <div class="titulo-bloque">Notas</div>
            <div class="contenido-bloque">{{ $ticket['notas'] }}</div>
        </div>
    @endif

    {{-- ==================== PIE ==================== --}}
    <div class="pie">
        <div class="leyenda-principal">Esta cotización está sujeta a disponibilidad de productos.</div>
        <div>Los precios están sujetos a cambios sin previo aviso.</div>
    </div>

</div>
</body>
</html>
