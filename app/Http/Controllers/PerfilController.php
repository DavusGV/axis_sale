<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PerfilController extends Controller
{
    // Retorna los datos del usuario autenticado
    public function show(Request $request)
    {
        try {
            $user = $request->user()->load('establecimientos');
            $user->foto_perfil = $user->foto_perfil 
                ? asset('storage/' . $user->foto_perfil) 
                : null;

            return $this->Success(['user' => $user]);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al obtener perfil', 'message' => $e->getMessage()]);
        }
    }

    // Actualiza nombre, email, telefono, direccion, fecha_nacimiento y contrasena
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            $data = $request->validate([
                'name'             => 'sometimes|required|string|max:255',
                'email'            => 'sometimes|required|email|unique:users,email,' . $user->id,
                'telefono'         => 'nullable|string|max:20',
                'direccion'        => 'nullable|string|max:255',
                'fecha_nacimiento' => 'nullable|date',
                'password'         => 'nullable|string|min:8|confirmed',
            ]);

            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            return $this->Success(['message' => 'Perfil actualizado correctamente', 'user' => $user]);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al actualizar perfil', 'message' => $e->getMessage()]);
        }
    }

    // Sube o reemplaza la foto de perfil
    public function uploadFoto(Request $request)
    {
        try {
            $request->validate([
                'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $user = $request->user();

            // Eliminar foto anterior si existe
            if ($user->foto_perfil && Storage::disk('public')->exists($user->foto_perfil)) {
                Storage::disk('public')->delete($user->foto_perfil);
            }

            // Guardar nueva foto en storage/app/public/perfiles/
            $path = $request->file('foto')->store('perfiles', 'public');

            $user->update(['foto_perfil' => $path]);

            return $this->Success([
                'message'    => 'Foto actualizada correctamente',
                'foto_perfil' => asset('storage/' . $path),
            ]);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error al subir foto', 'message' => $e->getMessage()]);
        }
    }
}