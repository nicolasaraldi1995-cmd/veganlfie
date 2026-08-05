<?php

namespace App\Filament\Resources\MarcaResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\MarcaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarca extends EditRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = MarcaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
