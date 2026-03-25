<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Balance General</title>
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
        .header {
            width: 100%;
            margin-bottom: 8px;
        }

        .header-table {
            width: 100%;
        }

        .logo {
            width: 70px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
        }

        .company-sub {
            text-align: center;
            font-size: 9px;
            color: #6b7280;
        }

        .header-right {
            text-align: right;
            font-size: 8px;
            color: #6b7280;
        }

        .report-title-box {
            border: 1px solid #000;
            text-align: center;
            padding: 4px;
            font-weight: bold;
            font-size: 11px;
            margin-top: 4px;
        }

        /* ── Info del periodo ── */
        .info {
            width: 100%;
            border: 1px solid #ccc;
            margin-top: 6px;
            margin-bottom: 8px;
        }

        .info td {
            padding: 4px 6px;
            font-size: 9px;
        }

        /* ── Titulos de seccion ── */
        .section-title {
            margin-top: 10px;
            font-weight: bold;
            font-size: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            text-align: center;
        }

        /* ── Tablas de datos ── */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .table th {
            background: #e5e7eb;
            border: 1px solid #ccc;
            padding: 3px 4px;
            font-size: 8px;
            text-transform: uppercase;
        }

        .table td {
            border: 1px solid #ddd;
            padding: 3px 4px;
            font-size: 8px;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        /* ── Fila de subtotal ── */
        .subtotal-row td {
            border-top: 2px solid #000;
            font-weight: bold;
            font-size: 9px;
            padding: 4px;
        }

        /* ── Conteo ── */
        .conteo {
            font-size: 7px;
            color: #9ca3af;
            text-align: right;
            margin-top: 2px;
            margin-bottom: 4px;
        }

        /* ── Resumen final ── */
        .summary-container {
            margin-top: 8px;
            width: 50%;
            margin-left: auto;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            font-size: 10px;
        }

        .summary-title {
            background: #f3f4f6;
            font-weight: bold;
        }

        .summary-final {
            font-weight: bold;
            font-size: 11px;
        }

        .summary-final-positive {
            color: #059669;
        }

        .summary-final-negative {
            color: #dc2626;
        }

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

        /* ── Sin datos ── */
        .sin-datos {
            text-align: center;
            color: #9ca3af;
            font-style: italic;
            padding: 10px;
            font-size: 9px;
        }
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
                    <div class="company-sub">Reporte de Balance General</div>
                </td>
                <td class="header-right" style="width: 100px;">
                    Fecha de emision:<br>
                    {{ now()->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>

        <div class="report-title-box">
            REPORTE DE BALANCE GENERAL
        </div>
    </div>

    <!-- INFO DEL PERIODO -->
    <table class="info">
        <tr>
            <td><strong>Periodo:</strong> {{ $fechaInicio }} al {{ $fechaFin }}</td>
            <td><strong>Ventas registradas:</strong> {{ $ventas->count() }}</td>
            <td><strong>Abonos recibidos:</strong> {{ $abonos->count() }}</td>
            <td><strong>Gastos registrados:</strong> {{ $gastos->count() }}</td>
        </tr>
    </table>

    <!-- SECCION: INGRESOS GENERADOS -->
    <div class="section-title">Ingresos Generados</div>

    @if($ventas->count() > 0 || $abonos->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Metodo de Pago</th>
                    <th>Tipo</th>
                    <th class="right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventas as $venta)
                    @php
                        $esCredito = $venta->planPago !== null;
                        $tipo = $esCredito ? 'Credito (Anticipo)' : 'Contado';
                        $monto = $esCredito ? $venta->pago : $venta->total;
                    @endphp
                    <tr>
                        <td>{{ $venta->folio ?? 'S/F' }}</td>
                        <td>{{ $venta->created_at ? $venta->created_at->format('d/m/Y H:i') : '' }}</td>
                        <td>{{ $venta->metodo_pago }}</td>
                        <td>{{ $tipo }}</td>
                        <td class="right">${{ number_format($monto, 2) }}</td>
                    </tr>
                @endforeach

                @foreach($abonos as $abono)
                    <tr>
                        <td>Abono a credito</td>
                        <td>{{ \Carbon\Carbon::parse($abono->fecha_pago)->format('d/m/Y') }}</td>
                        <td>{{ $abono->metodo_pago }}</td>
                        <td>Abono</td>
                        <td class="right">${{ number_format($abono->monto_pagado, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="subtotal-row">
                    <td colspan="4" class="right">Subtotal Ingresos Brutos</td>
                    <td class="right">${{ number_format($totalIngresos, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="conteo">
            {{ $ventas->count() }} venta(s) + {{ $abonos->count() }} abono(s) = {{ $ventas->count() + $abonos->count() }} registro(s)
        </div>
    @else
        <p class="sin-datos">No se registraron ingresos en este periodo</p>
    @endif

    <!-- SECCION: INVERSIONES REALIZADAS (GASTOS) -->
    <div class="section-title">Inversiones Realizadas (Gastos)</div>

    @if($gastos->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Fecha</th>
                    <th>Categoria</th>
                    <th>Descripcion</th>
                    <th class="right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($gastos as $gasto)
                    <tr>
                        <td>{{ $gasto->concepto }}</td>
                        <td>{{ \Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y') }}</td>
                        <td>{{ ($gasto->tipoGasto->name ?? 'Sin categoria') }}</td>
                        <td>{{ $gasto->descripcion ?? '-' }}</td>
                        <td class="right">${{ number_format($gasto->monto, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="subtotal-row">
                    <td colspan="4" class="right">Subtotal Inversiones</td>
                    <td class="right">${{ number_format($totalGastos, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="conteo">
            {{ $gastos->count() }} gasto(s) registrado(s)
        </div>
    @else
        <p class="sin-datos">No se registraron gastos en este periodo</p>
    @endif

    <!-- SECCION: RESULTADO DEL PERIODO -->
    <div class="section-title">Resultado del Periodo</div>

    <div class="summary-container">
        <table class="summary-table">
            <tr class="summary-title">
                <td>Concepto</td>
                <td class="right">Monto</td>
            </tr>
            <tr>
                <td>Ingresos Brutos</td>
                <td class="right">${{ number_format($totalIngresos, 2) }}</td>
            </tr>
            <tr>
                <td>(-) Total Inversiones</td>
                <td class="right">${{ number_format($totalGastos, 2) }}</td>
            </tr>
            <tr class="summary-final">
                <td>SALDO NETO</td>
                <td class="right {{ $saldoNeto >= 0 ? 'summary-final-positive' : 'summary-final-negative' }}">
                    {{ $saldoNeto < 0 ? '-' : '' }}${{ number_format(abs($saldoNeto), 2) }}
                </td>
            </tr>
        </table>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} | {{ $establecimiento }} | Reporte de Balance General
    </div>

</body>
</html>