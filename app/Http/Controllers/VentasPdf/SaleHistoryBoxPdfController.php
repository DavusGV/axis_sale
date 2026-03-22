<?php

namespace App\Http\Controllers\VentasPdf;

use App\Http\Controllers\Controller;
use App\Models\HistorialCajas;
use App\Models\User;
use App\Models\UserEstablecimiento;
use App\Models\Ventas;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class SaleHistoryBoxPdfController extends Controller
{
    public function showSaleForBox($historyId)
    {
        try {

            // obtener el historial de caja con su usuario responsable
            $history = HistorialCajas::with('usuario')->find($historyId);

            if (!$history) {
                return response()->json([
                    'message' => 'Historial de caja no encontrado'
                ], 404);
            }
            //Obtenemos las ventas relacionadas al historial de caja
            $ventas = Ventas::with([
                'detalles.producto',
                'usuario'
            ])->where('historial_caja_id', $historyId)
            ->orderBy('created_at', 'asc')->get();

            $resumen = $this->calcularResumenVentas($ventas); //Calcular resumen de ventas
            $empresa = $this->obtenerDatosEmpresa($history->usuario_id); //Datos de empresa
            // Generamos la vista PDF
            $pdf = Pdf::loadView('pdf.cierre_caja', [
                'historial' => $history,
                'ventas' => $ventas,
                'resumen' => $resumen,
                'empresa' => $empresa
            ]);
            return $pdf->stream('reporte_caja_' . $historyId . '.pdf');

        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function calcularResumenVentas($ventas)
    {
        $totalGeneral = 0;
        $totalEfectivo = 0;
        $totalTransferencia = 0;
        $totalCredito = 0;
        $totalProductos = 0;
        $totalDescuentos = 0;

        foreach ($ventas as $venta) {

            $totalVenta = 0;

            foreach ($venta->detalles as $detalle) {

                $subtotal = ($detalle->cantidad * $detalle->precio) - $detalle->descuento_aplicado;
                $totalVenta += $subtotal;
                //acumuladores globales
                $totalProductos += $detalle->cantidad;
                $totalDescuentos += $detalle->descuento_aplicado;
            }

            //total general de todas las ventas
            $totalGeneral += $totalVenta;

            //clasificamos por los metdos de pago
            switch ($venta->metodo_pago) {
                case 'efectivo':
                    $totalEfectivo += $totalVenta;
                    break;
                case 'transferencia':
                    $totalTransferencia += $totalVenta;
                    break;
                case 'credito':
                    $totalCredito += $totalVenta;
                    break;
            }
        }

        return [
            'total_ventas' => $ventas->count(),
            'total_general' => $totalGeneral,
            'total_efectivo' => $totalEfectivo,
            'total_transferencia' => $totalTransferencia,
            'total_credito' => $totalCredito,
            'total_descuentos' => $totalDescuentos,
            'total_productos' => $totalProductos
        ];
    }

   private function obtenerDatosEmpresa($userId)
    {
        $userEstablecimiento = UserEstablecimiento::with('establecimiento')
            ->where('user_id', $userId)
            ->first();

        
        //inicializamos el logo por default
        $logo = null;

        if ($userEstablecimiento && $userEstablecimiento->establecimiento) {

            $establecimiento = $userEstablecimiento->establecimiento;
            $nombre = $establecimiento->nombre;
            $direccion = $establecimiento->direccion;
            $telefono = $establecimiento->telefono;

            //usamos el logo del establecimiento si existe, sino se usará el logo por default
            if (!empty($establecimiento->logo)) {
                $logoPath = public_path('storage/' . $establecimiento->logo);

                if (file_exists($logoPath)) {
                    $logo = base64_encode(file_get_contents($logoPath));
                }
            }
        }

        //usamos logo por deafult si no se encuentra el del establecimiento
        if (!$logo) {
            $defaultLogoPath = public_path('images/cart.png');
            if (file_exists($defaultLogoPath)) {
                $logo = base64_encode(file_get_contents($defaultLogoPath));
            }
        }

        return [
            'nombre' => $nombre,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'logo' => $logo
        ];
    }
}