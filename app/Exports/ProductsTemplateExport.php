<?php

namespace App\Exports;

use App\Exports\Sheets\ProductosSheet;
use App\Exports\Sheets\CategoriasSheet;
use App\Exports\Sheets\UnidadesSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductsTemplateExport implements WithMultipleSheets
{
    protected int $establecimientoId;

    public function __construct(int $establecimientoId)
    {
        $this->establecimientoId = $establecimientoId;
    }

    public function sheets(): array
    {
        // Orden importante: la hoja principal va primero
        // Las hojas catalogo se ocultan como veryHidden desde cada sheet
        return [
            new ProductosSheet($this->establecimientoId),
            new CategoriasSheet($this->establecimientoId),
            new UnidadesSheet($this->establecimientoId),
        ];
    }
}