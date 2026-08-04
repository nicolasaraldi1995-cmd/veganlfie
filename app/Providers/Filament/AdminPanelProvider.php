<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\PedidosRecientes;
use App\Filament\Widgets\ResumenFinanciero;
use App\Filament\Widgets\ResumenOperador;
use App\Filament\Widgets\StockValorizado;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('VEGANLIFE Admin')
            ->colors([
                'primary' => Color::Green,
            ])
            // Sin colapsar: plegado se veían sólo los iconos y no se entendía
            // qué era cada cosa. El menú siempre muestra el nombre al lado.
            ->databaseTransactions()
            // De arriba abajo, por lo que más se usa en el día a día. Sin icono
            // en el grupo: Filament no admite icono en el grupo y en sus
            // pantallas a la vez, y el de cada pantalla es el que sirve.
            ->navigationGroups([
                'Ventas',
                'Catálogo',
                'Promociones',
                'Finanzas',
                'Herramientas',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                ResumenFinanciero::class,
                ResumenOperador::class,
                StockValorizado::class,
                PedidosRecientes::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
