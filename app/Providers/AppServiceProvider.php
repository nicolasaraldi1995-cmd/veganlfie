<?php

namespace App\Providers;

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
}
