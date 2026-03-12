<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfiguracionEstablecimiento;
use Exception;

class ConfiguracionEstablecimientoController extends Controller
{
    // obtiene la configuracion del establecimiento activo
    public function show()
    {
        try {
            $establecimiento_id = app('establishment_id');

            // si no existe configuracion, devolvemos los valores por defecto
            $config = ConfiguracionEstablecimiento::firstOrCreate(
                ['establecimiento_id' => $establecimiento_id],
                [
                    'modo_iva'       => 'sin_iva',
                    'imprimir_ticket_venta'  => true,
                    'impresora_ancho' => 80,
                    'impresora_alto'  => 200,
                ]
            );

            return $this->Success(['configuracion' => $config]);
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al obtener la configuracion.',
                'details' => $e->getMessage()
            ]);
        }
    }

    // guarda o actualiza la configuracion del establecimiento activo
    public function update(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $request->validate([
                'modo_iva'      => 'required|in:sin_iva,iva_incluido,iva_adicional',
                'imprimir_ticket_venta' => 'required|boolean',
                'impresora_ancho' => 'required|integer|in:58,80',
                'impresora_alto'  => 'required|integer|min:100|max:500',
            ]);

            $config = ConfiguracionEstablecimiento::updateOrCreate(
                ['establecimiento_id' => $establecimiento_id],
                [
                    'modo_iva'      => $request->modo_iva,
                    'imprimir_ticket_venta' => $request->imprimir_ticket_venta,
                    'impresora_ancho' => $request->impresora_ancho,
                    'impresora_alto'  => $request->impresora_alto,
                ]
            );

            return $this->Success(['configuracion' => $config]);
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al guardar la configuracion.',
                'details' => $e->getMessage()
            ]);
        }
    }
}