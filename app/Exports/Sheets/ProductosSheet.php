<?php

namespace App\Exports\Sheets;

use App\Models\Category;
use App\Models\UnidadMedida;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductosSheet implements WithTitle, WithHeadings, WithStyles, WithColumnWidths, WithEvents, FromArray
{
    protected int $establecimientoId;

    // total de filas a las que aplicaremos la validacion de dropdown
    // si el usuario necesita mas filas puede arrastrar el formato hacia abajo
    protected int $filasValidacion = 500;

    public function __construct(int $establecimientoId)
    {
        $this->establecimientoId = $establecimientoId;
    }

    public function title(): string
    {
        return 'Productos';
    }

    public function headings(): array
    {
        // Orden definido: codigo, clave, nombre, categoria, unidad_medida,
        // stock, precio_compra, precio_venta, es_servicio, iva, descripcion
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
        ];
    }

    // Devolvemos un array vacio: solo queremos el template con encabezados
    public function array(): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // codigo
            'B' => 18, // clave
            'C' => 30, // nombre
            'D' => 25, // categoria
            'E' => 25, // unidad_medida
            'F' => 10, // stock
            'G' => 15, // precio_compra
            'H' => 15, // precio_venta
            'I' => 14, // es_servicio
            'J' => 10, // iva
            'K' => 35, // descripcion
        ];
    }

    public function styles($sheet)
    {
        // Estilo del encabezado: fondo azul, texto blanco, negrita y centrado
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '1E40AF'],
                ],
            ],
        ]);

        // Altura del encabezado
        $sheet->getRowDimension(1)->setRowHeight(24);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Congelar la primera fila para que el encabezado siempre se vea
                $sheet->freezePane('A2');

                // Cantidad de categorias y unidades existentes para construir
                // las referencias a las hojas catalogo
                $totalCategorias = Category::where('establecimiento_id', $this->establecimientoId)->count();
                $totalUnidades   = UnidadMedida::where('establecimiento_id', $this->establecimientoId)->count();

                // Aplicamos validaciones de la fila 2 hasta filasValidacion + 1
                $filaInicio = 2;
                $filaFin    = $this->filasValidacion + 1;

                // Dropdown de categoria (columna D) referenciando hoja Categorias
                if ($totalCategorias > 0) {
                    $rangoCategorias = "Categorias!\$A\$2:\$A\$" . ($totalCategorias + 1);
                    $this->aplicarDropdownDesdeRango($sheet, 'D', $filaInicio, $filaFin, $rangoCategorias, 'Categoria invalida', 'Selecciona una categoria del listado.');
                }

                // Dropdown de unidad_medida (columna E) referenciando hoja Unidades
                if ($totalUnidades > 0) {
                    $rangoUnidades = "Unidades!\$A\$2:\$A\$" . ($totalUnidades + 1);
                    $this->aplicarDropdownDesdeRango($sheet, 'E', $filaInicio, $filaFin, $rangoUnidades, 'Unidad invalida', 'Selecciona una unidad de medida del listado.');
                }

                // Dropdown SI/NO para es_servicio (columna I)
                $this->aplicarDropdownLista($sheet, 'I', $filaInicio, $filaFin, '"SI,NO"', 'Valor invalido', 'Solo se permite SI o NO.');

                // Formato numerico para columnas de precio
                $sheet->getStyle("G{$filaInicio}:H{$filaFin}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Formato entero para stock
                $sheet->getStyle("F{$filaInicio}:F{$filaFin}")->getNumberFormat()->setFormatCode('0');

                // Formato porcentaje (visual, el usuario escribe 16 no 0.16)
                $sheet->getStyle("J{$filaInicio}:J{$filaFin}")->getNumberFormat()->setFormatCode('0.00');
            },
        ];
    }

    /**
     * Aplica una validacion de tipo dropdown referenciando un rango de otra hoja
     */
    private function aplicarDropdownDesdeRango($sheet, string $columna, int $inicio, int $fin, string $formula, string $tituloError, string $mensajeError): void
    {
        for ($fila = $inicio; $fila <= $fin; $fila++) {
            $validation = $sheet->getCell("{$columna}{$fila}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle($tituloError);
            $validation->setError($mensajeError);
            $validation->setPromptTitle('Seleccion requerida');
            $validation->setPrompt($mensajeError);
            $validation->setFormula1($formula);
        }
    }

    /**
     * Aplica una validacion de tipo dropdown con lista en linea (SI/NO)
     */
    private function aplicarDropdownLista($sheet, string $columna, int $inicio, int $fin, string $lista, string $tituloError, string $mensajeError): void
    {
        for ($fila = $inicio; $fila <= $fin; $fila++) {
            $validation = $sheet->getCell("{$columna}{$fila}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle($tituloError);
            $validation->setError($mensajeError);
            $validation->setFormula1($lista);
        }
    }
}