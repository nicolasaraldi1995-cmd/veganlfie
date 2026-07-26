<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // ponytail: el panel /admin (Filament + Livewire, modales, notificaciones)
        // no se probó contra un CSP y queda afuera para no arriesgar romperlo.
        // 'unsafe-inline' en script/style es el techo del sitio público (JSON-LD
        // de productos, estilos inline de Vue) -- subirlo de nivel implica pasar
        // a nonces por request.
        if (! $request->is('admin*')) {
            $response->headers->set('Content-Security-Policy', implode(' ', [
                "default-src 'self';",
                "script-src 'self' 'unsafe-inline';",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net;",
                "font-src 'self' https://fonts.bunny.net;",
                "img-src 'self' data:;",
                "connect-src 'self';",
                "frame-ancestors 'self';",
                "object-src 'none';",
                "base-uri 'self';",
                "form-action 'self';",
            ]));
        }

        return $response;
    }
}
