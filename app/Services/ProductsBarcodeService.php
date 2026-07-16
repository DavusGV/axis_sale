<?php

namespace App\Services;

use App\Models\Products;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Exception;

class ProductsBarcodeService
{
    public function __construct(private BarcodeService $barcodeService) {}

    /**
     * Resuelve los productos segun el modo, siempre acotado al establecimiento
     * activo. Solo productos con codigo no vacio, ya que ese valor es lo que
     * se codifica en el barcode.
     */
    private function resolverProductos(array $data): Collection
    {
        $establecimientoId = app('establishment_id');

        $query = Products::where('establecimiento_id', $establecimientoId)
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '');

        if ($data['modo'] === 'especifico') {
            // si el id no pertenece al establecimiento, no devuelve nada
            $query->where('id', $data['producto_id']);
        }

        // en modo segun stock los servicios no aplican (no manejan stock)
        if ($data['tipo_cantidad'] === 'stock') {
            $query->where('es_servicio', false);
        }

        return $query->orderBy('nombre')->get();
    }

    /**
     * Cuantas etiquetas corresponden a un producto segun el tipo de cantidad.
     */
    private function cantidadPara(Products $producto, array $data): int
    {
        return match ($data['tipo_cantidad']) {
            'unica'         => 1,
            'personalizada' => (int) $data['cantidad'],
            'stock'         => max(0, (int) $producto->stock),
            default         => 0,
        };
    }

    /**
     * Lista plana de etiquetas (una entrada por etiqueta) SIN generar el
     * barcode todavia. Asi el preview puede contar barato.
     */
    private function construirEtiquetas(array $data): array
    {
        $productos     = $this->resolverProductos($data);
        $incluirPrecio = (bool) ($data['incluir_precio'] ?? false);

        $etiquetas = [];
        foreach ($productos as $producto) {
            $cantidad = $this->cantidadPara($producto, $data);
            for ($i = 0; $i < $cantidad; $i++) {
                $etiquetas[] = [
                    'nombre' => $producto->nombre,
                    'codigo' => $producto->codigo,
                    'precio' => $incluirPrecio ? $producto->precio_venta : null,
                ];
            }
        }

        return $etiquetas;
    }

    private function porPagina(): int
    {
        return (int) config('barcodes.columnas') * (int) config('barcodes.filas');
    }

    // agrega la imagen real del barcode a un set de etiquetas
    private function adjuntarBarcodes(array $etiquetas): array
    {
        foreach ($etiquetas as &$etiqueta) {
            $etiqueta['barcode'] = $this->barcodeService->generar($etiqueta['codigo']);
        }
        unset($etiqueta);

        return $etiquetas;
    }

    /**
     * Preview liviano: totales + solo la primera pagina con barcodes reales,
     * para no mandar miles de imagenes al navegador.
     */
    public function preview(array $data): array
    {
        $etiquetas = $this->construirEtiquetas($data);
        $total     = count($etiquetas);
        $porPagina = $this->porPagina();
        $paginas   = $porPagina > 0 ? (int) ceil($total / $porPagina) : 0;

        $primeraPagina = $this->adjuntarBarcodes(array_slice($etiquetas, 0, $porPagina));

        return [
            'total_etiquetas' => $total,
            'total_paginas'   => $paginas,
            'por_pagina'      => $porPagina,
            'columnas'        => (int) config('barcodes.columnas'),
            'filas'           => (int) config('barcodes.filas'),
            'primera_pagina'  => $primeraPagina,
        ];
    }

    // arma la estructura paginada que consume el Blade
    private function paginar(array $data): array
    {
        $etiquetas = $this->adjuntarBarcodes($this->construirEtiquetas($data));

        if (empty($etiquetas)) {
            throw new Exception('No hay productos con código para generar etiquetas.');
        }

        return array_chunk($etiquetas, $this->porPagina());
    }

    /**
     * Genera el PDF final. Reutiliza el cache del BarcodeService.
     */
    public function generar(array $data): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.barcodes', [
            'paginas' => $this->paginar($data),
            'config'  => config('barcodes'),
        ])->setPaper(
            config('barcodes.pagina.formato', 'letter'),
            config('barcodes.pagina.orientacion', 'portrait')
        );
    }

    /**
     * HTML del mismo Blade para calibrar en el navegador con Ctrl+P,
     * sin generar PDF en cada ajuste.
     */
    public function html(array $data): string
    {
        return view('pdf.barcodes', [
            'paginas' => $this->paginar($data),
            'config'  => config('barcodes'),
        ])->render();
    }
}