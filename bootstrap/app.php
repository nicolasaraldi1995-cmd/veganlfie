<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SoloStaff;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Producción corre en Laravel Cloud, detrás de un balanceador. Sin
        // confiar en el proxy, request()->ip() devuelve la IP del balanceador
        // -- la misma para todos-- y los topes de peticiones (entrar, registrar,
        // recuperar) se vuelven un solo balde global: un bot dejaba a todos los
        // clientes afuera. También arregla isSecure() detrás del balanceador.
        $middleware->trustProxies(at: '*');

        // Sólo se responde al dominio propio. Sin esto, un pedido con
        // "Host: evil.com" hacía que el mail de recuperar contraseña apuntara a
        // evil.com/reset-password/<token>: el que hacía clic le entregaba el
        // token al atacante. El dominio sale de APP_URL para no clavarlo acá.
        // Va como closure: config() todavía no existe cuando corre este bloque,
        // pero sí cuando el middleware evalúa la lista.
        $middleware->trustHosts(at: fn () => array_values(array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ])));

        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            // Ata la sesión a la contraseña: cambiarla (o resetearla) cierra
            // las demás. Sin esto, quien se hubiera quedado con la cookie
            // seguía entrando aunque la víctima cambiara la clave. El panel ya
            // lo tenía; al sitio le faltaba.
            AuthenticateSession::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff' => SoloStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
