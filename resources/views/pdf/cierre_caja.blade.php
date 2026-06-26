<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Corte de Caja</title>

<style>

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 8px;
    color: #111827;
}

/* HEADER */

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

.report-title-box {
    border: 1px solid #000;
    text-align: center;
    padding: 4px;
    font-weight: bold;
    font-size: 11px;
    margin-top: 4px;
}

/* INFO */

.info {
    width: 100%;
    border: 1px solid #ccc;
    margin-top: 6px;
    border-collapse: collapse;
}

.info td {
    padding: 4px;
    font-size: 9px;
}

/* SECTION */

.section-title {
    margin-top: 10px;
    font-weight: bold;
    font-size: 10px;
    border-bottom: 2px solid #000;
    padding: 3px 0 2px;
    text-align: center;
    text-transform: uppercase;
}

.section-title-abonos {
    margin-top: 10px;
    font-weight: bold;
    font-size: 10px;
    border-bottom: 2px solid #374151;
    padding: 3px 0 2px;
    text-align: center;
    text-transform: uppercase;
    color: #374151;
    background: #f3f4f6;
}

/* TABLE */

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
}

.table th {
    background: #e5e7eb;
    border: 1px solid #ccc;
    padding: 2px;
    font-size: 8px;
    text-transform: uppercase;
}

.table td {
    border: 1px solid #ddd;
    padding: 2px;
}

.right  { text-align: right; }
.center { text-align: center; }
.bold   { font-weight: bold; }

/* VENTA CARD */

.venta-card {
    margin-top: 6px;
    border: 1px solid #e5e7eb;
    padding: 4px;
}

.venta-header {
    font-size: 9px;
    margin-bottom: 3px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 2px;
}

.venta-footer {
    text-align: right;
    font-weight: bold;
    font-size: 9px;
    margin-top: 2px;
}

/* ABONO CARD */

.abono-card {
    margin-top: 5px;
    border: 1px solid #d1d5db;
    border-left: 3px solid #6b7280;
    padding: 4px 6px;
    background: #f9fafb;
}

.abono-header {
    font-size: 9px;
    margin-bottom: 2px;
}

.abono-monto {
    text-align: right;
    font-weight: bold;
    font-size: 10px;
    color: #111827;
}

/* BADGE */

.badge {
    padding: 1px 5px;
    border: 1px solid #000;
    font-size: 8px;
    font-weight: bold;
}

.badge-credito {
    padding: 1px 5px;
    border: 1px solid #374151;
    font-size: 8px;
    font-weight: bold;
    color: #374151;
    background: #f3f4f6;
}

/* RESUMEN */

.resumen-wrapper {
    margin-top: 8px;
}

.resumen-bloque {
    width: 100%;
    border-collapse: collapse;
}

.resumen-bloque td {
    border: 1px solid #ccc;
    padding: 3px 5px;
    font-size: 9px;
}

.resumen-bloque-titulo {
    background: #e5e7eb;
    font-weight: bold;
    font-size: 9px;
    text-transform: uppercase;
    padding: 3px 5px;
    border: 1px solid #ccc;
}

.resumen-total-real {
    margin-top: 8px;
    padding: 6px;
    border-top: 2px solid #000;
    border-bottom: 2px solid #000;
    text-align: right;
    font-weight: bold;
    font-size: 12px;
}

/* FOOTER */

.footer {
    margin-top: 12px;
    text-align: center;
    font-size: 8px;
    color: #6b7280;
    border-top: 1px solid #e5e7eb;
    padding-top: 4px;
}

.sin-registros {
    text-align: center;
    color: #9ca3af;
    font-style: italic;
    padding: 6px;
}

</style>

</head>
<body>

<!-- HEADER -->
<div class="header">
    <table class="header-table">
        <tr>
            <td style="width:80px;">
                @if($empresa['logo'])
                    <img src="data:image/png;base64,{{ $empresa['logo'] }}" class="logo">
                @endif
            </td>
            <td style="text-align:center;">
                <div class="company-name">{{ $empresa['nombre'] }}</div>
                <div>Corte de caja</div>
                <div>{{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
    <div class="report-title-box">REPORTE DE CIERRE DE CAJA</div>
</div>

<!-- INFO GENERAL -->
<table class="info">
    <tr>
        <td><strong>Folio:</strong> #{{ $historial->id }}</td>
        <td><strong>Apertura:</strong> {{ $historial->fecha_apertura }}</td>
        <td><strong>Cierre:</strong> {{ $historial->fecha_cierre ?? 'En curso' }}</td>
        <td><strong>Cajero:</strong> {{ $historial->usuario->name ?? 'Admin' }}</td>
    </tr>
    <tr>
        <td><strong>Saldo inicial:</strong> ${{ number_format($historial->saldo_inicial, 2) }}</td>
        <td><strong>Saldo final:</strong> ${{ number_format($historial->saldo_final ?? 0, 2) }}</td>
        <td><strong>Ventas:</strong> {{ $resumen['num_ventas'] }}</td>
        <td><strong>Abonos:</strong> {{ $resumen['num_abonos'] }}</td>
    </tr>
</table>

<div class="section-title">Detalle de ventas</div>

@if($ventas->isEmpty())
    <div class="sin-registros">Sin ventas en este periodo</div>
@else
    @foreach($ventas as $venta)
        @php $esCredito = $venta->planPago !== null; @endphp
        <div class="venta-card">

            <div class="venta-header">
                <strong>{{ $venta->folio }}</strong> &nbsp;|&nbsp;
                {{ \Carbon\Carbon::parse($venta->created_at)->format('d/m/Y H:i') }} &nbsp;|&nbsp;
                {{ $venta->usuario->name ?? 'N/A' }} &nbsp;|&nbsp;
                @if($esCredito)
                    {{-- venta a credito: se muestra el badge de credito y el metodo de pago del anticipo --}}
                    <span class="badge-credito">Credito</span>
                @else
                    <span class="badge">{{ ucfirst($venta->metodo_pago) }}</span>
                @endif
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="center">Cant</th>
                        <th class="right">Precio</th>
                        <th class="right">Desc</th>
                        <th class="right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($venta->detalles as $detalle)
                        <tr>
                            <td>{{ $detalle->producto->nombre ?? 'Producto eliminado' }}</td>
                            <td class="center">{{ $detalle->cantidad }}</td>
                            <td class="right">${{ number_format($detalle->precio, 2) }}</td>
                            <td class="right">${{ number_format($detalle->descuento_aplicado, 2) }}</td>
                            <td class="right">${{ number_format($detalle->subtotal - $detalle->descuento_aplicado, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($esCredito)
                {{-- desglose credito: total, restante sin comision, comision si aplica, por cobrar con comision --}}
                @php
                    $restante = $venta->total - $venta->planPago->anticipo;
                    $comision = (float) $venta->planPago->interes_aplicado;
                @endphp
                <table style="width:100%; margin-top:3px; border-collapse:collapse;">
                    <tr>
                        <td style="font-size:8px; color:#6b7280;">
                            Total venta: ${{ number_format($venta->total, 2) }} &nbsp;|&nbsp;
                            Restante: ${{ number_format($restante, 2) }}
                            @if($comision > 0)
                                &nbsp;|&nbsp; Comisión: ${{ number_format($comision, 2) }}
                            @endif
                            &nbsp;|&nbsp; Por cobrar: ${{ number_format($venta->planPago->total_financiado, 2) }}
                        </td>
                        <td class="right bold" style="font-size:9px;">
                            Anticipo recibido: ${{ number_format($venta->planPago->anticipo, 2) }}
                        </td>
                    </tr>
                </table>
            @else
                <div class="venta-footer">
                    Ingreso: ${{ number_format($venta->total, 2) }}
                </div>
            @endif

        </div>
    @endforeach
@endif

<div class="section-title-abonos">Abonos recibidos en este periodo</div>

@if($abonos->isEmpty())
    <div class="sin-registros">Sin abonos en este periodo</div>
@else
    @foreach($abonos as $abono)
        <div class="abono-card">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td class="abono-header">
                        <strong>Abono — Cuota #{{ $abono->numero_cuota }}</strong> &nbsp;|&nbsp;
                        Venta: <strong>{{ $abono->plan->venta->folio ?? ('Plan #' . $abono->plan_pago_id) }}</strong> &nbsp;|&nbsp;
                        {{ \Carbon\Carbon::parse($abono->fecha_pago)->format('d/m/Y') }} &nbsp;|&nbsp;
                        <span class="badge">{{ ucfirst($abono->metodo_pago) }}</span>
                    </td>
                    <td class="abono-monto" style="width:120px;">
                        ${{ number_format($abono->monto_pagado, 2) }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size:8px; color:#6b7280; padding-top:2px;">
                        Saldo anterior: ${{ number_format($abono->saldo_anterior, 2) }} &nbsp;&rarr;&nbsp;
                        Saldo pendiente: ${{ number_format($abono->saldo_despues, 2) }}
                        @if($abono->notas)
                            &nbsp;|&nbsp; {{ $abono->notas }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
@endif

<div class="section-title">Resumen de ingresos</div>

<div class="resumen-wrapper">

    {{-- Ingresos de ventas  anticipos agrupados por metodo de pago --}}
    <table class="resumen-bloque">
        <tr>
            <td colspan="2" class="resumen-bloque-titulo">
                Ventas — {{ $resumen['num_ventas'] }}
            </td>
        </tr>
        @foreach($resumen['ventas_por_metodo'] as $metodo => $monto)
            <tr>
                <td>{{ ucfirst($metodo) }}</td>
                <td class="right">${{ number_format($monto, 2) }}</td>
            </tr>
        @endforeach
        <tr class="bold">
            <td>Subtotal ventas ingresado</td>
            <td class="right">${{ number_format($resumen['total_ventas_directas'] + $resumen['total_anticipos'], 2) }}</td>
        </tr>
    </table>

    {{-- Abonos agrupados por metodo de pago --}}
    <table class="resumen-bloque" style="margin-top:6px;">
        <tr>
            <td colspan="2" class="resumen-bloque-titulo">
                Abonos ({{ $resumen['num_abonos'] }})
            </td>
        </tr>
        @foreach($resumen['abonos_por_metodo'] as $metodo => $monto)
            <tr>
                <td>{{ ucfirst($metodo) }}</td>
                <td class="right">${{ number_format($monto, 2) }}</td>
            </tr>
        @endforeach
        @if($resumen['abonos_por_metodo']->isEmpty())
            <tr>
                <td colspan="2" class="sin-registros">Sin abonos</td>
            </tr>
        @endif
        <tr class="bold">
            <td>Subtotal abonos recibido</td>
            <td class="right">${{ number_format($resumen['total_abonos'], 2) }}</td>
        </tr>
    </table>

    {{-- Descuentos: solo referencia, no afecta el ingreso real --}}
    <table class="resumen-bloque" style="margin-top:6px;">
        <tr>
            <td style="color:#6b7280;">Descuentos aplicados en ventas</td>
            <td class="right" style="color:#6b7280;">-${{ number_format($resumen['total_descuentos'], 2) }}</td>
        </tr>
    </table>

</div>

<div class="resumen-total-real">
    TOTAL INGRESO EN CAJA: ${{ number_format($resumen['total_ingreso_real'], 2) }}
</div>

<div class="footer">
    Documento generado automáticamente por el sistema
</div>

</body>
</html>