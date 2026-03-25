<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Historial de Ventas</title>
    <style>
        @page {
            margin: 15mm 12mm 15mm 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111827;
        }

        /* ── Header ── */
        .header { width: 100%; margin-bottom: 8px; }
        .header-table { width: 100%; }
        .logo { width: 70px; }
        .company-name { font-size: 14px; font-weight: bold; text-align: center; }
        .company-sub { text-align: center; font-size: 9px; color: #6b7280; }
        .header-right { text-align: right; font-size: 8px; color: #6b7280; }

        .report-title-box {
            border: 1px solid #000;
            text-align: center;
            padding: 4px;
            font-weight: bold;
            font-size: 11px;
            margin-top: 4px;
        }

        /* ── Info ── */
        .info { width: 100%; border: 1px solid #ccc; margin-top: 6px; margin-bottom: 8px; }
        .info td { padding: 4px 6px; font-size: 9px; }

        /* ── Section ── */
        .section-title {
            margin-top: 10px;
            font-weight: bold;
            font-size: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            text-align: center;
        }

        /* ── Venta header ── */
        .venta-header {
            margin-top: 8px;
            background: #f3f4f6;
            border: 1px solid #ccc;
            padding: 3px 6px;
            font-size: 9px;
        }

        .venta-header strong { font-size: 9px; }

        .badge-vendida {
            color: #059669;
            font-weight: bold;
        }

        .badge-cancelada {
            color: #dc2626;
            font-weight: bold;
        }

        /* ── Tables ── */
        .table { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .table th {
            background: #e5e7eb;
            border: 1px solid #ccc;
            padding: 2px 4px;
            font-size: 7.5px;
            text-transform: uppercase;
        }
        .table td {
            border: 1px solid #ddd;
            padding: 2px 4px;
            font-size: 8px;
        }
        .right { text-align: right; }
        .center { text-align: center; }

        /* ── Subtotal venta ── */
        .venta-subtotal {
            text-align: right;
            font-weight: bold;
            font-size: 9px;
            padding: 2px 4px;
            border-top: 1px solid #000;
        }

        /* ── Resumen ── */
        .summary-container { margin-top: 10px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td {
            border: 1px solid #ccc;
            padding: 3px 6px;
            font-size: 9px;
        }
        .summary-title { background: #f3f4f6; font-weight: bold; }
        .summary-final {
            font-weight: bold;
            font-size: 10px;
            border-top: 2px solid #000;
        }
        .ganancia-positiva { color: #059669; }
        .ganancia-negativa { color: #dc2626; }

        /* ── Footer ── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 3px;
        }

        /* ── Page break ── */
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 80px;">
                    @if(!empty($logo))
                        <img src="data:image/png;base64,{{ $logo }}" class="logo">
                    @endif
                </td>
                <td style="text-align: center;">
                    <div class="company-name">{{ $establecimiento }}</div>
                    <div class="company-sub">Reporte de Historial de Ventas</div>
                </td>
                <td class="header-right" style="width: 100px;">
                    Fecha de emision:<br>
                    {{ now()->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>

        <div class="report-title-box">
            REPORTE DE HISTORIAL DE VENTAS
        </div>
    </div>

    <!-- INFO DEL PERIODO -->
    <table class="info">
        <tr>
            <td><strong>Periodo:</strong> {{ $fechaInicio }} al {{ $fechaFin }}</td>
            <td><strong>Total ventas:</strong> {{ $ventas->count() }}</td>
            <td><strong>Activas:</strong> {{ $resumen['ventas_activas'] }}</td>
            <td><strong>Canceladas:</strong> {{ $resumen['ventas_canceladas'] }}</td>
        </tr>
    </table>

    <!-- DETALLE DE VENTAS -->
    <div class="section-title">Detalle de Ventas</div>

    @foreach($ventas as $venta)
        @php
            $esCredito = $venta->planPago !== null;
            $statusTexto = ($venta->status ?? 'vendido') === 'cancelada' ? 'CANCELADA' : 'VENDIDA';
            $statusClase = ($venta->status ?? 'vendido') === 'cancelada' ? 'badge-cancelada' : 'badge-vendida';
            $tipoTexto = $esCredito ? 'Credito' : 'Contado';
            $clienteTexto = '';
            if ($esCredito && $venta->planPago && $venta->planPago->cliente) {
                $clienteTexto = $venta->planPago->cliente->nombre . ' ' . ($venta->planPago->cliente->apellido_p ?? '');
            }

            $subtotalVenta = 0;
            $costoVenta = 0;
            $descuentoVenta = 0;
            $cantProductos = 0;
        @endphp

        <div class="venta-header">
            <strong>{{ $venta->folio ?? 'S/F' }}</strong> |
            {{ $venta->created_at ? $venta->created_at->format('d/m/Y H:i') : '' }} |
            {{ $venta->metodo_pago }} |
            {{ $tipoTexto }}
            @if($clienteTexto) | {{ $clienteTexto }} @endif
            | <span class="{{ $statusClase }}">{{ $statusTexto }}</span>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="center">Cant</th>
                    <th class="right">P. Venta</th>
                    <th class="right">P. Compra</th>
                    <th class="right">Descuento</th>
                    <th class="right">Subtotal</th>
                    <th class="right">Costo</th>
                    <th class="right">Ganancia</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                    @php
                        $precioVenta = $detalle->precio;
                        $precioCompra = $detalle->precio_compra ?? 0;
                        $cantidad = $detalle->cantidad;
                        $descuento = $detalle->descuento_aplicado ?? 0;
                        $subtotal = ($precioVenta * $cantidad) - $descuento;
                        $costoTotal = $precioCompra * $cantidad;
                        $ganancia = $subtotal - $costoTotal;

                        $subtotalVenta += $subtotal;
                        $costoVenta += $costoTotal;
                        $descuentoVenta += $descuento;
                        $cantProductos += $cantidad;
                    @endphp
                    <tr>
                        <td>{{ $detalle->producto->nombre ?? 'Producto eliminado' }}</td>
                        <td class="center">{{ $cantidad }}</td>
                        <td class="right">${{ number_format($precioVenta, 2) }}</td>
                        <td class="right">${{ number_format($precioCompra, 2) }}</td>
                        <td class="right">${{ number_format($descuento, 2) }}</td>
                        <td class="right">${{ number_format($subtotal, 2) }}</td>
                        <td class="right">${{ number_format($costoTotal, 2) }}</td>
                        <td class="right {{ $ganancia >= 0 ? 'ganancia-positiva' : 'ganancia-negativa' }}">
                            ${{ number_format($ganancia, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="venta-subtotal">
            {{ $cantProductos }} producto(s) |
            Desc: ${{ number_format($descuentoVenta, 2) }} |
            IVA: ${{ number_format($venta->iva_total ?? 0, 2) }} |
            Total: ${{ number_format($venta->total, 2) }} |
            Ganancia: <span class="{{ ($subtotalVenta - $costoVenta) >= 0 ? 'ganancia-positiva' : 'ganancia-negativa' }}">
                ${{ number_format($subtotalVenta - $costoVenta, 2) }}
            </span>
        </div>
    @endforeach

    <!-- RESUMEN GENERAL -->
    <div class="section-title" style="margin-top: 14px;">Resumen General del Periodo</div>

    <div class="summary-container">
        <table class="summary-table">
            <tr class="summary-title">
                <td>Concepto</td>
                <td class="right">Valor</td>
            </tr>
            <tr>
                <td>Total ventas realizadas</td>
                <td class="right">{{ $resumen['total_ventas'] }} ventas</td>
            </tr>
            <tr>
                <td>Ventas activas</td>
                <td class="right">{{ $resumen['ventas_activas'] }} ventas</td>
            </tr>
            <tr>
                <td>Ventas canceladas</td>
                <td class="right">{{ $resumen['ventas_canceladas'] }} ventas</td>
            </tr>
            <tr>
                <td>Total productos vendidos</td>
                <td class="right">{{ $resumen['total_productos'] }} unidades</td>
            </tr>
            <tr>
                <td>Total descuentos aplicados</td>
                <td class="right">${{ number_format($resumen['total_descuentos'], 2) }}</td>
            </tr>
            <tr>
                <td>IVA total cobrado</td>
                <td class="right">${{ number_format($resumen['total_iva'], 2) }}</td>
            </tr>
            <tr>
                <td>Total vendido (ventas activas)</td>
                <td class="right">${{ number_format($resumen['total_vendido'], 2) }}</td>
            </tr>
            <tr>
                <td>Total cancelado</td>
                <td class="right">${{ number_format($resumen['total_cancelado'], 2) }}</td>
            </tr>
            <tr>
                <td>Costo de compra total</td>
                <td class="right">${{ number_format($resumen['total_costo_compra'], 2) }}</td>
            </tr>
            <tr class="summary-final">
                <td>GANANCIA NETA</td>
                <td class="right {{ $resumen['ganancia_neta'] >= 0 ? 'ganancia-positiva' : 'ganancia-negativa' }}">
                    ${{ number_format($resumen['ganancia_neta'], 2) }}
                </td>
            </tr>
        </table>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} | {{ $establecimiento }} | Reporte de Historial de Ventas
    </div>

</body>
</html>