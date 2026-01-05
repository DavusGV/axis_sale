<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserEstablecimiento;

/*
    esta clase se agrega al kernel de middleware
    aqui es donde validamos el es establecimiento del frontend
    verificamos que el usuario tenga asignado el establecimiento y devolver el request
*/

class ValidateUserEstablishment
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $establishmentId = app('establishment_id');

        if (!$establishmentId) {
            return response()->json([
                'message' => 'Establishment not provided'
            ], 400);
        }

        $exists = UserEstablecimiento::where('user_id', $user->id)
            ->where('establecimiento_id', $establishmentId)
            ->exists();

        if (!$exists) {
            return response()->json([
                'message' => 'You do not have access to this establishment'
            ], 403);
        }

        return $next($request);
    }
}
