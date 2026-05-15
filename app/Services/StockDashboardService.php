<?php

namespace App\Services;

use App\Models\MovimientoStock;
use App\Models\Products;
use App\Models\Ventas;
use App\Models\VentasDetalles;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StockDashboardService
{
    protected MovimientoStockService $movimientoService;

    public function __construct(MovimientoStockService $movimientoService)
    {
        $this->movimientoService = $movimientoService;
    }

    /**
     * Devuelve el resumen completo del dashboard para un producto en un rango de fechas:
     * - KPIs: stock actual, total entradas, total vendido, total reducido, ultimo movimiento
     * - Datos para la grafica apilada por dia
     * - Timeline cronologico de movimientos
     */
    public function obtenerCronologia(int $productoId, string $fechaInicio, string $fechaFin, int $establecimientoId): array
    {
        $producto = Products::where('id', $productoId)
            ->where('establecimiento_id', $establecimientoId)
            ->first();

        if (!$producto) {
            throw new \Exception('Producto no encontrado.');
        }

        // tomamos el rango inclusivo de dia completo
        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $fin    = Carbon::parse($fechaFin)->endOfDay();

        // 1) movimientos manuales (entradas y reducciones) en el rango
        $movimientosManuales = $this->obtenerMovimientosManuales($productoId, $inicio, $fin);

        // 2) salidas por venta calculadas dinamicamente con reglas de validacion
        $salidasVentas = $this->obtenerSalidasPorVentas($productoId, $establecimientoId, $inicio, $fin);

        // 3) timeline unificado ordenado por fecha desc
        $timeline = $this->construirTimeline($movimientosManuales, $salidasVentas);

        // 4) datos agrupados por dia para la grafica
        $grafica = $this->construirSeriesGrafica($movimientosManuales, $salidasVentas, $inicio, $fin);

        // 5) KPIs
        $kpis = [
            'stock_actual'      => (int) $producto->stock,
            'total_entradas'    => $movimientosManuales->where('tipo', 'entrada')->sum('cantidad'),
            'total_vendido'     => $salidasVentas->sum('cantidad'),
            'total_reducido'    => $movimientosManuales->where('tipo', 'reduccion')->sum('cantidad'),
            'ultimo_movimiento' => $timeline->first()['fecha'] ?? null,
            'es_servicio'       => (bool) $producto->es_servicio,
        ];

        return [
            'producto' => [
                'id'           => $producto->id,
                'nombre'       => $producto->nombre,
                'codigo'       => $producto->codigo,
                'stock_actual' => (int) $producto->stock,
                'es_servicio'  => (bool) $producto->es_servicio,
            ],
            'kpis'     => $kpis,
            'grafica'  => $grafica,
            'timeline' => $timeline->values()->toArray(),
            'rango'    => [
                'inicio' => $inicio->toDateString(),
                'fin'    => $fin->toDateString(),
            ],
        ];
    }

    /**
     * Trae entradas y reducciones manuales del producto en el rango.
     */
    private function obtenerMovimientosManuales(int $productoId, Carbon $inicio, Carbon $fin): Collection
    {
        return MovimientoStock::with('usuario:id,name')
            ->where('producto_id', $productoId)
            ->whereBetween('created_at', [$inicio, $fin])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($mov) {
                return [
                    'id'             => $mov->id,
                    'tipo'           => $mov->tipo, // entrada / reduccion
                    'cantidad'       => (int) $mov->cantidad,
                    'stock_anterior' => (int) $mov->stock_anterior,
                    'stock_nuevo'    => (int) $mov->stock_nuevo,
                    'motivo'         => $mov->motivo,
                    'usuario'        => $mov->usuario->name ?? 'Sistema',
                    'fecha'          => $mov->created_at->format('Y-m-d'),
                    'hora'           => $mov->created_at->format('H:i:s'),
                    'fecha_completa' => $mov->created_at->format('Y-m-d H:i:s'),
                    'referencia'     => null,
                ];
            });
    }

    /**
     * Trae las salidas del producto calculadas desde la tabla de ventas.
     * Aplica las MISMAS reglas de validacion que IngresosControlador:
     *  - Excluye ventas con status = 'cancelada'
     *  - Excluye ventas a credito huerfanas (metodo_pago='credito' sin plan_pago)
     *  - Incluye ventas normales (contado) y ventas a credito con plan valido
     */
    private function obtenerSalidasPorVentas(int $productoId, int $establecimientoId, Carbon $inicio, Carbon $fin): Collection
    {
        $detalles = VentasDetalles::with([
                'venta' => function ($q) {
                    $q->with('planPago', 'usuario:id,name');
                },
            ])
            ->where('producto_id', $productoId)
            ->whereHas('venta', function ($q) use ($establecimientoId, $inicio, $fin) {
                $q->where('establecimiento_id', $establecimientoId)
                  ->whereBetween('created_at', [$inicio, $fin])
                  // excluimos canceladas
                  ->where(function ($sub) {
                      $sub->where('status', '!=', 'cancelada')
                          ->orWhereNull('status');
                  });
            })
            ->get();

        return $detalles->filter(function ($detalle) {
                $venta = $detalle->venta;
                if (!$venta) {
                    return false;
                }

                // detectamos venta huerfana: metodo credito sin plan ligado
                $esCreditoHuerfana = strtolower(trim($venta->metodo_pago ?? '')) === 'credito'
                    && $venta->planPago === null;

                return !$esCreditoHuerfana;
            })
            ->map(function ($detalle) {
                $venta = $detalle->venta;
                return [
                    'id'             => 'v_' . $detalle->id, // prefijo para no chocar con id de movimientos
                    'tipo'           => 'venta',
                    'cantidad'       => (int) $detalle->cantidad,
                    'stock_anterior' => null,
                    'stock_nuevo'    => null,
                    'motivo'         => null,
                    'usuario'        => $venta->usuario->name ?? 'Sistema',
                    'fecha'          => $venta->created_at->format('Y-m-d'),
                    'hora'           => $venta->created_at->format('H:i:s'),
                    'fecha_completa' => $venta->created_at->format('Y-m-d H:i:s'),
                    'referencia'     => $venta->folio,
                ];
            })
            ->values();
    }

    /**
     * Une los movimientos manuales con las ventas en una sola lista
     * ordenada cronologicamente (mas reciente primero).
     */
    private function construirTimeline(Collection $movimientos, Collection $ventas): Collection
    {
        return $movimientos
            ->concat($ventas)
            ->sortByDesc('fecha_completa')
            ->values();
    }

    /**
     * Construye las series para la grafica de barras apiladas.
     * Solo incluye los dias que tuvieron al menos un movimiento (entrada, venta o reduccion).
     * Los dias sin movimientos se omiten para no saturar el eje X.
     */
    private function construirSeriesGrafica(Collection $movimientos, Collection $ventas, Carbon $inicio, Carbon $fin): array
    {
        // agrupamos por fecha (YYYY-MM-DD) cada tipo
        $entradasPorDia = $movimientos->where('tipo', 'entrada')
            ->groupBy('fecha')
            ->map(fn ($items) => array_sum(array_column($items->toArray(), 'cantidad')));

        $reduccionesPorDia = $movimientos->where('tipo', 'reduccion')
            ->groupBy('fecha')
            ->map(fn ($items) => array_sum(array_column($items->toArray(), 'cantidad')));

        $ventasPorDia = $ventas->groupBy('fecha')
            ->map(fn ($items) => array_sum(array_column($items->toArray(), 'cantidad')));

        // unimos las fechas de los tres tipos en un set unico, sin duplicados
        // y las ordenamos cronologicamente para que la grafica se lea de izquierda a derecha
        $fechasConMovimiento = collect()
            ->merge($entradasPorDia->keys())
            ->merge($ventasPorDia->keys())
            ->merge($reduccionesPorDia->keys())
            ->unique()
            ->sort()
            ->values();

        $categorias       = [];
        $serieEntradas    = [];
        $serieVentas      = [];
        $serieReducciones = [];

        foreach ($fechasConMovimiento as $clave) {
            // formato dd/mm para el label visible del eje X
            $categorias[] = Carbon::parse($clave)->format('d/m');

            $serieEntradas[]    = (int) ($entradasPorDia[$clave] ?? 0);
            $serieVentas[]      = (int) ($ventasPorDia[$clave] ?? 0);
            $serieReducciones[] = (int) ($reduccionesPorDia[$clave] ?? 0);
        }

        return [
            'categorias' => $categorias,
            'series'     => [
                ['name' => 'Entradas',    'data' => $serieEntradas],
                ['name' => 'Ventas',      'data' => $serieVentas],
                ['name' => 'Reducciones', 'data' => $serieReducciones],
            ],
        ];
    }
}