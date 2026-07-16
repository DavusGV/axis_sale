<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; }

        .pagina {
            page-break-after: always;
            padding: {{ $config['margen']['superior'] }}mm {{ $config['margen']['derecho'] }}mm {{ $config['margen']['inferior'] }}mm {{ $config['margen']['izquierdo'] }}mm;
        }
        .pagina:last-child { page-break-after: auto; }

        table.rejilla { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.rejilla td {
            width: {{ round(100 / $config['columnas'], 4) }}%;
            height: {{ $config['etiqueta']['alto'] }}mm;
            padding: {{ $config['etiqueta']['gap_vertical'] }}mm {{ $config['etiqueta']['gap_horizontal'] }}mm;
            text-align: center;
            vertical-align: middle;
            @if ($config['guias_corte'])
            border: 1px dashed #bbbbbb;
            @endif
        }

        .etiqueta .nombre {
            font-size: 8pt; font-weight: bold; margin-bottom: 1mm;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .etiqueta img.barcode {
            width: 100%;
            height: {{ $config['etiqueta']['alto_barcode'] }}mm;
            display: block;
        }
        .etiqueta .codigo { font-size: 7pt; letter-spacing: 0.5px; margin-top: 0.5mm; }
        .etiqueta .precio { font-size: 8pt; font-weight: bold; margin-top: 0.5mm; }
    </style>
</head>
<body>
    @foreach ($paginas as $pagina)
        <div class="pagina">
            <table class="rejilla">
                @foreach (array_chunk($pagina, $config['columnas']) as $fila)
                    <tr>
                        @foreach ($fila as $etiqueta)
                            <td>
                                <div class="etiqueta">
                                    <div class="nombre">{{ $etiqueta['nombre'] }}</div>
                                    <img class="barcode" src="{{ $etiqueta['barcode'] }}" alt="barcode">
                                    <div class="codigo">{{ $etiqueta['codigo'] }}</div>
                                    @if (!is_null($etiqueta['precio']))
                                        <div class="precio">${{ number_format((float) $etiqueta['precio'], 2) }}</div>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                        {{-- celdas vacias para completar la ultima fila --}}
                        @for ($i = count($fila); $i < $config['columnas']; $i++)
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>