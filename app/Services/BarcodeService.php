<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeService
{
    private BarcodeGeneratorPNG $generator;

    // cache de imagenes por codigo: el mismo barcode no se regenera N veces
    private array $cache = [];

    public function __construct()
    {
        $this->generator = new BarcodeGeneratorPNG();
    }

    /**
     * Genera un Code 128 como data URI PNG base64 listo para incrustar
     * en el Blade que consume DOMPDF. Reutiliza el resultado si el
     * mismo codigo ya fue generado en esta ejecucion.
     */
    public function generar(string $codigo, int $factorAncho = 2, int $alto = 40): string
    {
        $clave = $codigo . ':' . $factorAncho . ':' . $alto;

        if (isset($this->cache[$clave])) {
            return $this->cache[$clave];
        }

        $png = $this->generator->getBarcode(
            $codigo,
            $this->generator::TYPE_CODE_128,
            $factorAncho,
            $alto
        );

        return $this->cache[$clave] = 'data:image/png;base64,' . base64_encode($png);
    }
}