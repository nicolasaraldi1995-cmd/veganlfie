<?php

namespace App\Filament\Widgets;

use App\Models\Pedido;
use App\Models\User;
use App\Services\CuentaClienteService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Abre el escritorio con lo que hay que hacer, no con plata.
 *
 * El resumen financiero arranca con "ventas hoy: $0" casi todas las mañanas,
 * que no dice qué hacer; esto muestra lo que está esperando una acción y
 * lleva de un click al lugar donde se resuelve.
 */
class ParaHoy extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isOperador());
    }

    /**
     * Cada número lleva a la lista de pedidos ya filtrada por ese estado, para
     * no tener que volver a buscarlo a mano.
     */
    private static function urlPedidos(string $estado): string
    {
        return \App\Filament\Resources\PedidoResource::getUrl('index', [
            'tableFilters' => ['estado' => ['value' => $estado]],
        ]);
    }

    protected function getStats(): array
    {
        $esAdmin = auth()->user()?->isAdmin() ?? false;

        $pendientes = Pedido::where('estado', 'pending')->count();
        $paraPreparar = Pedido::whereIn('estado', ['confirmed', 'preparing'])->count();

        $stats = [
            Stat::make('Pedidos por confirmar', $pendientes)
                ->description($pendientes ? 'Esperando tu OK' : 'Al día')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color($pendientes ? 'warning' : 'success')
                ->url(self::urlPedidos('pending')),
            Stat::make('Para preparar y entregar', $paraPreparar)
                ->description($paraPreparar ? 'Confirmados, sin entregar' : 'Nada pendiente')
                ->icon('heroicon-o-truck')
                ->color($paraPreparar ? 'info' : 'success')
                ->url(self::urlPedidos('confirmed')),
        ];

        if ($esAdmin) {
            $sinAprobar = User::where('aprobado', false)->count();
            $deudores = app(CuentaClienteService::class)->resumenPorCliente()
                ->filter(fn (array $c) => $c['saldo'] > 0.009);

            $stats[] = Stat::make('Cuentas nuevas', $sinAprobar)
                ->description($sinAprobar ? 'Esperando alta' : 'Ninguna pendiente')
                ->icon('heroicon-o-user-plus')
                ->color($sinAprobar ? 'warning' : 'success')
                ->url(\App\Filament\Resources\UserResource::getUrl('index'));

            $stats[] = Stat::make('Clientes con saldo', $deudores->count())
                ->description($deudores->isNotEmpty()
                    ? '$'.number_format($deudores->sum('saldo'), 0, ',', '.').' por cobrar'
                    : 'Nadie debe')
                ->icon('heroicon-o-banknotes')
                ->color($deudores->isNotEmpty() ? 'danger' : 'success')
                ->url(\App\Filament\Pages\ResumenCuenta::getUrl());
        }

        return $stats;
    }
}
