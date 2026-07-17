<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\CuentaClienteService;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    public function getTabs(): array
    {
        $resumen = app(CuentaClienteService::class)->resumenPorCliente()
            ->where('role', 'cliente');

        $idsQueDeben = $resumen->filter(fn (array $c) => $c['saldo'] > 0.009)->pluck('id');
        $idsAlDia = $resumen->filter(fn (array $c) => $c['saldo'] <= 0.009)->pluck('id');

        return [
            'todos' => Tab::make('Todos'),
            'deben' => Tab::make('Deben')
                ->query(fn ($query) => $query->whereIn('id', $idsQueDeben))
                ->badge($idsQueDeben->count()),
            'al_dia' => Tab::make('Al día')
                ->query(fn ($query) => $query->whereIn('id', $idsAlDia))
                ->badge($idsAlDia->count()),
        ];
    }
}
