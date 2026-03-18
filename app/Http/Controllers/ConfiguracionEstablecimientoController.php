<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{ConfiguracionEstablecimiento, Establecimiento};
use Exception;
use Illuminate\Support\Facades\Storage;

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
                    'formato_hora'  => '12h',
                    'formato_fecha' => 'd/m/Y',
                    'num_cuenta' => null,
                ]
            );

            $establecimiento = Establecimiento::find($establecimiento_id);
            $logoUrl = null;
            if ($establecimiento && $establecimiento->logo) {
                $logoUrl = asset('storage/' . $establecimiento->logo);
            }

            return $this->Success([
                'configuracion'        => $config,
                'logo_url'             => $logoUrl,
                'nombre_establecimiento' => $establecimiento->nombre ?? 'Mi Negocio',
            ]);
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
                'formato_hora'  => 'nullable|in:12h,24h',
                'formato_fecha' => 'nullable|string|max:10',
                'num_cuenta'    => 'nullable|string|max:50',
            ]);

            $config = ConfiguracionEstablecimiento::updateOrCreate(
                ['establecimiento_id' => $establecimiento_id],
                [
                    'modo_iva'      => $request->modo_iva,
                    'imprimir_ticket_venta' => $request->imprimir_ticket_venta,
                    'impresora_ancho' => $request->impresora_ancho,
                    'impresora_alto'  => $request->impresora_alto,
                    'formato_hora'  => $request->formato_hora,
                    'formato_fecha'  => $request->formato_fecha,
                    'num_cuenta'  => $request->num_cuenta,
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

    // sube o actualiza el logo del establecimiento desde configuracion
    public function updateLogo(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $request->validate([
                'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $establecimiento = Establecimiento::findOrFail($establecimiento_id);

            // si ya tiene logo, eliminamos el archivo anterior
            if ($establecimiento->logo && Storage::disk('public')->exists($establecimiento->logo)) {
                Storage::disk('public')->delete($establecimiento->logo);
            }

            // generamos un nombre limpio del establecimiento para la carpeta
            $nombreLimpio = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $establecimiento->nombre));
            $extension    = $request->file('logo')->getClientOriginalExtension();
            $nombreArchivo = 'logo_' . $nombreLimpio . '.' . $extension;

            // guardamos en storage/app/public/logos/nombre_establecimiento
            $path = $request->file('logo')->storeAs('logos/' . $nombreLimpio, $nombreArchivo, 'public');
            $establecimiento->logo = $path;
            $establecimiento->save();

            return $this->Success([
                'message'  => 'Logo actualizado correctamente.',
                'logo_url' => asset('storage/' . $path),
            ]);
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al actualizar el logo.',
                'details' => $e->getMessage(),
            ]);
        }
    }
}