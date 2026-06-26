<?php

namespace App\Http\Controllers\VentasPdf;

use App\Http\Controllers\Controller;
use App\Models\HistorialCajas;
use App\Models\PagoPlan;
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

            // ventas del periodo de esta caja con su plan de pago para saber si es credito
            $ventas = Ventas::with([
                'detalles.producto',
                'usuario',
                'planPago',
            ])->where('historial_caja_id', $historyId)
              ->where('status', '!=', 'cancelada')
              ->orderBy('created_at', 'asc')
              ->get();

            // abonos registrados en caja
            // cada abono apunta al historial_caja_id donde entro el dinero
            $abonos = PagoPlan::with([
                'plan.venta',
            ])->where('historial_caja_id', $historyId)
                ->orderBy('created_at', 'asc')
                ->get();

            // Generamos la vista PDF
            $resumen = $this->calcularResumen($ventas, $abonos); //Calcular resumen de ventas
            $empresa = $this->obtenerDatosEmpresa(); //Datos de empresa

            $pdf = Pdf::loadView('pdf.cierre_caja', [
                'historial' => $history,
                'ventas'    => $ventas,
                'abonos'    => $abonos,
                'resumen'   => $resumen,
                'empresa'   => $empresa,
            ]);

            return $pdf->stream('reporte_caja_' . $historyId . '.pdf');

        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula los ingresos reales de la caja
     * Los abonos se agrupan por separado por metodo de pago
     */
    private function calcularResumen($ventas, $abonos): array
    {
        $totalVentasDirectas = 0;
        $totalAnticipos      = 0;
        $totalDescuentos     = 0;
        $totalProductos      = 0;

        // acumula ingreso real por metodo de pago
        $ventasPorMetodo = [];

        foreach ($ventas as $venta) {

            $esCredito = $venta->planPago !== null;

            foreach ($venta->detalles as $detalle) {
                $totalDescuentos += $detalle->descuento_aplicado;
                $totalProductos  += $detalle->cantidad;
            }

            // normalizamos el metodo para agrupar sin distincion de mayusculas
            $metodo = ucfirst(strtolower($venta->metodo_pago ?? 'efectivo'));

            if ($esCredito) {
                // solo el anticipo entro en caja
                $anticipo        = (float) ($venta->planPago->anticipo ?? 0);
                $totalAnticipos += $anticipo;

                $ventasPorMetodo[$metodo] = ($ventasPorMetodo[$metodo] ?? 0) + $anticipo;
            } else {
                $monto                = (float) $venta->total;
                $totalVentasDirectas += $monto;

                $ventasPorMetodo[$metodo] = ($ventasPorMetodo[$metodo] ?? 0) + $monto;
            }
        }

        // acumula abonos por metodo de pago
        $totalAbonos     = 0;
        $abonosPorMetodo = [];

        foreach ($abonos as $abono) {
            $monto        = (float) $abono->monto_pagado;
            $totalAbonos += $monto;

            $metodo = ucfirst(strtolower($abono->metodo_pago ?? 'efectivo'));
            $abonosPorMetodo[$metodo] = ($abonosPorMetodo[$metodo] ?? 0) + $monto;
        }

        return [
            // conteos
            'num_ventas'            => $ventas->count(),
            'num_ventas_directas'   => $ventas->where('planPago', null)->count(),
            'num_ventas_credito'    => $ventas->whereNotNull('planPago')->count(),
            'num_abonos'            => $abonos->count(),
            'total_productos'       => $totalProductos,

            // totales por concepto
            'total_ventas_directas' => $totalVentasDirectas,
            'total_anticipos'       => $totalAnticipos,

            // agrupados por metodo de pago para el resumen
            'ventas_por_metodo'     => collect($ventasPorMetodo),
            'abonos_por_metodo'     => collect($abonosPorMetodo),

            // totales de abonos
            'total_abonos'          => $totalAbonos,

            // descuentos solo de referencia
            'total_descuentos'      => $totalDescuentos,

            // ingreso real total que entro en caja
            'total_ingreso_real'    => $totalVentasDirectas + $totalAnticipos + $totalAbonos,
        ];
    }

    private function obtenerDatosEmpresa(): array
    {
        $establecimiento_id  = app('establishment_id');
        $userEstablecimiento = UserEstablecimiento::with('establecimiento')
            ->where('establecimiento_id', $establecimiento_id)
            ->first();

        $nombre = '';
        $direccion = '';
        $telefono = '';

        if ($userEstablecimiento && $userEstablecimiento->establecimiento) {
            $establecimiento = $userEstablecimiento->establecimiento;
            $nombre = $establecimiento->nombre;
            $direccion = $establecimiento->direccion;
            $telefono = $establecimiento->telefono;
        }

        return [
            'nombre' => $nombre,
            'direccion' => $direccion,
            'telefono' => $telefono,
            // reutilizamos el helper global para obtener el logo en base64
            'logo' => obtenerLogoEstablecimiento(),
        ];
    }
}