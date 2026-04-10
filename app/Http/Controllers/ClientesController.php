<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Models\Cliente;
use App\Models\PlanPago;
use App\Models\Ventas;
use App\Models\Cotizacion;
use Carbon\Carbon;

class ClientesController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('America/Mexico_City');
        Carbon::setLocale('es');
    }

    public function index(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $query = Cliente::where('establecimiento_id', $establecimiento_id)
                            ->where('activo', true);

            // filtro de busqueda por nombre, apellidos o telefono
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('apellido_p', 'like', "%{$search}%")
                      ->orWhere('apellido_m', 'like', "%{$search}%")
                      ->orWhere('telefono1', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $query->orderBy('nombre', 'asc');

            $perPage = $request->get('per_page', 15);
            $paginator = $query->paginate($perPage);

            // agregamos conteos de actividad por cliente
            $items = collect($paginator->items())->map(function ($cliente) {
                return array_merge($cliente->toArray(), [
                    'total_ventas'            => $cliente->ventas()->where('status', 'vendido')->count(),
                    'cotizaciones_pendientes' => $cliente->cotizaciones()->where('status', 'pendiente')->count(),
                    'creditos_activos'        => PlanPago::where('cliente_id', $cliente->id)
                                                    ->whereIn('estado', ['activo', 'atrasado', 'vencido'])
                                                    ->count(),
                ]);
            });

            return response()->json([
                'data'         => $items,
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ], 200);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener clientes.', 'details' => $e->getMessage()]);
        }
    }

    // busqueda rapida para el selector del modal de credito
    public function buscar(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $search = $request->get('q', '');

            $clientes = Cliente::where('establecimiento_id', $establecimiento_id)
                ->where('activo', true)
                ->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido_p', 'like', "%{$search}%")
                    ->orWhere('telefono1', 'like', "%{$search}%");
                })
                ->select('id', 'nombre', 'apellido_p', 'apellido_m', 'telefono1', 'foto')
                ->limit(10)
                ->get();

            return $this->Success(['clientes' => $clientes]);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al buscar clientes.', 'details' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            $request->validate([
                'nombre'     => 'required|string|max:100',
                'apellido_p' => 'required|string|max:100',
                'apellido_m' => 'nullable|string|max:100',
                'telefono1'  => 'required|string|max:20',
                'telefono2'  => 'nullable|string|max:20',
                'email'     => 'nullable|email|max:150',
                'direccion'  => 'nullable|string',
                'fecha_nacimiento' => 'nullable|date',
                'genero'     => 'nullable|in:masculino,femenino,otro',
                'foto'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'observaciones' => 'nullable|string',
            ]);

            // verificamos duplicado por telefono1
            if ($request->filled('telefono1')) {
                $dupTelefono1 = Cliente::where('establecimiento_id', $establecimiento_id)
                    ->where('telefono1', $request->telefono1)
                    ->where('activo', true)
                    ->first();

                if ($dupTelefono1) {
                    return $this->BadRequest(
                        'Ya existe un cliente con ese teléfono principal: ' .
                        $dupTelefono1->nombre . ' ' . $dupTelefono1->apellido_p
                    );
                }
            }

            // verificamos duplicado por telefono2
            if ($request->filled('telefono2')) {
                $dupTelefono2 = Cliente::where('establecimiento_id', $establecimiento_id)
                    ->where('telefono2', $request->telefono2)
                    ->where('activo', true)
                    ->first();

                if ($dupTelefono2) {
                    return $this->BadRequest(
                        'Ya existe un cliente con ese teléfono secundario: ' .
                        $dupTelefono2->nombre . ' ' . $dupTelefono2->apellido_p
                    );
                }
            }

            // verificamos duplicado por email
            if ($request->filled('email')) {
                $dupEmail = Cliente::where('establecimiento_id', $establecimiento_id)
                    ->where('email', $request->email)
                    ->where('activo', true)
                    ->first();

                if ($dupEmail) {
                    return $this->BadRequest(
                        'Ya existe un cliente con ese correo electrónico: ' .
                        $dupEmail->nombre . ' ' . $dupEmail->apellido_p
                    );
                }
            }

            $cliente = new Cliente();
            $cliente->establecimiento_id = $establecimiento_id;
            $cliente->nombre             = $request->nombre;
            $cliente->apellido_p         = $request->apellido_p;
            $cliente->apellido_m         = $request->apellido_m;
            $cliente->telefono1          = $request->telefono1;
            $cliente->telefono2          = $request->telefono2;
            $cliente->email              = $request->email;
            $cliente->direccion          = $request->direccion;
            $cliente->fecha_nacimiento   = $request->fecha_nacimiento;
            $cliente->genero             = $request->genero;
            $cliente->observaciones      = $request->observaciones;
            $cliente->activo             = true;

            // guardamos la foto si viene en la peticion
            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('clientes', 'public');
                $cliente->foto = $path;
            }

            $cliente->save();

            DB::commit();

            return $this->Success([
                'message' => 'Cliente registrado exitosamente.',
                'cliente' => $cliente
            ]);

        } catch (ValidationException $ve) {
            DB::rollBack();
            throw $ve;
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error al registrar cliente.', 'details' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $cliente = Cliente::where('id', $id)
                              ->where('establecimiento_id', $establecimiento_id)
                              ->first();

            if (!$cliente) {
                return $this->BadRequest('Cliente no encontrado.');
            }

            return $this->Success(['cliente' => $cliente]);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener cliente.', 'details' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            $cliente = Cliente::where('id', $id)
                              ->where('establecimiento_id', $establecimiento_id)
                              ->first();

            if (!$cliente) {
                return $this->BadRequest('Cliente no encontrado.');
            }

            // verificamos duplicado por telefono1 excluyendo al cliente actual
            if ($request->filled('telefono1')) {
                $dupTelefono1 = Cliente::where('establecimiento_id', $establecimiento_id)
                    ->where('telefono1', $request->telefono1)
                    ->where('activo', true)
                    ->where('id', '!=', $id)
                    ->first();

                if ($dupTelefono1) {
                    return $this->BadRequest(
                        'Ya existe un cliente con ese teléfono principal: ' .
                        $dupTelefono1->nombre . ' ' . $dupTelefono1->apellido_p
                    );
                }
            }

            // verificamos duplicado por telefono2 excluyendo al cliente actual
            if ($request->filled('telefono2')) {
                $dupTelefono2 = Cliente::where('establecimiento_id', $establecimiento_id)
                    ->where('telefono2', $request->telefono2)
                    ->where('activo', true)
                    ->where('id', '!=', $id)
                    ->first();

                if ($dupTelefono2) {
                    return $this->BadRequest(
                        'Ya existe un cliente con ese teléfono secundario: ' .
                        $dupTelefono2->nombre . ' ' . $dupTelefono2->apellido_p
                    );
                }
            }

            // verificamos duplicado por email excluyendo al cliente actual
            if ($request->filled('email')) {
                $dupEmail = Cliente::where('establecimiento_id', $establecimiento_id)
                    ->where('email', $request->email)
                    ->where('activo', true)
                    ->where('id', '!=', $id)
                    ->first();

                if ($dupEmail) {
                    return $this->BadRequest(
                        'Ya existe un cliente con ese correo electrónico: ' .
                        $dupEmail->nombre . ' ' . $dupEmail->apellido_p
                    );
                }
            }

            $request->validate([
                'nombre'     => 'sometimes|required|string|max:100',
                'apellido_p' => 'sometimes|required|string|max:100',
                'apellido_m' => 'nullable|string|max:100',
                'telefono1'  => 'sometimes|required|string|max:20',
                'telefono2'  => 'nullable|string|max:20',
                'email'     => 'nullable|email|max:150',
                'direccion'  => 'nullable|string',
                'fecha_nacimiento' => 'nullable|date',
                'genero'     => 'nullable|in:masculino,femenino,otro',
                'foto'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'observaciones' => 'nullable|string',
            ]);

            $cliente->nombre           = $request->nombre           ?? $cliente->nombre;
            $cliente->apellido_p       = $request->apellido_p       ?? $cliente->apellido_p;
            $cliente->apellido_m       = $request->apellido_m       ?? $cliente->apellido_m;
            $cliente->telefono1        = $request->telefono1        ?? $cliente->telefono1;
            $cliente->telefono2        = $request->telefono2        ?? $cliente->telefono2;
            $cliente->email           = $request->email           ?? $cliente->email;
            $cliente->direccion        = $request->direccion        ?? $cliente->direccion;
            $cliente->fecha_nacimiento = $request->fecha_nacimiento ?? $cliente->fecha_nacimiento;
            $cliente->genero           = $request->genero           ?? $cliente->genero;
            $cliente->observaciones    = $request->observaciones    ?? $cliente->observaciones;

            // si mandan foto nueva eliminamos la anterior y guardamos la nueva
            if ($request->hasFile('foto')) {
                if ($cliente->foto) {
                    Storage::disk('public')->delete($cliente->foto);
                }
                $path = $request->file('foto')->store('clientes', 'public');
                $cliente->foto = $path;
            }

            $cliente->save();

            DB::commit();

            return $this->Success([
                'message' => 'Cliente actualizado exitosamente.',
                'cliente' => $cliente
            ]);

        } catch (ValidationException $ve) {
            DB::rollBack();
            throw $ve;
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error al actualizar cliente.', 'details' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            $cliente = Cliente::where('id', $id)
                              ->where('establecimiento_id', $establecimiento_id)
                              ->first();

            if (!$cliente) {
                return $this->BadRequest('Cliente no encontrado.');
            }

            // verificamos credito activo antes de eliminar
            $creditoActivo = PlanPago::where('cliente_id', $id)
                ->whereIn('estado', ['activo', 'atrasado', 'vencido'])
                ->first();

            if ($creditoActivo) {
                return $this->BadRequest(
                    'No se puede eliminar el cliente porque tiene un crédito activo pendiente de liquidar.'
                );
            }

            // verificamos si tiene ventas registradas
            $tieneVentas = Ventas::where('cliente_id', $id)
                ->whereIn('status', ['no_pagado', 'pendiente'])
                ->exists();

            if ($tieneVentas) {
                return $this->BadRequest(
                    'No se puede eliminar el cliente porque tiene ventas registradas.'
                );
            }

            // verificamos cotizaciones pendientes
            $cotizacionPendiente = Cotizacion::where('cliente_id', $id)
                ->where('status', 'pendiente')
                ->exists();

            if ($cotizacionPendiente) {
                return $this->BadRequest(
                    'No se puede eliminar el cliente porque tiene cotizaciones pendientes.'
                );
            }

            // soft delete: solo marcamos como inactivo
            // para no perder el historial de creditos o ventas asociadas
            $cliente->activo = false;
            $cliente->save();

            DB::commit();

            return $this->Success(['message' => 'Cliente eliminado correctamente.']);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error al eliminar cliente.', 'details' => $e->getMessage()]);
        }
    }
}