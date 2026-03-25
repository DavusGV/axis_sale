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

class BalanceReporteExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $ventasPorMetodo;
    protected $ventasCredito;
    protected $abonos;
    protected $gastos;
    protected $fechaInicio;
    protected $fechaFin;
    protected $establecimiento;
    protected $totalContado;
    protected $totalAnticipos;
    protected $totalAbonos;

    // Control de filas para estilos
    protected $filasSubseccion = [];
    protected $filasEncabezado = [];
    protected $filasSubtotal = [];
    protected $filaTituloGastos;
    protected $filaEncabezadoGastos;
    protected $filaSubtotalGastos;
    protected $filaTotalIngresos;
    protected $filaResumenInicio;
    protected $totalFilas;

    /**
     * @param \Illuminate\Support\Collection $ventasPorMetodo - ventas de contado agrupadas por metodo
     * @param \Illuminate\Support\Collection $ventasCredito - ventas a credito
     * @param \Illuminate\Support\Collection $abonos - abonos con relacion plan.venta y plan.cliente
     * @param \Illuminate\Support\Collection $gastos
     * @param string $fechaInicio
     * @param string $fechaFin
     * @param string $establecimiento
     * @param float $totalContado
     * @param float $totalAnticipos
     * @param float $totalAbonos
     */
    public function __construct(
        $ventasPorMetodo,
        $ventasCredito,
        $abonos,
        $gastos,
        $fechaInicio,
        $fechaFin,
        $establecimiento,
        $totalContado,
        $totalAnticipos,
        $totalAbonos
    ) {
        $this->ventasPorMetodo = $ventasPorMetodo;
        $this->ventasCredito = $ventasCredito;
        $this->abonos = $abonos;
        $this->gastos = $gastos;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->establecimiento = $establecimiento;
        $this->totalContado = $totalContado;
        $this->totalAnticipos = $totalAnticipos;
        $this->totalAbonos = $totalAbonos;
    }

    public function title(): string
    {
        return 'Balance General';
    }

    public function array(): array
    {
        $filas = [];
        $fila = 1;

        // ── Encabezado principal ──
        $filas[] = ['Reporte de Balance General'];
        $filas[] = [$this->establecimiento];
        $filas[] = ['Periodo: ' . $this->fechaInicio . ' al ' . $this->fechaFin];
        $filas[] = [''];
        $fila = 5;

        // ── SECCION INGRESOS ──
        $this->filasSubseccion[] = $fila;
        $filas[] = ['INGRESOS GENERADOS', '', '', '', '', ''];
        $fila++;

        $totalIngresos = 0;

        // ── Ventas de contado agrupadas por metodo de pago ──
        foreach ($this->ventasPorMetodo as $metodo => $ventasDelMetodo) {
            // Titulo del metodo
            $this->filasSubseccion[] = $fila;
            $filas[] = [$metodo . ' (Contado)', '', '', '', '', ''];
            $fila++;

            // Encabezados
            $this->filasEncabezado[] = $fila;
            $filas[] = ['Folio', 'Fecha', 'Metodo', '', 'Monto ($)', ''];
            $fila++;

            $subtotalMetodo = 0;
            foreach ($ventasDelMetodo as $venta) {
                $filas[] = [
                    $venta->folio ?? 'S/F',
                    $venta->created_at ? $venta->created_at->format('d/m/Y H:i') : '',
                    $venta->metodo_pago ?? '',
                    '',
                    round($venta->total, 2),
                    '',
                ];
                $fila++;
                $subtotalMetodo += $venta->total;
            }

            // Subtotal del metodo
            $this->filasSubtotal[] = $fila;
            $filas[] = ['', '', '', 'Subtotal ' . $metodo, round($subtotalMetodo, 2), ''];
            $fila++;
            $totalIngresos += $subtotalMetodo;

            // Separacion
            $filas[] = [''];
            $fila++;
        }

        // ── Creditos: anticipos + abonos ──
        if ($this->ventasCredito->count() > 0 || $this->abonos->count() > 0) {
            $this->filasSubseccion[] = $fila;
            $filas[] = ['CREDITOS (Anticipos y Abonos)', '', '', '', '', ''];
            $fila++;

            // Encabezados de credito
            $this->filasEncabezado[] = $fila;
            $filas[] = ['Folio Venta', 'Fecha', 'Cliente', 'Tipo', 'Metodo', 'Monto ($)'];
            $fila++;

            // Anticipos
            foreach ($this->ventasCredito as $venta) {
                $clienteNombre = '';
                if ($venta->planPago && $venta->planPago->cliente) {
                    $clienteNombre = $venta->planPago->cliente->nombre . ' ' . ($venta->planPago->cliente->apellido_p ?? '');
                }

                $filas[] = [
                    $venta->folio ?? 'S/F',
                    $venta->created_at ? $venta->created_at->format('d/m/Y H:i') : '',
                    $clienteNombre ?: '-',
                    'Anticipo',
                    $venta->metodo_pago ?? '',
                    round($venta->pago, 2),
                ];
                $fila++;
            }

            // Abonos
            foreach ($this->abonos as $abono) {
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

                $filas[] = [
                    $folioVenta,
                    $abono->fecha_pago ? $abono->fecha_pago->format('d/m/Y') : '',
                    $clienteAbono,
                    'Abono #' . $abono->numero_cuota,
                    $abono->metodo_pago ?? '',
                    round($abono->monto_pagado, 2),
                ];
                $fila++;
            }

            $subtotalCreditos = $this->totalAnticipos + $this->totalAbonos;
            $this->filasSubtotal[] = $fila;
            $filas[] = ['', '', '', '', 'Subtotal Creditos', round($subtotalCreditos, 2)];
            $fila++;
            $totalIngresos += $subtotalCreditos;

            $filas[] = [''];
            $fila++;
        }

        // Total general de ingresos
        $totalIngresos = round($totalIngresos, 2);
        $this->filaTotalIngresos = $fila;
        $filas[] = ['', '', '', '', 'TOTAL INGRESOS BRUTOS', round($totalIngresos, 2)];
        $fila++;

        $filas[] = [''];
        $fila++;

        // ── SECCION GASTOS ──
        $this->filaTituloGastos = $fila;
        $filas[] = ['INVERSIONES REALIZADAS (GASTOS)', '', '', '', '', ''];
        $fila++;

        $this->filaEncabezadoGastos = $fila;
        $filas[] = ['Concepto', 'Fecha', 'Categoria', 'Descripcion', 'Monto ($)', ''];
        $fila++;

        $totalGastos = 0;
        foreach ($this->gastos as $gasto) {
            $totalGastos += $gasto->monto;
            $filas[] = [
                $gasto->concepto ?? '',
                $gasto->fecha ?? '',
                $gasto->tipoGasto->name ?? 'Sin tipo',
                $gasto->descripcion ?? '',
                round($gasto->monto, 2),
                '',
            ];
            $fila++;
        }

        $this->filaSubtotalGastos = $fila;
        $filas[] = ['', '', '', '', 'Subtotal Inversiones', round($totalGastos, 2)];
        $fila++;

        $filas[] = [''];
        $fila++;

        // ── SECCION RESULTADO ──
        $this->filaResumenInicio = $fila;
        $filas[] = ['RESULTADO DEL PERIODO', '', '', '', '', ''];
        $fila++;

        $filas[] = ['', '', '', 'Concepto', '', 'Monto'];
        $fila++;

        $filas[] = ['', '', '', 'Ingresos por contado', '', round($this->totalContado, 2)];
        $fila++;

        $filas[] = ['', '', '', 'Ingresos por anticipos (credito)', '', round($this->totalAnticipos, 2)];
        $fila++;

        $filas[] = ['', '', '', 'Ingresos por abonos (credito)', '', round($this->totalAbonos, 2)];
        $fila++;

        $filas[] = ['', '', '', 'Total Ingresos Brutos', '', round($totalIngresos, 2)];
        $fila++;

        $filas[] = ['', '', '', '(-) Total Inversiones', '', round($totalGastos, 2)];
        $fila++;

        $saldoNeto = round($totalIngresos - $totalGastos, 2);
        $filas[] = ['', '', '', 'SALDO NETO', '', $saldoNeto];
        $fila++;

        $this->totalFilas = $fila - 1;

        return $filas;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 18,
            'C' => 20,
            'D' => 26,
            'E' => 22,
            'F' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge encabezado principal
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        $sheet->mergeCells('A3:F3');

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

        // Subsecciones (titulos de metodo, creditos, ingresos, gastos, resultado)
        foreach ($this->filasSubseccion as $f) {
            $sheet->mergeCells("A{$f}:F{$f}");
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
            $sheet->getStyle("A{$f}:F{$f}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '111827']],
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

        // Subtotales
        foreach ($this->filasSubtotal as $f) {
            $sheet->getStyle("A{$f}:F{$f}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                ],
            ]);
        }

        // Total ingresos brutos
        if ($this->filaTotalIngresos) {
            $sheet->getStyle("E{$this->filaTotalIngresos}:F{$this->filaTotalIngresos}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '059669']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                ],
            ]);
        }

        // Titulo gastos
        if ($this->filaTituloGastos) {
            $sheet->mergeCells("A{$this->filaTituloGastos}:F{$this->filaTituloGastos}");
            $sheet->getStyle("A{$this->filaTituloGastos}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
            ]);
        }

        // Encabezado gastos
        if ($this->filaEncabezadoGastos) {
            $sheet->getStyle("A{$this->filaEncabezadoGastos}:F{$this->filaEncabezadoGastos}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '3B82F6'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Subtotal gastos
        if ($this->filaSubtotalGastos) {
            $sheet->getStyle("E{$this->filaSubtotalGastos}:F{$this->filaSubtotalGastos}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '2563EB']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                ],
            ]);
        }

        // Resultado del periodo
        if ($this->filaResumenInicio) {
            $sheet->mergeCells("A{$this->filaResumenInicio}:F{$this->filaResumenInicio}");
            $sheet->getStyle("A{$this->filaResumenInicio}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7C3AED'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Saldo neto (ultima fila)
            $sheet->getStyle("D{$this->totalFilas}:F{$this->totalFilas}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
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

        // Formato numerico para columnas de montos
        $sheet->getStyle("E1:F{$this->totalFilas}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        return [];
    }
}