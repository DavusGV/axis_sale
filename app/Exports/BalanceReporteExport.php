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
    protected $ventas;
    protected $abonos;
    protected $gastos;
    protected $fechaInicio;
    protected $fechaFin;
    protected $establecimiento;

    // Control de filas para aplicar estilos despues
    protected $filaTituloIngresos;
    protected $filaEncabezadoIngresos;
    protected $filaSubtotalIngresos;
    protected $filaTituloGastos;
    protected $filaEncabezadoGastos;
    protected $filaSubtotalGastos;
    protected $filaTituloResultado;
    protected $filaIngresosBrutos;
    protected $filaTotalInversiones;
    protected $filaSaldoNeto;
    protected $filaIndicador;
    protected $totalFilas;

    /**
     * Constructor: recibe los datos ya consultados desde el controlador
     *
     * @param \Illuminate\Support\Collection $ventas
     * @param \Illuminate\Support\Collection $abonos
     * @param \Illuminate\Support\Collection $gastos
     * @param string $fechaInicio
     * @param string $fechaFin
     * @param string $establecimiento
     */
    public function __construct($ventas, $abonos, $gastos, $fechaInicio, $fechaFin, $establecimiento = '')
    {
        $this->ventas = $ventas;
        $this->abonos = $abonos;
        $this->gastos = $gastos;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->establecimiento = $establecimiento;
    }

    /**
     * Nombre de la hoja del excel
     */
    public function title(): string
    {
        return 'Balance General';
    }

    /**
     * Arma todas las filas del reporte en un solo array
     * Cada sub-array es una fila del excel
     */
    public function array(): array
    {
        $filas = [];
        $fila = 1;

        // ── Encabezado principal ──
        $filas[] = ['Reporte de Balance General'];                          // fila 1
        $filas[] = [$this->establecimiento];                                // fila 2
        $filas[] = ['Periodo: ' . $this->fechaInicio . ' al ' . $this->fechaFin]; // fila 3
        $filas[] = [''];                                                    // fila 4 vacia
        $fila = 5;

        // ── SECCION INGRESOS GENERADOS ──
        $this->filaTituloIngresos = $fila;
        $filas[] = ['INGRESOS GENERADOS'];
        $fila++;

        // Encabezados de tabla de ingresos
        $this->filaEncabezadoIngresos = $fila;
        $filas[] = ['Folio', 'Fecha', 'Metodo de Pago', 'Tipo', 'Monto ($)'];
        $fila++;

        // Filas de ventas
        $totalIngresos = 0;
        foreach ($this->ventas as $venta) {
            $esCredito = $venta->planPago !== null;
            $tipo = $esCredito ? 'Credito (Anticipo)' : 'Contado';
            // Si es credito usamos el pago (anticipo), si es contado usamos el total
            $monto = $esCredito ? $venta->pago : $venta->total;
            $totalIngresos += $monto;

            $filas[] = [
                $venta->folio ?? 'S/F',
                $venta->created_at ? $venta->created_at->format('d/m/Y H:i') : '',
                $venta->metodo_pago ?? '',
                $tipo,
                round($monto, 2),
            ];
            $fila++;
        }

        // Filas de abonos a credito
        foreach ($this->abonos as $abono) {
            $totalIngresos += $abono->monto_pagado;

            $filas[] = [
                'Abono a credito',
                $abono->fecha_pago ?? '',
                $abono->metodo_pago ?? '',
                'Abono',
                round($abono->monto_pagado, 2),
            ];
            $fila++;
        }

        // Subtotal de ingresos
        $this->filaSubtotalIngresos = $fila;
        $filas[] = ['', '', '', 'Subtotal Ingresos Brutos', round($totalIngresos, 2)];
        $fila++;

        // Fila vacia de separacion
        $filas[] = [''];
        $fila++;

        // ── SECCION INVERSIONES REALIZADAS (GASTOS) ──
        $this->filaTituloGastos = $fila;
        $filas[] = ['INVERSIONES REALIZADAS (GASTOS)'];
        $fila++;

        // Encabezados de tabla de gastos
        $this->filaEncabezadoGastos = $fila;
        $filas[] = ['Concepto', 'Fecha', 'Tipo de Gasto', 'Descripcion', 'Monto ($)'];
        $fila++;

        // Filas de gastos
        $totalGastos = 0;
        foreach ($this->gastos as $gasto) {
            $totalGastos += $gasto->monto;

            $filas[] = [
                $gasto->concepto ?? '',
                $gasto->fecha ?? '',
                $gasto->tipoGasto->name ?? 'Sin tipo',
                $gasto->descripcion ?? '',
                round($gasto->monto, 2),
            ];
            $fila++;
        }

        // Subtotal de gastos
        $this->filaSubtotalGastos = $fila;
        $filas[] = ['', '', '', 'Subtotal Inversiones', round($totalGastos, 2)];
        $fila++;

        // Fila vacia de separacion
        $filas[] = [''];
        $fila++;

        // ── SECCION RESULTADO DEL PERIODO ──
        $this->filaTituloResultado = $fila;
        $filas[] = ['RESULTADO DEL PERIODO'];
        $fila++;

        $saldoNeto = round($totalIngresos - $totalGastos, 2);

        $this->filaIngresosBrutos = $fila;
        $filas[] = ['', '', '', 'Ingresos Brutos', round($totalIngresos, 2)];
        $fila++;

        $this->filaTotalInversiones = $fila;
        $filas[] = ['', '', '', 'Total Inversiones', round($totalGastos, 2)];
        $fila++;

        $this->filaSaldoNeto = $fila;
        $filas[] = ['', '', '', 'Saldo Neto', $saldoNeto];
        $fila++;

        // Indicador de resultado
        $this->filaIndicador = $fila;
        $indicador = $saldoNeto >= 0
            ? 'Saldo favorable'
            : 'Mayor inversion que ingreso';
        $filas[] = ['', '', '', $indicador, ''];
        $fila++;

        $this->totalFilas = $fila - 1;

        return $filas;
    }

    /**
     * Anchos de columna para que el reporte sea legible
     */
    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 20,
            'C' => 20,
            'D' => 28,
            'E' => 18,
        ];
    }

    /**
     * Estilos visuales: negritas, colores, bordes, merge de celdas
     */
    public function styles(Worksheet $sheet)
    {
        // ── Merge de celdas para titulos principales ──
        $sheet->mergeCells('A1:E1');
        $sheet->mergeCells('A2:E2');
        $sheet->mergeCells('A3:E3');
        $sheet->mergeCells("A{$this->filaTituloIngresos}:E{$this->filaTituloIngresos}");
        $sheet->mergeCells("A{$this->filaTituloGastos}:E{$this->filaTituloGastos}");
        $sheet->mergeCells("A{$this->filaTituloResultado}:E{$this->filaTituloResultado}");

        // ── Titulo principal ──
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '1F2937'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Nombre del establecimiento
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '4B5563'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Periodo
        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'size' => 11,
                'color' => ['rgb' => '6B7280'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // ── Titulo seccion INGRESOS ──
        $sheet->getStyle("A{$this->filaTituloIngresos}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '059669'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Encabezados tabla ingresos
        $sheet->getStyle("A{$this->filaEncabezadoIngresos}:E{$this->filaEncabezadoIngresos}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '10B981'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // Filas de datos de ingresos (entre encabezado y subtotal)
        $inicioDataIng = $this->filaEncabezadoIngresos + 1;
        $finDataIng = $this->filaSubtotalIngresos - 1;
        if ($finDataIng >= $inicioDataIng) {
            $sheet->getStyle("A{$inicioDataIng}:E{$finDataIng}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
                ],
                'font' => ['size' => 10],
            ]);

            // Alternar color de filas para mejor lectura
            for ($i = $inicioDataIng; $i <= $finDataIng; $i++) {
                if (($i - $inicioDataIng) % 2 === 0) {
                    $sheet->getStyle("A{$i}:E{$i}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'ECFDF5'],
                        ],
                    ]);
                }
            }
        }

        // Subtotal ingresos
        $sheet->getStyle("D{$this->filaSubtotalIngresos}:E{$this->filaSubtotalIngresos}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '059669']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);

        // ── Titulo seccion GASTOS ──
        $sheet->getStyle("A{$this->filaTituloGastos}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Encabezados tabla gastos
        $sheet->getStyle("A{$this->filaEncabezadoGastos}:E{$this->filaEncabezadoGastos}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // Filas de datos de gastos
        $inicioDataGas = $this->filaEncabezadoGastos + 1;
        $finDataGas = $this->filaSubtotalGastos - 1;
        if ($finDataGas >= $inicioDataGas) {
            $sheet->getStyle("A{$inicioDataGas}:E{$finDataGas}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
                ],
                'font' => ['size' => 10],
            ]);

            for ($i = $inicioDataGas; $i <= $finDataGas; $i++) {
                if (($i - $inicioDataGas) % 2 === 0) {
                    $sheet->getStyle("A{$i}:E{$i}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'EFF6FF'],
                        ],
                    ]);
                }
            }
        }

        // Subtotal gastos
        $sheet->getStyle("D{$this->filaSubtotalGastos}:E{$this->filaSubtotalGastos}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '2563EB']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);

        // ── Titulo seccion RESULTADO ──
        $sheet->getStyle("A{$this->filaTituloResultado}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '7C3AED'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Fila ingresos brutos
        $sheet->getStyle("D{$this->filaIngresosBrutos}:E{$this->filaIngresosBrutos}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
        ]);

        // Fila total inversiones
        $sheet->getStyle("D{$this->filaTotalInversiones}:E{$this->filaTotalInversiones}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
        ]);

        // Fila saldo neto
        $sheet->getStyle("D{$this->filaSaldoNeto}:E{$this->filaSaldoNeto}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
        ]);

        // Indicador de resultado (saldo favorable o mayor inversion)
        $sheet->getStyle("D{$this->filaIndicador}")->applyFromArray([
            'font' => ['bold' => true, 'italic' => true, 'size' => 11],
        ]);

        // Formato numerico para columna E (montos)
        $sheet->getStyle("E1:E{$this->totalFilas}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        return [];
    }
}