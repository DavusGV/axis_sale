<?php

namespace App\Http\Controllers\VentasPdf;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\HistorialCajas;
use App\Models\Ventas;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;



class SaleHistoryBoxPdfController extends Controller
{
    public function showSaleForBox($historyId)
    {
        try {

            if (!$historyId) {
                return $this->BadRequest('historialId es requerido', 422);
            }

            $history = HistorialCajas::find($historyId);

            if (!$history) {
                return $this->BadRequest('Historial de caja no encontrado', 404);
            }

            $ventas = Ventas::with([
                    'detalles.producto',
                    'usuario'
                ])
                ->where('historial_caja_id', $historyId)
                ->orderBy('created_at', 'asc')
                ->get();

            $totalGeneral = $ventas->sum('total');
            $totalDescuentos = $ventas->sum('descuento');

            $totalesPorMetodo = $ventas->groupBy('metodo_pago')->map(function ($group) {
                return [
                    'total' => $group->sum('total'),
                    'descuento' => $group->sum('descuento')
                ];
            });

            $totalEfectivo = $totalesPorMetodo['efectivo']['total'] ?? 0;
            $totalTransferencia = $totalesPorMetodo['transferencia']['total'] ?? 0;

            $totalProductosVendidos = $ventas
                ->flatMap(function ($venta) {
                    return $venta->detalles;
                })
                ->sum('cantidad');

            $resumen = [
                'total_general' => $totalGeneral,
                'total_efectivo' => $totalEfectivo,
                'total_transferencia' => $totalTransferencia,
                'total_descuentos' => $totalDescuentos,
                'total_productos_vendidos' => $totalProductosVendidos
            ];

            $pdf = Pdf::loadView('pdf.cierre_caja', [
                'historial' => $history,
                'ventas' => $ventas,
                'resumen' => $resumen
            ]);
            return $pdf->stream('reporte_caja_'.$historyId.'.pdf');

        } catch (Exception $e) {
            return $this->InternalError($e->getMessage(), 500);
        }
    }
}