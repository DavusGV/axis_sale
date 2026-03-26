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

class DashboardExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $establecimiento;
    protected $fecha;

    // Control de filas para estilos
    protected $filasSeccion = [];
    protected $filasEncabezado = [];
    protected $totalFilas;

    public function __construct(array $data, string $establecimiento, string $fecha)
    {
        $this->data = $data;
        $this->establecimiento = $establecimiento;
        $this->fecha = $fecha;
    }

    public function title(): string
    {
        return 'Dashboard';
    }

    public function array(): array
    {
        $filas = [];
        $fila = 1;

        // ── Encabezado ──
        $filas[] = ['Reporte del Dashboard'];
        $filas[] = [$this->establecimiento];
        $filas[] = ['Fecha: ' . $this->fecha];
        $filas[] = [''];
        $fila = 5;

        // ── RESUMEN DEL DIA ──
        $this->filasSeccion[] = $fila;
        $filas[] = ['RESUMEN DEL DIA', '', '', ''];
        $fila++;

        $this->filasEncabezado[] = $fila;
        $filas[] = ['Concepto', 'Valor', '', ''];
        $fila++;

        $filas[] = ['Ingresos del dia', $this->data['ingresos_dia'], '', ''];
        $fila++;
        $filas[] = ['Gastos del dia', $this->data['gastos_dia'], '', ''];
        $fila++;
        $filas[] = ['Ganancias del dia', $this->data['ganancias'], '', ''];
        $fila++;
        $filas[] = ['Descuentos aplicados', $this->data['descuentos'], '', ''];
        $fila++;
        $filas[] = ['Creditos pendientes', $this->data['creditos_pendientes'] . ' credito(s)', '', ''];
        $fila++;
        $filas[] = ['Cotizaciones pendientes', $this->data['cotizaciones_pendientes'] . ' cotizacion(es)', '', ''];
        $fila++;

        $filas[] = [''];
        $fila++;

        // ── TENDENCIA SEMANAL ──
        $this->filasSeccion[] = $fila;
        $filas[] = ['TENDENCIA SEMANAL', '', '', ''];
        $fila++;

        $this->filasEncabezado[] = $fila;
        $filas[] = ['Dia', 'Fecha', 'Total Ventas', ''];
        $fila++;

        $totalSemana = 0;
        foreach ($this->data['tendencia_semanal'] as $dia) {
            $filas[] = [$dia['dia'], $dia['fecha'], $dia['total'], ''];
            $fila++;
            $totalSemana += $dia['total'];
        }

        $filas[] = ['', '', round($totalSemana, 2), ''];
        $fila++;

        $filas[] = [''];
        $fila++;

        // ── TOP PRODUCTOS MAS VENDIDOS ──
        $this->filasSeccion[] = $fila;
        $filas[] = ['TOP PRODUCTOS MAS VENDIDOS (HISTORICO)', '', '', ''];
        $fila++;

        $this->filasEncabezado[] = $fila;
        $filas[] = ['#', 'Producto', 'Unidades Vendidas', ''];
        $fila++;

        $pos = 1;
        foreach ($this->data['top_productos'] as $prod) {
            $filas[] = [$pos, $prod['nombre'], $prod['total_vendido'], ''];
            $fila++;
            $pos++;
        }

        $filas[] = [''];
        $fila++;

        // ── VENTAS DEL DIA - PRODUCTOS ──
        $this->filasSeccion[] = $fila;
        $filas[] = ['PRODUCTOS VENDIDOS HOY', '', '', ''];
        $fila++;

        if (count($this->data['ventas_dia_productos']) > 0) {
            $this->filasEncabezado[] = $fila;
            $filas[] = ['Producto', 'Unidades Vendidas', '', ''];
            $fila++;

            foreach ($this->data['ventas_dia_productos'] as $prod) {
                $filas[] = [$prod['nombre'], $prod['total_vendido'], '', ''];
                $fila++;
            }
        } else {
            $filas[] = ['Sin ventas registradas hoy', '', '', ''];
            $fila++;
        }

        $filas[] = [''];
        $fila++;

        // ── STOCK AGOTADO ──
        $this->filasSeccion[] = $fila;
        $filas[] = ['PRODUCTOS SIN STOCK', '', '', ''];
        $fila++;

        if (count($this->data['stock_cero']) > 0) {
            $this->filasEncabezado[] = $fila;
            $filas[] = ['Producto', 'Stock', '', ''];
            $fila++;

            foreach ($this->data['stock_cero'] as $prod) {
                $filas[] = [$prod['nombre'], $prod['stock'], '', ''];
                $fila++;
            }
        } else {
            $filas[] = ['Todos los productos tienen stock', '', '', ''];
            $fila++;
        }

        $filas[] = [''];
        $fila++;

        // ── STOCK BAJO ──
        $this->filasSeccion[] = $fila;
        $filas[] = ['PRODUCTOS CON STOCK BAJO (menos de 10)', '', '', ''];
        $fila++;

        if (count($this->data['stock_bajo']) > 0) {
            $this->filasEncabezado[] = $fila;
            $filas[] = ['Producto', 'Stock', '', ''];
            $fila++;

            foreach ($this->data['stock_bajo'] as $prod) {
                $filas[] = [$prod['nombre'], $prod['stock'], '', ''];
                $fila++;
            }
        } else {
            $filas[] = ['No hay productos con stock bajo', '', '', ''];
            $fila++;
        }

        $this->totalFilas = $fila - 1;

        return $filas;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 22,
            'C' => 20,
            'D' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Encabezado principal
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('A2:D2');
        $sheet->mergeCells('A3:D3');

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

        // Titulos de seccion
        foreach ($this->filasSeccion as $f) {
            $sheet->mergeCells("A{$f}:D{$f}");
            $sheet->getStyle("A{$f}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '374151'],
                ],
            ]);
        }

        // Encabezados de tablas
        foreach ($this->filasEncabezado as $f) {
            $sheet->getStyle("A{$f}:D{$f}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                ],
            ]);
        }

        // Formato numerico
        $sheet->getStyle("B1:C{$this->totalFilas}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        return [];
    }
}