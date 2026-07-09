<?php

namespace App\Exports\Sheets;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;

class CategoriasSheet implements WithTitle, WithHeadings, FromCollection, WithEvents
{
    protected int $establecimientoId;

    public function __construct(int $establecimientoId)
    {
        $this->establecimientoId = $establecimientoId;
    }

    public function title(): string
    {
        return 'Categorias';
    }

    public function headings(): array
    {
        return ['nombre'];
    }

    public function collection(): Collection
    {
        // Solo el nombre, ordenado, para que el dropdown sea legible
        return Category::where('establecimiento_id', $this->establecimientoId)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->map(fn ($nombre) => ['nombre' => $nombre]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Ocultamos la hoja como veryHidden para que no aparezca
                // ni siquiera en el menu de hojas ocultas de Excel
                $event->sheet->getDelegate()->setSheetState('veryHidden');
            },
        ];
    }
}