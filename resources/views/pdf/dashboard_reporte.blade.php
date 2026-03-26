<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte del Dashboard</title>
    <style>
        @page {
            margin: 15mm 12mm 15mm 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111827;
        }

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

        .section-title {
            margin-top: 10px;
            font-weight: bold;
            font-size: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            text-align: center;
        }

        /* ── Tarjetas resumen ── */
        .cards-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .card {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: center;
            width: 33.33%;
        }

        .card-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
        }

        .card-value {
            font-size: 14px;
            font-weight: bold;
            margin-top: 2px;
        }

        .card-green { color: #059669; }
        .card-red { color: #dc2626; }
        .card-blue { color: #2563eb; }
        .card-purple { color: #7c3aed; }
        .card-orange { color: #f59e0b; }

        /* ── Tablas ── */
        .table { width: 100%; border-collapse: collapse; margin-top: 4px; }
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
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }

        .total-row td {
            border-top: 2px solid #000;
            font-weight: bold;
            font-size: 9px;
        }

        /* ── Barra visual simple ── */
        .bar-container {
            width: 100%;
            background: #e5e7eb;
            height: 12px;
            position: relative;
        }

        .bar-fill {
            height: 12px;
            background: #3b82f6;
        }

        .bar-label {
            font-size: 7px;
            color: #6b7280;
            text-align: right;
        }

        /* ── Stock badges ── */
        .stock-cero {
            color: #dc2626;
            font-weight: bold;
        }

        .stock-bajo {
            color: #f59e0b;
            font-weight: bold;
        }

        /* ── Layout dos columnas ── */
        .two-cols {
            width: 100%;
        }

        .two-cols td {
            width: 50%;
            vertical-align: top;
            padding: 0 4px;
        }

        .sin-datos {
            text-align: center;
            color: #9ca3af;
            font-style: italic;
            padding: 8px;
            font-size: 9px;
        }

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
                    <div class="company-sub">Reporte del Dashboard</div>
                </td>
                <td class="header-right" style="width: 100px;">
                    Fecha de emision:<br>
                    {{ $fecha }}
                </td>
            </tr>
        </table>

        <div class="report-title-box">
            REPORTE DIARIO — {{ strtoupper($fecha) }}
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--              TARJETAS RESUMEN                 -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="section-title">Resumen del Dia</div>

    <table class="cards-table">
        <tr>
            <td class="card">
                <div class="card-label">Ingresos</div>
                <div class="card-value card-green">${{ number_format($data['ingresos_dia'], 2) }}</div>
            </td>
            <td class="card">
                <div class="card-label">Gastos</div>
                <div class="card-value card-red">${{ number_format($data['gastos_dia'], 2) }}</div>
            </td>
            <td class="card">
                <div class="card-label">Ganancias</div>
                <div class="card-value card-blue">${{ number_format($data['ganancias'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td class="card">
                <div class="card-label">Descuentos</div>
                <div class="card-value card-orange">${{ number_format($data['descuentos'], 2) }}</div>
            </td>
            <td class="card">
                <div class="card-label">Creditos Pendientes</div>
                <div class="card-value card-purple">{{ $data['creditos_pendientes'] }}</div>
            </td>
            <td class="card">
                <div class="card-label">Cotizaciones Pendientes</div>
                <div class="card-value card-purple">{{ $data['cotizaciones_pendientes'] }}</div>
            </td>
        </tr>
    </table>

    <!-- ══════════════════════════════════════════════ -->
    <!--             TENDENCIA SEMANAL                 -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="section-title">Tendencia Semanal</div>

    @php
        $maxVenta = collect($data['tendencia_semanal'])->max('total');
        $totalSemana = collect($data['tendencia_semanal'])->sum('total');
    @endphp

    <table class="table">
        <thead>
            <tr>
                <th style="width: 12%;">Dia</th>
                <th style="width: 18%;">Fecha</th>
                <th style="width: 50%;">Ventas</th>
                <th style="width: 20%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['tendencia_semanal'] as $dia)
                @php
                    $porcentaje = $maxVenta > 0 ? ($dia['total'] / $maxVenta) * 100 : 0;
                @endphp
                <tr>
                    <td class="bold">{{ $dia['dia'] }}</td>
                    <td>{{ $dia['fecha'] }}</td>
                    <td>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: {{ $porcentaje }}%;"></div>
                        </div>
                    </td>
                    <td class="right">${{ number_format($dia['total'], 2) }}</td>
                </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="3" class="right">Total Semana</td>
                <td class="right">${{ number_format($totalSemana, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- ══════════════════════════════════════════════ -->
    <!--     TOP PRODUCTOS + VENTAS DEL DIA            -->
    <!-- ══════════════════════════════════════════════ -->
    <table class="two-cols">
        <tr>
            <!-- Top productos historico -->
            <td>
                <div class="section-title">Top Productos (Historico)</div>

                @if(count($data['top_productos']) > 0)
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th class="right">Vendidos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['top_productos'] as $i => $prod)
                                <tr>
                                    <td class="center bold">{{ $i + 1 }}</td>
                                    <td>{{ $prod['nombre'] }}</td>
                                    <td class="right bold">{{ $prod['total_vendido'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="sin-datos">Sin datos</p>
                @endif
            </td>

            <!-- Productos vendidos hoy -->
            <td>
                <div class="section-title">Productos Vendidos Hoy</div>

                @if(count($data['ventas_dia_productos']) > 0)
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="right">Vendidos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['ventas_dia_productos'] as $prod)
                                <tr>
                                    <td>{{ $prod['nombre'] }}</td>
                                    <td class="right bold">{{ $prod['total_vendido'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="sin-datos">Sin ventas registradas hoy</p>
                @endif
            </td>
        </tr>
    </table>

    <!-- ══════════════════════════════════════════════ -->
    <!--                  STOCK                        -->
    <!-- ══════════════════════════════════════════════ -->
    <table class="two-cols">
        <tr>
            <!-- Stock agotado -->
            <td>
                <div class="section-title">Productos Sin Stock</div>

                @if(count($data['stock_cero']) > 0)
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="center">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['stock_cero'] as $prod)
                                <tr>
                                    <td>{{ $prod['nombre'] }}</td>
                                    <td class="center stock-cero">{{ $prod['stock'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="sin-datos">Todos los productos tienen stock</p>
                @endif
            </td>

            <!-- Stock bajo -->
            <td>
                <div class="section-title">Stock Bajo (menos de 10)</div>

                @if(count($data['stock_bajo']) > 0)
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="center">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['stock_bajo'] as $prod)
                                <tr>
                                    <td>{{ $prod['nombre'] }}</td>
                                    <td class="center stock-bajo">{{ $prod['stock'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="sin-datos">No hay productos con stock bajo</p>
                @endif
            </td>
        </tr>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        Documento generado el {{ $fecha }} | {{ $establecimiento }} | Reporte del Dashboard
    </div>

</body>
</html>