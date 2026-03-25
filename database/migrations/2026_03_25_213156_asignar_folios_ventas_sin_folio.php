<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Ventas;
use App\Models\Establecimiento;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        $ventasSinFolio = Ventas::whereNull('folio')
            ->orWhere('folio', '')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($ventasSinFolio as $venta) {
            $establecimiento = Establecimiento::find($venta->establecimiento_id);
            $nombre = $establecimiento->nombre ?? 'XX';
            $iniciales = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nombre), 0, 2));
            $anio = Carbon::parse($venta->created_at)->format('y');

            // Contamos cuantas ventas YA tienen folio antes de esta
            $consecutivo = Ventas::where('establecimiento_id', $venta->establecimiento_id)
                ->whereYear('created_at', Carbon::parse($venta->created_at)->year)
                ->where('id', '<', $venta->id)
                ->count() + 1;

            $folio = 'VTA-' . $iniciales . $anio . str_pad($consecutivo, 3, '0', STR_PAD_LEFT);

            $venta->folio = $folio;
            $venta->save();
        }
    }

    public function down(): void
    {
        // No revertimos folios asignados
    }
};