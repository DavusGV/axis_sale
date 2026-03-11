<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cierre de Caja</title>

<style>

body{
    font-family: DejaVu Sans, sans-serif;
    font-size:12px;
    margin:20px;
}

/* encabezado */

.header{
    text-align:center;
    margin-bottom:20px;
}

.header h2{
    margin:0;
}

/* grid estilo bootstrap */

.row{
    width:100%;
    clear:both;
}

.col-6{
    width:50%;
    float:left;
}

.col-12{
    width:100%;
}

/* tablas */

.table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

.table th{
    background:#f2f2f2;
    border:1px solid #ddd;
    padding:6px;
}

.table td{
    border:1px solid #ddd;
    padding:6px;
}

.text-right{
    text-align:right;
}

.text-center{
    text-align:center;
}

/* resumen */

.summary{
    width:40%;
    float:right;
    margin-top:20px;
}

.summary td{
    padding:6px;
}

.summary tr td:last-child{
    text-align:right;
}

</style>

</head>

<body>

<!-- HEADER -->

<div class="header">
<h2>REPORTE DE CIERRE DE CAJA</h2>
<p>Historial #{{ $historial->id }}</p>
</div>

<!-- INFO CAJA -->

<div class="row">

<div class="col-6">
<strong>Fecha apertura:</strong><br>
{{ $historial->created_at }}
</div>

<div class="col-6">
<strong>Fecha cierre:</strong><br>
{{ $historial->updated_at }}
</div>

</div>

<!-- RESUMEN -->

<h3>Resumen</h3>

<table class="table">

<thead>

<tr>
<th>Total general</th>
<th>Efectivo</th>
<th>Transferencia</th>
<th>Descuentos</th>
<th>Productos vendidos</th>
</tr>

</thead>

<tbody>

<tr>

<td class="text-right">
${{ number_format($resumen['total_general'],2) }}
</td>

<td class="text-right">
${{ number_format($resumen['total_efectivo'],2) }}
</td>

<td class="text-right">
${{ number_format($resumen['total_transferencia'],2) }}
</td>

<td class="text-right">
${{ number_format($resumen['total_descuentos'],2) }}
</td>

<td class="text-center">
{{ $resumen['total_productos_vendidos'] }}
</td>

</tr>

</tbody>

</table>

<h3>Listado de Ventas</h3>

<table class="table">

<thead>
<tr>
<th># Venta</th>
<th>Usuario</th>
<th>Método de pago</th>
<th class="text-right">Total</th>
<th class="text-right">Descuento</th>
<th>Fecha</th>
</tr>
</thead>

<tbody>

<tbody>

@foreach($ventas as $venta)

<!-- FILA DE LA VENTA -->

<tr>

<td class="text-center">
{{ $venta->id }}
</td>

<td>
{{ $venta->usuario->name ?? 'N/A' }}
</td>

<td class="text-center">
{{ ucfirst($venta->metodo_pago) }}
</td>

<td class="text-right">
${{ number_format($venta->total,2) }}
</td>

<td class="text-right">
${{ number_format($venta->descuento,2) }}
</td>

<td>
{{ $venta->created_at }}
</td>

</tr>

<!-- FILA DETALLE PRODUCTOS -->

<tr>

<td colspan="6">

<table class="table">

<thead>
<tr>
<th>Producto</th>
<th class="text-center">Cantidad</th>
<th class="text-right">Precio</th>
<th class="text-right">Subtotal</th>
</tr>
</thead>

<tbody>

@foreach($venta->detalles as $detalle)

<tr>

<td>
{{ $detalle->producto->nombre ?? 'Producto eliminado' }}
</td>

<td class="text-center">
{{ $detalle->cantidad }}
</td>

<td class="text-right">
${{ number_format($detalle->precio,2) }}
</td>

<td class="text-right">
${{ number_format($detalle->cantidad * $detalle->precio,2) }}
</td>

</tr>

@endforeach

</tbody>

</table>

</td>

</tr>

@endforeach

</tbody>
</table>
</body>
</html>