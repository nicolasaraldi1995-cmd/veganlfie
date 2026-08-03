<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas del sitio público reservadas al personal de la distribuidora (la lista
 * de precios con la planilla, por ejemplo): un cliente logueado no tiene por
 * qué verlas.
 */
class SoloStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless((bool) ($user?->isAdmin() || $user?->isOperador()), 403);

        return $next($request);
    }
}
