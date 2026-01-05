<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EstablecimientoController extends Controller 
{
    // Mostrar lista de los establecimientos
    public function index(Request $request)
    {
        try {
            $search   = $request->get('search');
            $perPage  = $request->get('per_page', 10);
            $query = Establecimiento::query();

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                    ->orWhere('direccion', 'LIKE', "%{$search}%")
                    ->orWhere('telefono', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $establecimientos = $query
                ->orderBy('id', 'asc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $establecimientos->items(),
                'pagination' => [
                    'current_page' => $establecimientos->currentPage(),
                    'last_page'    => $establecimientos->lastPage(),
                    'per_page'     => $establecimientos->perPage(),
                    'total'        => $establecimientos->total(),
                ]
            ]);

        } catch (Exception $e) {
            return $this->InternalError([ 'error' => 'Error fetching establishments', 'message' => $e->getMessage()]);
        }
    }

    //Mostrar los datos de un establecimoento especifico
    public function show($id) {
        $estableciementos = Establecimiento::find($id);

        if (!$estableciementos) {
            return response()->json(['success' => false, 'message' => 'Establishment not found'], 400);
        }
        return response ()->json([ 'success' => true,'data' => $estableciementos]);
    }

    // Crear un establecimiento
    public function store (Request $request) {
        
        DB::beginTransaction();
        try {
            $data = $request->validate([
                'nombre'    => 'required|string|max:255',
                'direccion' => 'nullable|string|max:255',
                'telefono'  => 'nullable|string|max:255',
                'email'     => 'nullable|email|max:255'
            ]);
            $estableciemento = Establecimiento::create($data);
            DB::commit();
            return $this->Success($estableciemento);
        }catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error'=> 'Validation failed', 'message' => $e->errors()]);
        }
         catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error creating establishment', 'message' => $e->getMessage()]);
        }
    }

    // Actualizar datos a un establecimientos
    public function update(Request $request, $id) {

        DB::beginTransaction();
        try {
            $establecimiento = Establecimiento::find($id);

            if (!$establecimiento) {
            return $this->BadRequest(['message' => 'Establishment not found']);
        }

            $data = $request->validate([
                'nombre'    => 'required|string|max:255',
                'direccion' => 'nullable|string|max:255',
                'telefono'  => 'nullable|string|max:255',
                'email'     => 'nullable|email|max:255',
            ]);
            $establecimiento->update($data);
            DB::commit();

            return $this->Success($establecimiento);
        } catch (ValidationException $e) {
        DB::rollBack();
        return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()
        ]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([ 'error' => 'Error updated establishment', 'message' => $e->getMessage()]);
        }
    }

    // Eliminar un establecimiento
    public function destroy($id) {
        DB::beginTransaction();
        try {
            $establecimiento = Establecimiento::find($id);
            if (!$establecimiento) {
                return $this->BadRequest(['message' => 'Establishment not found']);
            }
            $establecimiento->delete();
            DB::commit();
            return $this->Success(['message' => 'Establishment deleted successfully']);
        }catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error deleting establishment', 'message' => $e->getMessage()]);
        }
    }
}