<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Collection;

/**
 * Lee el archivo subido por el usuario.
 * Solo lee la hoja "Productos" (las hojas catalogo se ignoran).
 * Acumula todas las filas leidas y las expone via getRows() para
 * que el servicio las procese centralizadamente.
 */
class ProductsImport implements ToCollection, WithHeadingRow, WithChunkReading, WithCalculatedFormulas, SkipsEmptyRows
{
    /**
     * Filas acumuladas con su numero de fila real en el Excel.
     * Cada elemento: ['fila' => N, 'data' => [...]]
     */
    protected array $rows = [];

    /**
     * Contador interno de filas leidas para calcular el numero real
     * de fila considerando que la fila 1 es el encabezado.
     */
    protected int $offset = 0;

    public function collection(Collection $collection)
    {
        foreach ($collection as $index => $row) {
            $this->offset++;
            $this->rows[] = [
                // +1 por el encabezado, asi el numero coincide con lo que ve el usuario en Excel
                'fila' => $this->offset + 1,
                'data' => $row->toArray(),
            ];
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function getRows(): array
    {
        return $this->rows;
    }
}