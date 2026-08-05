<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\PedidoResource;
use Filament\Resources\Pages\ListRecords;

class ListPedidos extends ListRecords
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = PedidoResource::class;
}
