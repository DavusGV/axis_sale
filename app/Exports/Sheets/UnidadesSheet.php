<?php

namespace App\Exports\Sheets;

use App\Models\UnidadMedida;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;

class UnidadesSheet implements WithTitle, WithHeadings, FromCollection, WithEvents
{
    protected int $establecimientoId;

    public function __construct(int $establecimientoId)
    {
        $this->establecimientoId = $establecimientoId;
    }

    public function title(): string
    {
        return 'Unidades';
    }

    public function headings(): array
    {
        return ['unidad'];
    }

    public function collection(): Collection
    {
        // Mostramos "unidad (abreviatura)" para que sea claro al usuario
        // pero internamente el backend resolvera por la combinacion completa
        return UnidadMedida::where('establecimiento_id', $this->establecimientoId)
            ->orderBy('unidad')
            ->get(['unidad', 'abreviatura'])
            ->map(fn ($u) => ['unidad' => $u->unidad . ' (' . $u->abreviatura . ')']);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Hoja oculta completamente
                $event->sheet->getDelegate()->setSheetState('veryHidden');
            },
        ];
    }
}