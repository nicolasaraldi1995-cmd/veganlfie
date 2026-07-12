<?php

namespace App\Filament\Widgets;

use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\Producto;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenOperador extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isOperador() ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Pedidos hoy', Pedido::whereDate('created_at', today())->where('estado', '!=', 'draft')->count())
                ->icon('heroicon-o-shopping-bag')
                ->color('success'),
            Stat::make('Pendientes', Pedido::where('estado', 'pending')->count())
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Productos activos', Producto::activos()->count())
                ->icon('heroicon-o-cube'),
            Stat::make('Sin stock', Presentacion::where('stock', '<=', 0)->activos()->count())
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
