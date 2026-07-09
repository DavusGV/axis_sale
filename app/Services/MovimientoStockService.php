<?php

namespace App\Services;

use App\Models\MovimientoStock;
use App\Models\Products;
use Illuminate\Support\Facades\DB;
use Exception;

class MovimientoStockService
{
    /**
     * Registra una entrada manual al stock (compra, devolucion, ajuste positivo)
     * Suma la cantidad al stock actual del producto.
     */
    public function registrarEntrada(int $productoId, int $cantidad, ?string $motivo, int $usuarioId, int $establecimientoId): MovimientoStock
    {
        return $this->registrar('entrada', $productoId, $cantidad, $motivo, $usuarioId, $establecimientoId);
    }

    /**
     * Registra una reduccion manual (productos danados, caducados, robo, merma)
     * Resta la cantidad al stock actual del producto.
     * El motivo es obligatorio en este caso.
     */
    public function registrarReduccion(int $productoId, int $cantidad, string $motivo, int $usuarioId, int $establecimientoId): MovimientoStock
    {
        if (trim($motivo) === '') {
            throw new Exception('El motivo es obligatorio para registrar una reduccion.');
        }

        return $this->registrar('reduccion', $productoId, $cantidad, $motivo, $usuarioId, $establecimientoId);
    }

    /**
     * Logica central que aplica el movimiento al stock y registra el historial.
     * Usa lockForUpdate para evitar condiciones de carrera en concurrencia.
     */
    private function registrar(string $tipo, int $productoId, int $cantidad, ?string $motivo, int $usuarioId, int $establecimientoId): MovimientoStock
    {
        if ($cantidad <= 0) {
            throw new Exception('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($tipo, $productoId, $cantidad, $motivo, $usuarioId, $establecimientoId) {

            $producto = Products::where('id', $productoId)
                ->where('establecimiento_id', $establecimientoId)
                ->lockForUpdate()
                ->first();

            if (!$producto) {
                throw new Exception('Producto no encontrado o no pertenece a este establecimiento.');
            }

            // los servicios no manejan stock fisico
            if ($producto->es_servicio) {
                throw new Exception('Los servicios no manejan stock, no se pueden registrar movimientos.');
            }

            $stockAnterior = (int) $producto->stock;
            $stockNuevo    = $tipo === 'entrada'
                ? $stockAnterior + $cantidad
                : $stockAnterior - $cantidad;

            // validamos que la reduccion no deje stock negativo
            if ($stockNuevo < 0) {
                throw new Exception(
                    "Stock insuficiente. Stock actual: {$stockAnterior}, cantidad a reducir: {$cantidad}."
                );
            }

            // actualizamos el stock del producto
            $producto->stock = $stockNuevo;
            $producto->save();

            // registramos el movimiento en el historial
            return MovimientoStock::create([
                'establecimiento_id' => $establecimientoId,
                'producto_id'        => $productoId,
                'usuario_id'         => $usuarioId,
                'tipo'               => $tipo,
                'cantidad'           => $cantidad,
                'stock_anterior'     => $stockAnterior,
                'stock_nuevo'        => $stockNuevo,
                'motivo'             => $motivo,
            ]);
        });
    }
}