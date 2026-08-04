<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->limitesPorSeparado();

        // Ocho caracteres con letras y números. Sin esto rige el mínimo de
        // fábrica, que son ocho caracteres a secas: "12345678" pasaba. Fuera
        // de producción se afloja, para no pelear con las claves de prueba.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()
            : Password::min(8));

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Un contador por cosa, no uno solo para todas.
     *
     * Laravel arma la clave del tope con el id del usuario a secas, sin la
     * ruta (ThrottleRequests::resolveRequestSignature). O sea que "throttle:30"
     * del carrito y "throttle:10" del checkout compartían un mismo balde: el
     * cliente que tocaba diez veces el más/menos en el carrito ya no podía
     * confirmar el pedido, le salía un error y perdíamos la venta.
     *
     * Con topes con nombre, el nombre entra en la clave y cada uno cuenta lo
     * suyo. Para el que no está logueado la clave es la IP, así que le pasaba
     * lo mismo entre las puertas de entrada.
     */
    private function limitesPorSeparado(): void
    {
        $porUsuarioOIp = fn (Request $peticion) => $peticion->user()?->id ?: $peticion->ip();

        foreach ([
            'carrito' => 60,
            'pedido' => 60,
            'checkout' => 10,
            'buscar' => 60,
            'entrar' => 20,
        ] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $peticion) => Limit::perMinute($porMinuto)->by($nombre.'|'.$porUsuarioOIp($peticion)));
        }

        // Las de alta y recuperación son por hora: acá lo que se frena no es a
        // alguien apurado, es un barrido automático.
        foreach (['registrarse' => 5, 'recuperar' => 5, 'restablecer' => 10] as $nombre => $porHora) {
            RateLimiter::for($nombre, fn (Request $peticion) => Limit::perHour($porHora)->by($nombre.'|'.$porUsuarioOIp($peticion)));
        }
    }
}
