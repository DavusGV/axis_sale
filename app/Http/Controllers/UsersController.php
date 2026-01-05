<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Models\User;
use Exception;
use App\DTOs\AuthDTO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class UsersController extends Controller 
{
    // Mostrar lista de los usuarios
    public function index(Request $request)
    {
        try {
            $search   = $request->get('search');
            $perPage  = $request->get('per_page', 10);
            $query = User::query();

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $users = $query
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page'    => $users->lastPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                ]
            ]);

        } catch (Exception $e) {
            return $this->InternalError([ 'error' => 'Error fetching users', 'message' => $e->getMessage()]);
        }
    }

    //Mostrar los datos de un usuario especifico
    public function show($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return $this->BadRequest(['message' => 'User not found']);
            }

            return $this->Success(['user' => $user]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error fetching user','message' => $e->getMessage()]);
        }
    }



    // Crear un usuario
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validar los datos del request usando DTO
            $data = AuthDTO::validate($request->all(), 'register');
            
            // Registrar usuario 
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            
            // Crear token de autenticación
            $token = $user->createToken('auth_token')->plainTextToken;
            
            DB::commit();
            
            return $this->Success([
                'token' => $token, 
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]);
            
        }  catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([ 'error' => 'Registration failed', 'message' => $e->getMessage()]);
        }
    }

    // Actualizar un usuario
    public function update(Request $request, $id) {
       DB::beginTransaction();
       try {
            $user = User::find($id);
            if (!$user) {
                return $this->BadRequest(['success' => false, 'message' => 'User not found'], 400);
            }

            $data = $request->validate([
                'name'     => 'sometimes|required|string|max:255',
                'email'    => 'sometimes|required|email|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:6|confirmed',
            ]);

            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);
            DB::commit();

            return $this->Success(['message' => 'User updated successfully', 'user' => $user]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error updating user', 'message' => $e->getMessage()]);
       }
    }   

    // Eliminar un usuario
    public function destroy($id) {
       DB::beginTransaction();
       try {
            $user = User::find($id);
            if (!$user) {
                return $this->BadRequest(['success' => false, 'message' => 'User not found'], 400);
            }
            $user->establecimientos()->detach();
            $user->delete();
            DB::commit();

            return $this->Success(['message' => 'User deleted successfully']);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error deleting user', 'message' => $e->getMessage()]);
       }
    }   

    public function establecimientos($id)
    {
        try {
            $user = User::with('establecimientos')->find($id);

            if (!$user) {
                return $this->BadRequest(['message' => 'User not found']);
            }

            $assignedIds = $user->establecimientos->pluck('id');
            $available = Establecimiento::whereNotIn('id', $assignedIds)->get();

            return $this->Success([
                'user_id' => $user->id,
                'assigned_establecimientos' => $user->establecimientos,
                'available_establecimientos' => $available
            ]);
        } catch (Exception $e) {
            return $this->InternalError([
                'error' => 'Error fetching establecimientos',
                'message' => $e->getMessage()
            ]);
        }
    }


    // Asignar un establecimiento al usuario
    public function assignEstablecimiento(Request $request, $userId) {
        DB::beginTransaction();
        try {
            $data = $request->validate([
                'establecimiento_id' => 'required|exists:establecimientos,id'
            ]);
            $user = User::findOrFail($userId);
            if ($user->establecimientos()->where('establecimiento_id', $data['establecimiento_id'])->exists()) {
                return $this->BadRequest(['message' => 'Establishment already assigned']);
            }
            $user->establecimientos()->attach($data['establecimiento_id']);
            DB::commit();

            return $this->Success(['message' => 'Establishment assigned successfully']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', ',message' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error assigning user', 'message' => $e->getMessage()]);
        }
    }

    // DesAsignarle el establecimiento al usuario 
    public function unassignEstablecimiento($userId, $establecimientoId) {
         DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);
            $user->establecimientos()->detach($establecimientoId);
            DB::commit();

            return $this->Success(['message' => 'Establishment unassigned successfully']);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Error unassingning user', 'message' => $e->getMessage()]);
        }
    }

}