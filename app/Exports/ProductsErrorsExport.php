<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Genera un archivo descargable con las filas que fallaron en la importacion
 * mas una columna adicional "_errores" para que el usuario sepa que corregir
 * y pueda volver a subir el archivo.
 */
class ProductsErrorsExport implements FromArray, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected array $filasFallidas;

    public function __construct(array $filasFallidas)
    {
        $this->filasFallidas = $filasFallidas;
    }

    public function title(): string
    {
        return 'Productos';
    }

    public function headings(): array
    {
        // Mismas columnas del template original mas la columna de errores al final
        return [
            'codigo',
            'clave',
            'nombre',
            'categoria',
            'unidad_medida',
            'stock',
            'precio_compra',
            'precio_venta',
            'es_servicio',
            'iva',
            'descripcion',
            '_errores',
        ];
    }

    public function array(): array
    {
        return $this->filasFallidas;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 18,
            'C' => 30,
            'D' => 25,
            'E' => 25,
            'F' => 10,
            'G' => 15,
            'H' => 15,
            'I' => 14,
            'J' => 10,
            'K' => 35,
            'L' => 60, // columna de errores mas ancha
        ];
    }

    public function styles($sheet)
    {
        // Encabezado en rojo para diferenciarlo del template normal
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DC2626'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '991B1B'],
                ],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(24);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');

                // Resaltar la columna de errores en cada fila con fondo amarillo claro
                $totalFilas = count($this->filasFallidas) + 1;
                if ($totalFilas > 1) {
                    $sheet->getStyle("L2:L{$totalFilas}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FEF3C7'],
                        ],
                        'alignment' => [
                            'wrapText' => true,
                            'vertical' => Alignment::VERTICAL_TOP,
                        ],
                    ]);
                }
            },
        ];
    }
}