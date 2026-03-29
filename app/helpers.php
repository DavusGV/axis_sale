<?php


/**
 * Obtiene el logo del establecimiento en base64
 * Reutilizable en todos los controladores y exports
 * Retorna null si no hay logo
 */
function obtenerLogoEstablecimiento(): ?string
{
    $establecimiento_id = app('establishment_id');
    $logo = null;

    $userEstablecimiento = \App\Models\UserEstablecimiento::with('establecimiento')
        ->where('establecimiento_id', $establecimiento_id)
        ->first();

    if ($userEstablecimiento && $userEstablecimiento->establecimiento) {
        $establecimiento = $userEstablecimiento->establecimiento;

        if (!empty($establecimiento->logo)) {
            $logoPath = public_path('storage/' . $establecimiento->logo);
            if (file_exists($logoPath)) {
                $logo = base64_encode(file_get_contents($logoPath));
            }
        }
    }

    return $logo;
}

/**
 * Convierte el logo del establecimiento a base64 para tickets
 * Recibe el modelo Establecimiento con su propiedad logo
 */
function obtenerLogoBase64($establecimiento): ?string
{
    if (!$establecimiento || !$establecimiento->logo) {
        return null;
    }

    $logoPath = storage_path('app/public/' . $establecimiento->logo);

    if (!file_exists($logoPath)) {
        return null;
    }

    $contenido = file_get_contents($logoPath);
    $mime = mime_content_type($logoPath);

    return 'data:' . $mime . ';base64,' . base64_encode($contenido);
}

/**
 * Calcula el monto de IVA de un producto segun el modo de iva del establecimiento
 * Retorna el monto de iva calculado
 */
function calcularIvaProducto(float $subtotalNeto, float $ivaPorcentaje, string $modoIva): float
{
    if ($modoIva === 'sin_iva' || $ivaPorcentaje == 0) {
        return 0;
    }

    if ($modoIva === 'iva_incluido') {
        return $subtotalNeto - ($subtotalNeto / (1 + $ivaPorcentaje / 100));
    }

    // iva_adicional
    return $subtotalNeto * ($ivaPorcentaje / 100);
}