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

        /* ── Section titles ── */
        .section-title {
            margin-top: 10px;
            font-weight: bold;
            font-size: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            text-align: center;
        }

        .subsection-title {
            margin-top: 8px;
            font-weight: bold;
            font-size: 9px;
            padding: 3px 6px;
            background: #f3f4f6;
            border-left: 3px solid #000;
        }

        .subsection-efectivo { border-left-color: #059669; }
        .subsection-transferencia { border-left-color: #2563eb; }
        .subsection-tarjeta { border-left-color: #7c3aed; }
        .subsection-credito { border-left-color: #f59e0b; }
        .subsection-otro { border-left-color: #6b7280; }

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

        /* ── Subtotals ── */
        .subtotal-row td {
            border-top: 2px solid #000;
            font-weight: bold;
            font-size: 9px;
            padding: 3px 4px;
        }

        .subtotal-metodo td {
            border-top: 1px solid #999;
            font-weight: bold;
            font-size: 8px;
            padding: 3px 4px;
            background: #fafafa;
        }

        /* ── Conteo ── */
        .conteo {
            font-size: 7px;
            color: #9ca3af;
            text-align: right;
            margin-top: 1px;
            margin-bottom: 4px;
        }

        /* ── Summary ── */
        .summary-container { margin-top: 8px; width: 55%; margin-left: auto; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td {
            border: 1px solid #ccc;
            padding: 3px 6px;
            font-size: 9px;
        }
        .summary-title { background: #f3f4f6; font-weight: bold; }
        .summary-final { font-weight: bold; font-size: 10px; border-top: 2px solid #000; }
        .summary-final-positive { color: #059669; }
        .summary-final-negative { color: #dc2626; }

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

        .sin-datos {
            text-align: center;
            color: #9ca3af;
            font-style: italic;
            padding: 8px;
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
            <td><strong>Ventas contado:</strong> {{ $ventasPorMetodo->flatten()->count() }}</td>
            <td><strong>Ventas credito:</strong> {{ $ventasCredito->count() }}</td>
            <td><strong>Abonos:</strong> {{ $abonos->count() }}</td>
            <td><strong>Gastos:</strong> {{ $gastos->count() }}</td>
        </tr>
    </table>

    <!-- ══════════════════════════════════════════════ -->
    <!--           INGRESOS POR METODO DE PAGO         -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="section-title">Ingresos Generados</div>

    {{-- Ventas de contado agrupadas por metodo de pago --}}
    @php
        $totalGeneralContado = 0;
        // Mapa de clases CSS por metodo de pago
        $clasesMetodo = [
            'efectivo' => 'subsection-efectivo',
            'transferencia' => 'subsection-transferencia',
            'tarjeta' => 'subsection-tarjeta',
        ];
    @endphp

    @forelse($ventasPorMetodo as $metodo => $ventasDelMetodo)
        @php
            $claseMetodo = $clasesMetodo[strtolower(trim($metodo))] ?? 'subsection-otro';
            $subtotalMetodo = $ventasDelMetodo->sum('total');
            $totalGeneralContado += $subtotalMetodo;
        @endphp

        <div class="subsection-title {{ $claseMetodo }}">
            {{ $metodo }} (Contado)
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Metodo</th>
                    <th class="right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventasDelMetodo as $venta)
                    <tr>
                        <td>{{ $venta->folio ?? 'S/F' }}</td>
                        <td>{{ $venta->created_at ? $venta->created_at->format('d/m/Y H:i') : '' }}</td>
                        <td>{{ $venta->metodo_pago }}</td>
                        <td class="right">${{ number_format($venta->total, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="subtotal-metodo">
                    <td colspan="3" class="right">Subtotal {{ $metodo }}</td>
                    <td class="right">${{ number_format($subtotalMetodo, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="conteo">{{ $ventasDelMetodo->count() }} venta(s)</div>
    @empty
        <p class="sin-datos">No se registraron ventas de contado en este periodo</p>
    @endforelse

    {{-- Seccion de creditos: anticipos + abonos --}}
    @if($ventasCredito->count() > 0 || $abonos->count() > 0)

        <div class="subsection-title subsection-credito">
            Creditos (Anticipos y Abonos)
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Folio Venta</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Metodo</th>
                    <th class="right">Monto</th>
                </tr>
            </thead>
            <tbody>
                {{-- Anticipos de ventas a credito --}}
                @foreach($ventasCredito as $venta)
                    @php
                        $clienteNombre = '';
                        if ($venta->planPago && $venta->planPago->cliente) {
                            $clienteNombre = $venta->planPago->cliente->nombre . ' ' . ($venta->planPago->cliente->apellido_p ?? '');
                        }
                    @endphp
                    <tr>
                        <td>{{ $venta->folio ?? 'S/F' }}</td>
                        <td>{{ $venta->created_at ? $venta->created_at->format('d/m/Y H:i') : '' }}</td>
                        <td>{{ $clienteNombre ?: '-' }}</td>
                        <td>Anticipo</td>
                        <td>{{ $venta->metodo_pago }}</td>
                        <td class="right">${{ number_format($venta->pago, 2) }}</td>
                    </tr>
                @endforeach

                {{-- Abonos a credito con folio de la venta y cliente --}}
                @foreach($abonos as $abono)
                    @php
                        $folioVenta = 'S/F';
                        $clienteAbono = '-';
                        if ($abono->plan) {
                            if ($abono->plan->venta) {
                                $folioVenta = $abono->plan->venta->folio ?? 'S/F';
                            }
                            if ($abono->plan->cliente) {
                                $clienteAbono = $abono->plan->cliente->nombre . ' ' . ($abono->plan->cliente->apellido_p ?? '');
                            }
                        }
                    @endphp
                    <tr>
                        <td>{{ $folioVenta }}</td>
                        <td>{{ \Carbon\Carbon::parse($abono->fecha_pago)->format('d/m/Y') }}</td>
                        <td>{{ $clienteAbono }}</td>
                        <td>Abono #{{ $abono->numero_cuota }}</td>
                        <td>{{ $abono->metodo_pago }}</td>
                        <td class="right">${{ number_format($abono->monto_pagado, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="subtotal-metodo">
                    <td colspan="5" class="right">Subtotal Creditos (Anticipos + Abonos)</td>
                    <td class="right">${{ number_format($totalAnticipos + $totalAbonos, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="conteo">
            {{ $ventasCredito->count() }} anticipo(s) + {{ $abonos->count() }} abono(s)
        </div>
    @endif

    {{-- Subtotal general de ingresos --}}
    <table class="table" style="margin-top: 4px;">
        <tr class="subtotal-row">
            <td colspan="3" class="right" style="border: none;">TOTAL INGRESOS BRUTOS</td>
            <td class="right" style="border: 1px solid #000;">${{ number_format($totalIngresos, 2) }}</td>
        </tr>
    </table>

    <!-- ══════════════════════════════════════════════ -->
    <!--         INVERSIONES REALIZADAS (GASTOS)       -->
    <!-- ══════════════════════════════════════════════ -->
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

        <div class="conteo">{{ $gastos->count() }} gasto(s) registrado(s)</div>
    @else
        <p class="sin-datos">No se registraron gastos en este periodo</p>
    @endif

    <!-- ══════════════════════════════════════════════ -->
    <!--            RESULTADO DEL PERIODO              -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="section-title">Resultado del Periodo</div>

    <div class="summary-container">
        <table class="summary-table">
            <tr class="summary-title">
                <td>Concepto</td>
                <td class="right">Monto</td>
            </tr>
            <tr>
                <td>Ingresos por contado</td>
                <td class="right">${{ number_format($totalContado, 2) }}</td>
            </tr>
            <tr>
                <td>Ingresos por anticipos (credito)</td>
                <td class="right">${{ number_format($totalAnticipos, 2) }}</td>
            </tr>
            <tr>
                <td>Ingresos por abonos (credito)</td>
                <td class="right">${{ number_format($totalAbonos, 2) }}</td>
            </tr>
            <tr style="border-top: 1px solid #000;">
                <td><strong>Total Ingresos Brutos</strong></td>
                <td class="right"><strong>${{ number_format($totalIngresos, 2) }}</strong></td>
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