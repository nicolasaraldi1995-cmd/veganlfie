<?php

namespace App\Filament\Resources\ComboResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\ComboResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCombos extends ListRecords
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = ComboResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
