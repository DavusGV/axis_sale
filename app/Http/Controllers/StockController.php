<?php

namespace App\Http\Controllers;

use App\Services\MovimientoStockService;
use App\Services\StockDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use \Illuminate\Support\Facades\Auth;
use Exception;

class StockController extends Controller
{
    protected MovimientoStockService $movimientoService;
    protected StockDashboardService $dashboardService;

    public function __construct(
        MovimientoStockService $movimientoService,
        StockDashboardService $dashboardService
    ) {
        $this->movimientoService = $movimientoService;
        $this->dashboardService  = $dashboardService;
    }

    /**
     * Registra un movimiento manual (entrada o reduccion) sobre el stock de un producto.
     */
    public function registrarMovimiento(Request $request)
    {
        try {
            $data = $request->validate([
                'producto_id' => 'required|integer|exists:productos,id',
                'tipo'        => 'required|in:entrada,reduccion',
                'cantidad'    => 'required|integer|min:1',
                'motivo'      => 'nullable|string|max:500',
            ]);

            $establecimientoId = app('establishment_id');
            $usuarioId = Auth::id();

            if (!$usuarioId) {
                return $this->BadRequest(['error' => 'Usuario no identificado.']);
            }

            // motivo obligatorio en reduccion (la regla la encapsula el service)
            if ($data['tipo'] === 'reduccion') {
                if (empty(trim($data['motivo'] ?? ''))) {
                    return $this->BadRequest([
                        'error'    => 'Validation failed',
                        'messages' => ['motivo' => ['El motivo es obligatorio para una reduccion.']],
                    ]);
                }
                $movimiento = $this->movimientoService->registrarReduccion(
                    $data['producto_id'],
                    $data['cantidad'],
                    $data['motivo'],
                    $usuarioId,
                    $establecimientoId
                );
            } else {
                $movimiento = $this->movimientoService->registrarEntrada(
                    $data['producto_id'],
                    $data['cantidad'],
                    $data['motivo'] ?? null,
                    $usuarioId,
                    $establecimientoId
                );
            }

            return $this->Success([
                'message'    => 'Movimiento registrado correctamente.',
                'movimiento' => $movimiento,
            ]);

        } catch (ValidationException $e) {
            return $this->BadRequest([
                'error'    => 'Validation failed',
                'messages' => $e->errors(),
            ]);
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al registrar el movimiento.',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Devuelve la cronologia completa de un producto: KPIs, datos de grafica y timeline.
     * Si no se mandan fechas usa los ultimos 2 meses por defecto.
     */
    public function cronologia(Request $request)
    {
        try {
            $data = $request->validate([
                'producto_id'  => 'required|integer|exists:productos,id',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            ]);

            $establecimientoId = app('establishment_id');

            // por defecto: del inicio del mes pasado al fin del mes actual
            $fechaInicio = $data['fecha_inicio']
                ?? Carbon::now()->subMonth()->startOfMonth()->toDateString();
            $fechaFin = $data['fecha_fin']
                ?? Carbon::now()->endOfMonth()->toDateString();

            $resultado = $this->dashboardService->obtenerCronologia(
                $data['producto_id'],
                $fechaInicio,
                $fechaFin,
                $establecimientoId
            );

            return $this->Success($resultado);

        } catch (ValidationException $e) {
            return $this->BadRequest([
                'error'    => 'Validation failed',
                'messages' => $e->errors(),
            ]);
        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al obtener la cronologia.',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Devuelve la lista de productos del establecimiento para el select del dashboard.
     * Devuelve solo lo necesario: id, nombre, codigo, stock, es_servicio.
     */
    public function productosParaSelect(Request $request)
    {
        try {
            $establecimientoId = app('establishment_id');

            $query = \App\Models\Products::where('establecimiento_id', $establecimientoId)
                ->select('id', 'nombre', 'codigo', 'stock', 'es_servicio');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%");
                });
            }

            $productos = $query->orderBy('nombre')->limit(100)->get();

            return $this->Success(['productos' => $productos]);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al obtener los productos.',
                'message' => $e->getMessage(),
            ]);
        }
    }
}