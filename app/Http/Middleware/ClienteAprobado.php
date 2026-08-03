<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta en el servidor todo lo que implique ver precios o cerrar un pedido
 * cuando la cuenta todavía no fue aprobada: esconderlo en la interfaz no
 * alcanza, porque las rutas se pueden pedir a mano.
 */
class ClienteAprobado
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! ($request->user()?->puedeVerPrecios() ?? false)) {
            return redirect()->route('home')
                ->with('mensaje', 'Tu cuenta todavía está en revisión. Te avisamos apenas la demos de alta.');
        }

        return $next($request);
    }
}
