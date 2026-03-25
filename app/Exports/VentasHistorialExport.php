<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class VentasHistorialExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $ventas;
    protected $fechaInicio;
    protected $fechaFin;
    protected $establecimiento;

    // Control de filas para estilos
    protected $filasEncabezadoVentas = [];
    protected $filasSubtotalVentas = [];
    protected $filaResumenInicio;
    protected $totalFilas;

    public function __construct($ventas, $fechaInicio, $fechaFin, $establecimiento = '')
    {
        $this->ventas = $ventas;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->establecimiento = $establecimiento;
    }

    public function title(): string
    {
        return 'Historial de Ventas';
    }

    public function array(): array
    {
        $filas = [];
        $fila = 1;

        // ── Encabezado principal ──
        $filas[] = ['Reporte de Historial de Ventas'];
        $filas[] = [$this->establecimiento];
        $filas[] = ['Periodo: ' . $this->fechaInicio . ' al ' . $this->fechaFin];
        $filas[] = [''];
        $fila = 5;

        // Acumuladores generales
        $totalVendido = 0;
        $totalCancelado = 0;
        $totalDescuentos = 0;
        $totalIva = 0;
        $totalCostoCompra = 0;
        $totalGanancia = 0;
        $cantVentas = 0;
        $cantCanceladas = 0;
        $cantProductos = 0;

        // ── Detalle por cada venta ──
        foreach ($this->ventas as $venta) {
            $esCredito = $venta->planPago !== null;
            $statusTexto = ($venta->status ?? 'vendido') === 'cancelada' ? 'CANCELADA' : 'VENDIDA';
            $tipoTexto = $esCredito ? 'Credito' : 'Contado';
            $clienteTexto = '';
            if ($esCredito && $venta->planPago && $venta->planPago->cliente) {
                $clienteTexto = $venta->planPago->cliente->nombre . ' ' . ($venta->planPago->cliente->apellido_p ?? '');
            }

            // Encabezado de la venta
            $this->filasEncabezadoVentas[] = $fila;
            $filas[] = [
                'Venta: ' . ($venta->folio ?? 'S/F'),
                'Fecha: ' . ($venta->created_at ? $venta->created_at->format('d/m/Y H:i') : ''),
                'Metodo: ' . ($venta->metodo_pago ?? ''),
                'Tipo: ' . $tipoTexto,
                'Status: ' . $statusTexto,
                $clienteTexto ? 'Cliente: ' . $clienteTexto : '',
                '',
                '',
            ];
            $fila++;

            // Encabezados de tabla de productos
            $filas[] = ['Producto', 'Cantidad', 'Precio Venta', 'Precio Compra', 'Descuento', 'Subtotal', 'Costo Total', 'Ganancia'];
            $fila++;

            // Detalle de productos
            $subtotalVenta = 0;
            $costoVenta = 0;
            $descuentoVenta = 0;
            $cantProductosVenta = 0;

            foreach ($venta->detalles as $detalle) {
                $precioVenta = $detalle->precio;
                $precioCompra = $detalle->precio_compra ?? 0;
                $cantidad = $detalle->cantidad;
                $descuento = $detalle->descuento_aplicado ?? 0;
                $subtotal = ($precioVenta * $cantidad) - $descuento;
                $costoTotal = $precioCompra * $cantidad;
                $ganancia = $subtotal - $costoTotal;

                $filas[] = [
                    $detalle->producto->nombre ?? 'Producto eliminado',
                    $cantidad,
                    round($precioVenta, 2),
                    round($precioCompra, 2),
                    round($descuento, 2),
                    round($subtotal, 2),
                    round($costoTotal, 2),
                    round($ganancia, 2),
                ];
                $fila++;

                $subtotalVenta += $subtotal;
                $costoVenta += $costoTotal;
                $descuentoVenta += $descuento;
                $cantProductosVenta += $cantidad;
            }

            $gananciaVenta = $subtotalVenta - $costoVenta;

            // Subtotal de la venta
            $this->filasSubtotalVentas[] = $fila;
            $filas[] = [
                '',
                $cantProductosVenta . ' producto(s)',
                '',
                '',
                round($descuentoVenta, 2),
                round($subtotalVenta, 2),
                round($costoVenta, 2),
                round($gananciaVenta, 2),
            ];
            $fila++;

            // Fila con IVA y total de la venta
            $filas[] = [
                '',
                '',
                '',
                'IVA: $' . number_format($venta->iva_total ?? 0, 2),
                '',
                'Total: $' . number_format($venta->total, 2),
                '',
                '',
            ];
            $fila++;

            // Fila vacia de separacion
            $filas[] = [''];
            $fila++;

            // Acumuladores
            if (($venta->status ?? 'vendido') === 'cancelada') {
                $totalCancelado += $venta->total;
                $cantCanceladas++;
            } else {
                $totalVendido += $venta->total;
                $totalDescuentos += $descuentoVenta;
                $totalIva += ($venta->iva_total ?? 0);
                $totalCostoCompra += $costoVenta;
                $totalGanancia += $gananciaVenta;
                $cantProductos += $cantProductosVenta;
            }
            $cantVentas++;
        }

        // ── RESUMEN GENERAL ──
        $this->filaResumenInicio = $fila;
        $filas[] = ['RESUMEN GENERAL DEL PERIODO'];
        $fila++;

        $filas[] = ['Concepto', '', '', '', '', '', '', 'Monto'];
        $fila++;

        $filas[] = ['Total ventas realizadas', '', '', '', '', '', '', $cantVentas . ' ventas'];
        $fila++;

        $filas[] = ['Ventas activas', '', '', '', '', '', '', ($cantVentas - $cantCanceladas) . ' ventas'];
        $fila++;

        $filas[] = ['Ventas canceladas', '', '', '', '', '', '', $cantCanceladas . ' ventas'];
        $fila++;

        $filas[] = ['Total productos vendidos', '', '', '', '', '', '', $cantProductos . ' unidades'];
        $fila++;

        $filas[] = ['Total descuentos aplicados', '', '', '', '', '', '', round($totalDescuentos, 2)];
        $fila++;

        $filas[] = ['IVA total cobrado', '', '', '', '', '', '', round($totalIva, 2)];
        $fila++;

        $filas[] = ['Total vendido (ventas activas)', '', '', '', '', '', '', round($totalVendido, 2)];
        $fila++;

        $filas[] = ['Total cancelado', '', '', '', '', '', '', round($totalCancelado, 2)];
        $fila++;

        $filas[] = ['Costo de compra total', '', '', '', '', '', '', round($totalCostoCompra, 2)];
        $fila++;

        $filas[] = ['GANANCIA NETA', '', '', '', '', '', '', round($totalGanancia, 2)];
        $fila++;

        $this->totalFilas = $fila - 1;

        return $filas;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24,
            'B' => 14,
            'C' => 16,
            'D' => 16,
            'E' => 14,
            'F' => 16,
            'G' => 16,
            'H' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // ── Merge y estilo del encabezado principal ──
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->mergeCells('A3:H3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '4B5563']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Encabezados de cada venta ──
        foreach ($this->filasEncabezadoVentas as $f) {
            $sheet->getStyle("A{$f}:H{$f}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '374151'],
                ],
            ]);

            // Encabezado de productos (fila siguiente)
            $fp = $f + 1;
            $sheet->getStyle("A{$fp}:H{$fp}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '111827']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // ── Subtotales de cada venta ──
        foreach ($this->filasSubtotalVentas as $f) {
            $sheet->getStyle("A{$f}:H{$f}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                ],
            ]);
        }

        // ── Resumen general ──
        if ($this->filaResumenInicio) {
            $fr = $this->filaResumenInicio;
            $sheet->mergeCells("A{$fr}:H{$fr}");
            $sheet->getStyle("A{$fr}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Encabezado de resumen
            $fre = $fr + 1;
            $sheet->getStyle("A{$fre}:H{$fre}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ]);

            // Fila de ganancia neta (ultima fila del resumen)
            $sheet->getStyle("A{$this->totalFilas}:H{$this->totalFilas}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'],
                ],
            ]);
        }

        // Formato numerico columnas de montos
        $sheet->getStyle("C1:H{$this->totalFilas}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        return [];
    }
}