<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// esta clase se agrega a kernel del middleware
class SetEstablishment
{
    public function handle(Request $request, Closure $next)
    {
        $establishmentId = $request->header('X-Establishment-ID');

        if ($establishmentId) {
            // Guardamos el establecimiento activo en el container
            app()->instance('establishment_id', (int) $establishmentId);
        }

        return $next($request);
    }
}
