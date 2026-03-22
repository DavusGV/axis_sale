<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Corte de Caja</title>

<style>

body{
    font-family: DejaVu Sans, sans-serif;
    font-size:8px;
    color:#111827;
}

/* HEADER FACTURA */

.header{
    width:100%;
    margin-bottom:8px;
}

.header-table{
    width:100%;
}

.logo{
    width:70px;
}

.company-info{
    text-align:right;
    font-size:9px;
}

.company-name{
    font-size:14px;
    font-weight:bold;
    text-align:center;
}

.report-title-box{
    border:1px solid #000;
    text-align:center;
    padding:4px;
    font-weight:bold;
    font-size:11px;
    margin-top:4px;
}

/* INFO */

.info{
    width:100%;
    border:1px solid #ccc;
    margin-top:6px;
}

.info td{
    padding:4px;
    font-size:9px;
}

/* SECTION */

.section-title{
    margin-top:10px;
    font-weight:bold;
    font-size:10px;
    border-bottom:1px solid #000;
    padding-bottom:2px;
    text-align: center;
}

/* TABLE */

.table{
    width:100%;
    border-collapse:collapse;
    margin-top:4px;
}

.table th{
    background:#e5e7eb;
    border:1px solid #ccc;
    padding:1px;
    font-size:8px;
    text-transform:uppercase;
}

.table td{
    border:1px solid #ddd;
    padding:1px;
}

.right{ text-align:right; }
.center{ text-align:center; }

/* META */

.sale-meta{
    font-size:9px;
    margin-bottom:2px;
}

.sale-total{ 
    text-align:right; 
    font-weight:bold; 
    font-size:10px;
}

.total-general {
    margin-top:10px;
    padding:6px;
    border-top:2px solid #000;
    text-align:right;
    font-weight:bold;
    font-size:12px;
}


/* BADGE MÉTODO */

.badge{
    padding:2px 6px;
    border:1px solid #000;
    font-size:9px;
    font-weight:bold;
}

/* RESUMEN FACTURA */

.summary-container{
    margin-top:5px;
    width:50%;        
    margin-left:auto;
}

.summary-table{
    width:100%;
    border-collapse:collapse;
}

.summary-table td{
    border:1px solid #ccc;
    padding:2px;
    font-size:10px;
}

.summary-title{
    background:#f3f4f6;
    font-weight:bold;
}

.summary-final{
    font-weight:bold;
    font-size:11px;
}

/* FOOTER */

.footer{
    margin-top:10px;
    text-align:center;
    font-size:8px;
    color:#6b7280;
}

</style>

</head>

<body>

<!-- HEADER -->
<div class="header">

    <table class="header-table">
        <tr>

            <!-- LOGO -->
            <td style="width:80px;">
                @if($empresa['logo'])
                    <img src="data:image/png;base64,{{ $empresa['logo'] }}" class="logo">
                @endif
            </td>

            <!-- EMPRESA CENTRADA -->
            <td style="text-align:center;">
                <div class="company-name">
                    {{ $empresa['nombre'] }}
                </div>
                <div>
                    Corte de caja
                </div>
                <div>
                    {{ now() }}
                </div>
            </td>

        </tr>
    </table>

    <div class="report-title-box">
        REPORTE DE CIERRE DE CAJA
    </div>

</div>

<!-- INFO GENERAL -->
<table class="info">
    <tr>
        <td><strong>Folio:</strong> #{{ $historial->id }}</td>
        <td><strong>Apertura:</strong> {{ $historial->fecha_apertura }}</td>
        <td><strong>Cierre:</strong> {{ $historial->fecha_cierre }}</td>
        <td><strong>Cajero:</strong> {{ $historial->usuario->name ?? 'Admin' }}</td>
    </tr>

    <tr>
        <td><strong>Saldo inicial:</strong> ${{ number_format($historial->saldo_inicial,2) }}</td>
        <td><strong>Saldo final:</strong> ${{ number_format($historial->saldo_final,2) }}</td>
        <td><strong>Ventas:</strong> {{ $resumen['total_ventas'] }}</td>
    </tr>
</table>

<!-- DETALLE -->
<div class="section-title">Detalle de ventas</div>

@foreach($ventas as $venta)

<div style="margin-top:6px;">

    <div class="sale-meta">
        <strong>Venta #{{ $venta->id }}</strong> |
        {{ $venta->created_at }} |
        {{ $venta->usuario->name ?? 'N/A' }} |
        <span class="badge">{{ ucfirst($venta->metodo_pago) }}</span>
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
                    <td class="right">${{ number_format($detalle->precio,2) }}</td>
                    <td class="right">${{ number_format($detalle->descuento_aplicado,2) }}</td>
                    <td class="right">${{ number_format($detalle->subtotal,2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="sale-total">
        Total: ${{ number_format($venta->total,2) }}
    </div>

</div>

@endforeach

<!--TOTAL GENERAL--> 
<div class="total-general">
    TOTAL VENTAS: ${{ number_format($resumen['total_general'],2) }}
</div>

<!-- RESUMEN -->
<div class="section-title">Resumen general</div>

<table class="summary-table">

    <tr class="summary-title">
        <td>Concepto</td>
        <td class="right">Monto</td>
    </tr>
    
    <tr>
        <td>Efectivo</td>
        <td class="right">${{ number_format($resumen['total_efectivo'],2) }}</td>
    </tr>

    <tr>
        <td>Transferencia</td>
        <td class="right">${{ number_format($resumen['total_transferencia'],2) }}</td>
    </tr>

    <tr>
        <td>Crédito</td>
        <td class="right">${{ number_format($resumen['total_credito'],2) }}</td>
    </tr>

    <tr>
        <td>Descuentos</td>
        <td class="right">${{ number_format($resumen['total_descuentos'],2) }}</td>
    </tr>

    <tr class="summary-final">
        <td>TOTAL FINAL</td>
        <td class="right">${{ number_format($resumen['total_general'],2) }}</td>
    </tr>

</table>


<div class="footer">
    Documento generado automáticamente por el sistema
</div>

</body>
</html>