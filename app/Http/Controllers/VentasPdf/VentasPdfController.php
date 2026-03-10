<?php

namespace App\Http\Controllers\VentasPdf;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\HistorialCajas;
use App\Models\Ventas;
use Illuminate\Http\Request;
use Exception;
class VentasPdfController extends Controller
{
    public function __construct()
    {

    }

    public function showSaleForBox($historyId)
    {
        try {
            if (!$historyId) {
                return $this->Error('historialId es requerido', 422);
            }

            $history = HistorialCajas::find($historyId);
            if (!$history) {
                return $this->Error('Historial de caja no encontrado', 404);
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
           
            // Total productos vendidos
            $totalProductosVendidos = $ventas
                ->flatMap(function ($venta) {
                    return $venta->detalles;
                })
                ->sum('cantidad');

            $resumen = [
                'total_general' => $totalGeneral,
                'total_descuentos' => $totalDescuentos,
                'total_efectivo' => $totalEfectivo,
                'total_transferencia' => $totalTransferencia,
                'total_productos_vendidos' => $totalProductosVendidos
            ];

            $pdf = Pdf::loadView('pdf.ventas_box_history', [
                'history' => $history,
                'ventas' => $ventas,
                'resumen' => $resumen
            ]);

            return $pdf->stream('reporte_ventas_caja.pdf');

        } catch (Exception $e) {
            return $this->Error($e->getMessage(), 500);
        }
    }
}