<?php

namespace App\Filament\Resources\PrecioResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\PrecioResource;
use Filament\Resources\Pages\ListRecords;

class ListPrecios extends ListRecords
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = PrecioResource::class;
}
