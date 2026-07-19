<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// esta clase se agrega a kernel del middleware
class SetEstablishment
{
    public function handle(Request $request, Closure $next)
    {
        $establecimiento_id = request()->header('X-Establishment-Id');

        if ($establecimiento_id) {
            // Guardamos el establecimiento activo en el container
            app()->instance('establishment_id', (int) $establecimiento_id);
        }

        return $next($request);
    }
}
